<?php
/**
 * CRON: Expire Subscriptions
 *
 * Nastaví status = 'expired' pre všetky aktívne subscriptions,
 * ktorým prešiel end_date a nemajú cancel_at_period_end (tie rieši iný cron).
 *
 * Spúšťaj raz denne, napr. o polnoci:
 *   0 0 * * * php /path/to/cron/expireSubscriptions.php
 *
 * Na Windows (manuálne testovanie):
 *   php cron/expireSubscriptions.php
 */

define('BOOTSTRAP_LOADED', true);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

$db = Database::getInstance()->getConnection();

echo "[" . date('Y-m-d H:i:s') . "] expireSubscriptions started\n";

try {
    // Find all active subscriptions that have passed their end_date
    // and are NOT marked for cancel_at_period_end (those are handled separately)
    $stmt = $db->prepare("
        SELECT id, user_id, chatbot_id, end_date
        FROM subscriptions
        WHERE status              = 'active'
          AND end_date           < NOW()
          AND cancel_at_period_end = FALSE
          AND auto_renew          = FALSE
    ");
    $stmt->execute();
    $expired = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($expired)) {
        echo "No subscriptions to expire.\n";
        exit(0);
    }

    $count = 0;
    foreach ($expired as $sub) {
        $db->prepare("
            UPDATE subscriptions
            SET status     = 'expired',
                updated_at = NOW()
            WHERE id = :id
        ")->execute(['id' => $sub['id']]);

        $count++;
        echo "  Expired subscription {$sub['id']} (end_date: {$sub['end_date']})\n";
    }

    echo "Done. Expired {$count} subscription(s).\n";

} catch (Exception $e) {
    error_log("expireSubscriptions cron error: " . $e->getMessage());
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}