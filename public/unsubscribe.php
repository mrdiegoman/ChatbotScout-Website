<?php
/**
 * Unsubscribe from marketing emails
 * One-click unsubscribe via token
 */

require '../inc/bootstrap.php';

$token   = trim($_GET['token'] ?? '');
$success = false;
$error   = false;

if (empty($token)) {
    $error = 'Invalid unsubscribe link.';
} else {
    try {
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("
            SELECT id, marketing_emails
            FROM users
            WHERE unsubscribe_token = :token
            LIMIT 1
        ");
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = 'Invalid or expired unsubscribe link.';
        } else {
            $db->prepare("
                UPDATE users
                SET marketing_emails = 0
                WHERE unsubscribe_token = :token
            ")->execute([':token' => $token]);

            $success = true;
        }
    } catch (Exception $e) {
        error_log('Unsubscribe error: ' . $e->getMessage());
        $error = 'Something went wrong. Please try again later.';
    }
}

require '../inc/header.php';
?>

<link rel="stylesheet" href="../css/unsubscribe.css">

<div class="unsub-wrapper">
    <div class="unsub-card">
        <?php if ($success): ?>
            <div class="unsub-icon unsub-icon--success">✓</div>
            <h1>You've been unsubscribed</h1>
            <p>You will no longer receive marketing emails from ChatbotScout.</p>
            <p class="unsub-note">
                You can re-enable marketing emails anytime in your
                <a href="profile.php?tab=settings">account settings</a>.
            </p>
        <?php elseif ($error): ?>
            <div class="unsub-icon unsub-icon--error">✕</div>
            <h1>Something went wrong</h1>
            <p><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <a href="index.php" class="unsub-btn">Back to ChatbotScout</a>
    </div>
</div>

<?php require '../inc/footer.php'; ?>