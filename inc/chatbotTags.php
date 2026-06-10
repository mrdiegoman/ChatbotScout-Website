<?php
/**
 * System tag synchronization for chatbot listings.
 *
 * Keep this logic in application code because the rules depend on business
 * state such as paid promotion access and admin-controlled affiliate status.
 */

function chatbotSystemTagId(PDO $db, string $name): ?string
{
    $stmt = $db->prepare("SELECT id FROM tags WHERE name = :name LIMIT 1");
    $stmt->execute(['name' => $name]);
    $tagId = $stmt->fetchColumn();

    return $tagId ? (string) $tagId : null;
}

function chatbotSetTag(PDO $db, string $chatbotId, string $tagName, bool $enabled): void
{
    $tagId = chatbotSystemTagId($db, $tagName);
    if (!$tagId) {
        return;
    }

    if ($enabled) {
        $stmt = $db->prepare("
            INSERT IGNORE INTO chatbot_tags (id, chatbot_id, tag_id)
            VALUES (:id, :chatbot_id, :tag_id)
        ");
        $stmt->execute([
            'id' => generateUUID(),
            'chatbot_id' => $chatbotId,
            'tag_id' => $tagId,
        ]);
        return;
    }

    $stmt = $db->prepare("
        DELETE FROM chatbot_tags
        WHERE chatbot_id = :chatbot_id
          AND tag_id = :tag_id
    ");
    $stmt->execute([
        'chatbot_id' => $chatbotId,
        'tag_id' => $tagId,
    ]);
}

function chatbotHasActivePromotion(PDO $db, string $chatbotId, ?string $slotType = null): bool
{
    $params = ['chatbot_id' => $chatbotId];
    $slotJoin = '';
    $slotWhere = '';

    if ($slotType !== null) {
        $slotJoin = 'JOIN promotions p ON p.id = s.promotions_id';
        $slotWhere = 'AND p.slot_type = :slot_type';
        $params['slot_type'] = $slotType;
    }

    $stmt = $db->prepare("
        SELECT 1
        FROM subscriptions s
        {$slotJoin}
        WHERE s.chatbot_id = :chatbot_id
          AND s.subscription_type = 'promotion'
          AND s.status IN ('active', 'trialing', 'past_due')
          AND s.end_date > NOW()
          {$slotWhere}
        LIMIT 1
    ");
    $stmt->execute($params);

    return (bool) $stmt->fetchColumn();
}

function syncChatbotAffiliateTag(PDO $db, string $chatbotId): void
{
    $stmt = $db->prepare("
        SELECT has_affiliate_program
        FROM chatbots
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute(['id' => $chatbotId]);
    $hasAffiliateProgram = $stmt->fetchColumn();

    if ($hasAffiliateProgram === false) {
        return;
    }

    chatbotSetTag($db, $chatbotId, 'Affiliate', !empty($hasAffiliateProgram));
}

function syncChatbotSponsoredTag(PDO $db, string $chatbotId): void
{
    $hasActivePromotion = chatbotHasActivePromotion($db, $chatbotId);

    chatbotSetTag($db, $chatbotId, 'Sponsored', $hasActivePromotion);

    $stmt = $db->prepare("
        UPDATE chatbots
        SET is_sponsored = :is_sponsored
        WHERE id = :id
    ");
    $stmt->execute([
        'is_sponsored' => $hasActivePromotion ? 1 : 0,
        'id' => $chatbotId,
    ]);
}

function syncChatbotSystemTags(PDO $db, string $chatbotId): void
{
    syncChatbotAffiliateTag($db, $chatbotId);
    syncChatbotSponsoredTag($db, $chatbotId);
}

function syncChatbotsSponsoredTagsForStripeSubscription(PDO $db, string $stripeSubscriptionId): void
{
    if ($stripeSubscriptionId === '') {
        return;
    }

    $stmt = $db->prepare("
        SELECT DISTINCT chatbot_id
        FROM subscriptions
        WHERE stripe_subscription_id = :stripe_subscription_id
          AND subscription_type = 'promotion'
    ");
    $stmt->execute(['stripe_subscription_id' => $stripeSubscriptionId]);

    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $chatbotId) {
        syncChatbotSponsoredTag($db, (string) $chatbotId);
    }
}

