<?php
/**
 * CRON: Cancel Subscriptions At Period End
 *
 * Zruší subscriptions kde:
 *  - cancel_at_period_end = TRUE
 *  - end_date < NOW()  (perióda skončila)
 *
 * Spúšťaj raz denne (pred expireSubscriptions):
 *   0 0 * * * php /path/to/cron/cancelAtPeriodEnd.php
 *
 * Na Windows (manuálne testovanie):
 *   php cron/cancelAtPeriodEnd.php
 */

define('BOOTSTRAP_LOADED', true);
require_once __DIR__ . '/../inc/bootstrap.php';

$db = Database::getInstance()->getConnection();

echo "[" . date('Y-m-d H:i:s') . "] cancelAtPeriodEnd started\n";

try {
    $stmt = $db->prepare("
        SELECT id, user_id, end_date, cancelled_at
        FROM subscriptions
        WHERE status               IN ('active', 'past_due')
          AND cancel_at_period_end = TRUE
          AND end_date             < NOW()
    ");
    $stmt->execute();
    $toCancel = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($toCancel)) {
        echo "No subscriptions to cancel.\n";
        exit(0);
    }

    $count = 0;
    foreach ($toCancel as $sub) {
        // cancelled_at was set when user requested cancellation,
        // but if it's NULL for some reason, set it now
        $cancelledAt = $sub['cancelled_at'] ?? date('Y-m-d H:i:s');

        $db->prepare("
            UPDATE subscriptions
            SET status       = 'cancelled',
                cancelled_at = :cancelled_at,
                auto_renew   = FALSE,
                updated_at   = NOW()
            WHERE id = :id
        ")->execute([
            'cancelled_at' => $cancelledAt,
            'id'           => $sub['id'],
        ]);

        $count++;
        echo "  Cancelled subscription {$sub['id']} (end_date: {$sub['end_date']})\n";
    }

    echo "Done. Cancelled {$count} subscription(s).\n";

} catch (Exception $e) {
    error_log("cancelAtPeriodEnd cron error: " . $e->getMessage());
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}