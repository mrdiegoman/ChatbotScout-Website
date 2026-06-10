<?php
/**
 * Legacy cleanup for Popular tags.
 *
 * Popular is now computed at query time from the current popularity ranking,
 * so persisted Popular rows in chatbot_tags would become stale.
 */

require __DIR__ . '/../inc/bootstrap.php';

try {
    $db = Database::getInstance()->getConnection();

    $tagStmt = $db->prepare("SELECT id FROM tags WHERE name = 'Popular' LIMIT 1");
    $tagStmt->execute();
    $popularTagId = $tagStmt->fetchColumn();

    if (!$popularTagId) {
        error_log('[updatePopularTags] Popular tag not found; nothing to clean.');
        exit(0);
    }

    $deleteStmt = $db->prepare("
        DELETE FROM chatbot_tags
        WHERE tag_id = :tag_id
    ");
    $deleteStmt->execute(['tag_id' => $popularTagId]);

    error_log('[updatePopularTags] Removed stale persisted Popular tag links. Popular badges are now computed dynamically.');
} catch (Exception $e) {
    error_log('[updatePopularTags] Error: ' . $e->getMessage());
    exit(1);
}

