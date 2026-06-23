<?php
/**
 * AJAX endpoint for the signed-in user's paginated billing history.
 */

require '../../inc/bootstrap.php';
require_once '../../inc/billingHistory.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!$isLoggedIn || empty($user['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please sign in again.']);
    exit;
}

try {
    $filters = billingHistoryFilters($_GET);
    $history = billingHistoryForUser(
        Database::getInstance()->getConnection(),
        (string) $user['id'],
        $filters,
        billingHistoryPage($_GET['billing_page'] ?? 1),
        10
    );

    $payments = array_map(static function (array $payment): array {
        return [
            'id' => (string) $payment['id'],
            'date_label' => (string) $payment['date_label'],
            'package_name' => (string) $payment['package_name'],
            'package_meta' => (string) $payment['package_meta'],
            'method_label' => (string) $payment['method_label'],
            'amount_label' => (string) $payment['amount_label'],
            'status' => (string) $payment['status'],
            'status_label' => (string) $payment['status_label'],
            'invoice_url' => $payment['invoice_url'] ? (string) $payment['invoice_url'] : null,
        ];
    }, $history['payments']);

    echo json_encode([
        'success' => true,
        'payments' => $payments,
        'pagination' => [
            'page' => $history['page'],
            'per_page' => $history['per_page'],
            'total' => $history['total'],
            'total_pages' => $history['total_pages'],
            'offset' => $history['offset'],
        ],
        'has_filters' => $history['has_filters'],
    ]);
} catch (Throwable $e) {
    error_log('getBillingHistory error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load billing history. Please try again.']);
}
