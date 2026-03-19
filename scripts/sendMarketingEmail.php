<?php
/**
 * Marketing Email Sender Script
 *
 * TEST MODE:  $testMode = true  → pošle email len na $testEmail
 * LIVE MODE:  $testMode = false → pošle všetkým userom s marketing_emails = 1
 *
 * Spustenie: https://yourdomain.com/scripts/sendMarketingEmail.php?secret=YOUR_SECRET
 * alebo cez CLI: php scripts/sendMarketingEmail.php
 * 
 * http://localhost/MainWebsite/scripts/sendMarketingEmail.php?secret=d0490709d5bc32d3a72ce77c6834eb76
 */

define('BOOTSTRAP_LOADED', true);
require __DIR__ . '/../inc/errorHandler.php';
require __DIR__ . '/../classes/Database.php';
require __DIR__ . '/../classes/Email.php';

session_start();

// ============================================
// OCHRANA — secret key (zmeň si na vlastný!)
// ============================================

$secret = 'd0490709d5bc32d3a72ce77c6834eb76';

$isCli  = PHP_SAPI === 'cli';
$isWeb  = !$isCli;

if ($isWeb) {
    if (($_GET['secret'] ?? '') !== $secret) {
        http_response_code(403);
        die('403 Forbidden');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

// ============================================
// KONFIGURÁCIA — uprav pred odoslaním
// ============================================

$testMode  = false;                          // true = len testEmail, false = všetci
$testEmail = 'rafaj.jakub@gmail.com';       // tvoj email na testovanie

$subject   = 'Nové chatboty na ChatbotScout! 🚀';
$preheader = 'Pozri čo je nové tento mesiac na ChatbotScout';
$headline  = 'Novinky na ChatbotScout';
$ctaText   = 'Pozrieť chatboty';
$ctaUrl    = 'http://localhost/mainwebsite/public/chatbotShowcase.php';

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

function log_line(string $msg): void {
    echo $msg . "\n";
    flush();
}

log_line('=== ChatbotScout Marketing Email Sender ===');
log_line('Mode: ' . ($testMode ? 'TEST (only ' . $testEmail . ')' : 'LIVE (all subscribers)'));
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
    $skipped      = 0;

    foreach ($users as $user) {

        // Ak user nema unsubscribe_token, vygeneruj mu ho
        if (empty($user['unsubscribe_token'])) {
            $token = bin2hex(random_bytes(32));
            $db->prepare("UPDATE users SET unsubscribe_token = :token WHERE id = :id")
               ->execute([':token' => $token, ':id' => $user['id']]);
            $user['unsubscribe_token'] = $token;
            log_line('[TOKEN GENERATED] ' . $user['email']);
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
            log_line('[OK]     ' . $user['email']);
            $sent++;
        } else {
            log_line('[FAILED] ' . $user['email']);
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