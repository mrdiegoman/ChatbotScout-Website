<?php
/**
 * Cron Job: Update Popular Tags
 * Runs daily — assigns "Popular" tag to top 5 chatbots by popularity score.
 * Only touches is_system tags, never manual tags.
 *
 * Setup: 0 0 * * * php /path/to/cron/updatePopularTags.php
 */

require __DIR__ . '/../inc/bootstrap.php';

try {
    $db = Database::getInstance()->getConnection();

    // ── Get Popular tag ID ────────────────────────────────────────────────────
    $tagStmt = $db->prepare("SELECT id FROM tags WHERE name = 'Popular' AND is_system = 1 LIMIT 1");
    $tagStmt->execute();
    $popularTagId = $tagStmt->fetchColumn();

    if (!$popularTagId) {
        error_log('[updatePopularTags] Popular tag not found in DB.');
        exit(1);
    }

    // ── Get top chatbots by popularity score ─────────────────────────────────
    $top5Stmt = $db->query("
        SELECT id
        FROM chatbots
        WHERE status = 'active'
        AND deleted_at IS NULL
        AND rating_average > 0
        AND click_count > 0
        ORDER BY (rating_average * LOG(click_count + 1)) DESC,
                rating_count DESC,
                click_count DESC
        LIMIT 5
    ");
    $top5Ids = $top5Stmt->fetchAll(PDO::FETCH_COLUMN);

    // ── If less than 5, fill up with most clicked chatbots ────────────────────
    if (count($top5Ids) < 5) {
        $needed  = 5 - count($top5Ids);
        $exclude = empty($top5Ids)
            ? "''"
            : implode(',', array_map(fn($id) => "'{$id}'", $top5Ids));

        $fillStmt = $db->query("
            SELECT id
            FROM chatbots
            WHERE status = 'active' AND click_count > 0
            AND deleted_at IS NULL
            AND id NOT IN ({$exclude})
            ORDER BY click_count DESC
            LIMIT {$needed}
        ");
        $top5Ids = array_merge($top5Ids, $fillStmt->fetchAll(PDO::FETCH_COLUMN));
    }

    if (empty($top5Ids)) {
        error_log('[updatePopularTags] No active chatbots found.');
        exit(0);
    }

    $db->beginTransaction();

    // ── Remove Popular tag from all chatbots ──────────────────────────────────
    $deleteStmt = $db->prepare("
        DELETE FROM chatbot_tags
        WHERE tag_id = :tag_id
    ");
    $deleteStmt->execute([':tag_id' => $popularTagId]);

    // ── Assign Popular tag to top 5 ───────────────────────────────────────────
    $insertStmt = $db->prepare("
        INSERT IGNORE INTO chatbot_tags (id, chatbot_id, tag_id)
        VALUES (UUID(), :chatbot_id, :tag_id)
    ");

    foreach ($top5Ids as $chatbotId) {
        $insertStmt->execute([
            ':chatbot_id' => $chatbotId,
            ':tag_id'     => $popularTagId,
        ]);
    }

    $db->commit();
    error_log('[updatePopularTags] Successfully updated Popular tags for ' . count($top5Ids) . ' chatbots.');

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[updatePopularTags] Error: ' . $e->getMessage());
    exit(1);
}