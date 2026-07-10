<?php
/**
 * Marketing Email Sender Script
 *
 * Default TEST mode sends email only to MARKETING_TEST_EMAIL or --email=...
 * LIVE mode sends to all verified users with marketing_emails = 1.
 *
 * Usage:
 *   php scripts/sendMarketingEmail.php
 *   php scripts/sendMarketingEmail.php --email=test@example.com
 *   php scripts/sendMarketingEmail.php --live --confirm-live-send
 */

define('BOOTSTRAP_LOADED', true);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../inc/errorHandler.php';
require __DIR__ . '/../classes/Database.php';
require __DIR__ . '/../classes/Email.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

function log_line(string $msg): void {
    echo $msg . PHP_EOL;
    flush();
}

function cli_option_value(array $args, string $name): ?string {
    foreach ($args as $index => $arg) {
        if (str_starts_with($arg, $name . '=')) {
            return substr($arg, strlen($name) + 1);
        }

        if ($arg === $name && isset($args[$index + 1])) {
            return $args[$index + 1];
        }
    }

    return null;
}

function mask_email_for_log(string $email): string {
    if (!str_contains($email, '@')) {
        return '[invalid email]';
    }

    [$local, $domain] = explode('@', $email, 2);
    $prefix = substr($local, 0, min(2, strlen($local)));

    return $prefix . '***@' . $domain;
}

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found');
}

$args = array_slice($argv ?? [], 1);

if (in_array('--help', $args, true)) {
    log_line('Usage:');
    log_line('  php scripts/sendMarketingEmail.php');
    log_line('  php scripts/sendMarketingEmail.php --email=test@example.com');
    log_line('  php scripts/sendMarketingEmail.php --live --confirm-live-send');
    exit;
}

$liveMode = in_array('--live', $args, true);

if ($liveMode && !in_array('--confirm-live-send', $args, true)) {
    log_line('[ABORTED] Live send requires --confirm-live-send.');
    exit(1);
}

// ============================================
// KONFIGURÁCIA — uprav pred odoslaním
// ============================================

$testMode  = !$liveMode;
$testEmail = cli_option_value($args, '--email') ?? ($_ENV['MARKETING_TEST_EMAIL'] ?? 'chatbotscoutsupport@gmail.com');

if ($testMode && !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
    log_line('[ERROR] Invalid test email.');
    exit(1);
}

$baseUrl = rtrim((string)($_ENV['APP_BASE_URL'] ?? ''), '/');
if ($baseUrl === '') {
    $baseUrl = 'https://chatbotscout.com';
}

$subject   = 'Nové chatboty na ChatbotScout! 🚀';
$preheader = 'Pozri čo je nové tento mesiac na ChatbotScout';
$headline  = 'Novinky na ChatbotScout';
$ctaText   = 'Pozrieť chatboty';
$ctaUrl    = $baseUrl . '/chatbots';

// HTML obsah emailu — uprav podľa potreby
$bodyHtml  = <<<HTML
<p>Pridali sme nové AI chatboty do nášho adresára!</p>

<p>Každý mesiac pridávame nové nástroje, ktoré ti pomôžu zlepšiť komunikáciu so zákazníkmi a automatizovať podporu na tvojom webe.</p>

<p>Tento mesiac sme pridali:</p>
<ul style="margin: 16px 0; padding-left: 20px;">
    <li style="margin-bottom: 8px; font-size: 15px;">🤖 Nové AI chatboty pre e-commerce</li>
    <li style="margin-bottom: 8px; font-size: 15px;">💬 Chatboty s podporou viacerých jazykov</li>
    <li style="margin-bottom: 8px; font-size: 15px;">⚡ Nástroje pre automatizáciu zákazníckej podpory</li>
</ul>

<p>Nájdi chatbot, ktorý je pre teba ten správny — porovnaj funkcie, ceny a hodnotenia od skutočných používateľov.</p>
HTML;

// ============================================
// MAIN
// ============================================

log_line('=== ChatbotScout Marketing Email Sender ===');
log_line('Mode: ' . ($testMode ? 'TEST (only ' . mask_email_for_log($testEmail) . ')' : 'LIVE (all subscribers)'));
log_line('');

try {
    $config = require __DIR__ . '/../config/database.php';
    $dsn    = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
    $db     = new PDO($dsn, $config['username'], $config['password'], $config['options']);

    // Načítaj príjemcov
    if ($testMode) {
        $stmt = $db->prepare("
            SELECT id, first_name, last_name, email, unsubscribe_token
            FROM users
            WHERE email = :email
              AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute([':email' => $testEmail]);
    } else {
        $stmt = $db->query("
            SELECT id, first_name, last_name, email, unsubscribe_token
            FROM users
            WHERE marketing_emails = 1
              AND status = 'active'
              AND is_email_verified = 1
        ");
    }

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($users)) {
        log_line('No recipients found. Exiting.');
        exit;
    }

    log_line('Recipients found: ' . count($users));
    log_line('');

    $emailService = new Email();
    $sent         = 0;
    $failed       = 0;

    foreach ($users as $user) {

        // Ak user nema unsubscribe_token, vygeneruj mu ho
        if (empty($user['unsubscribe_token'])) {
            $token = bin2hex(random_bytes(32));
            $db->prepare("UPDATE users SET unsubscribe_token = :token WHERE id = :id")
               ->execute([':token' => $token, ':id' => $user['id']]);
            $user['unsubscribe_token'] = $token;
            log_line('[TOKEN GENERATED] ' . mask_email_for_log($user['email']));
        }

        $name = trim($user['first_name'] . ' ' . $user['last_name']) ?: 'there';

        $result = $emailService->sendMarketingEmail(
            $user['email'],
            $name,
            $subject,
            $preheader,
            $headline,
            $bodyHtml,
            $ctaText,
            $ctaUrl,
            $user['unsubscribe_token']
        );

        if ($result) {
            log_line('[OK]     ' . mask_email_for_log($user['email']));
            $sent++;
        } else {
            log_line('[FAILED] ' . mask_email_for_log($user['email']));
            $failed++;
        }

        // Krátka pauza aby sme neprekrocili Gmail API rate limit
        if (!$testMode && count($users) > 1) {
            usleep(300000); // 0.3s medzi každým emailom
        }
    }

    log_line('');
    log_line('=== DONE ===');
    log_line('Sent:   ' . $sent);
    log_line('Failed: ' . $failed);

} catch (Exception $e) {
    log_line('[ERROR] ' . $e->getMessage());
    error_log('sendMarketingEmail script error: ' . $e->getMessage());
}
