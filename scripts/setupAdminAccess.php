<?php
/**
 * Generate hidden admin access .env values for one or more operators.
 *
 * Usage:
 *   php scripts/setupAdminAccess.php --users=jakub,peter
 *   php scripts/setupAdminAccess.php --users=jakub,peter --write
 *   php scripts/setupAdminAccess.php --users=jakub,peter --path=ops-very-secret-slug
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from CLI.\n");
    exit(1);
}

$options = getopt('', ['users:', 'path:', 'write', 'help']);

if (isset($options['help'])) {
    printHelp();
    exit(0);
}

$projectRoot = dirname(__DIR__);
$envFile = $projectRoot . DIRECTORY_SEPARATOR . '.env';
$users = parseUsers($options['users'] ?? '');

while (count($users) < 2) {
    $number = count($users) + 1;
    $username = prompt("Admin username {$number}: ");
    if ($username !== '') {
        $users[] = $username;
    }
}

$users = array_slice(array_values(array_unique(array_map('normalizeUsername', $users))), 0, 2);

$accessPath = trim((string)($options['path'] ?? ''));
if ($accessPath === '') {
    $accessPath = 'ops-' . bin2hex(random_bytes(10));
}

if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{7,80}$/', $accessPath)) {
    fwrite(STDERR, "ADMIN_ACCESS_PATH must be 8-81 chars and may contain letters, numbers, _ and -.\n");
    exit(1);
}

$admins = [];
foreach ($users as $username) {
    $admins[] = [
        'username' => $username,
        'env_key'  => envKey($username),
        'password' => generatePassword(24),
        'secret'   => base32Encode(random_bytes(20)),
    ];
}

$envBlock = buildEnvBlock($accessPath, $admins);

echo "\n=== ChatbotScout Operations Access ===\n\n";
echo "Hidden login path:\n";
echo "  {$accessPath}\n\n";

echo "Add these accounts to Google Authenticator manually:\n\n";
foreach ($admins as $admin) {
    $label = 'ChatbotScout:' . $admin['username'];
    $issuer = 'ChatbotScout';
    $otpUri = 'otpauth://totp/' . rawurlencode($label)
        . '?secret=' . $admin['secret']
        . '&issuer=' . rawurlencode($issuer)
        . '&algorithm=SHA1&digits=6&period=30';

    echo "- {$admin['username']}\n";
    echo "  Password: {$admin['password']}\n";
    echo "  Setup key: {$admin['secret']}\n";
    echo "  OTP URI: {$otpUri}\n\n";
}

echo "Paste this into .env:\n\n";
echo $envBlock . "\n";

if (isset($options['write'])) {
    writeEnvBlock($envFile, $envBlock);
    echo "\n.env updated. A backup was created next to it.\n";
} else {
    echo "Run with --write if you want the script to update .env automatically.\n";
}

function printHelp(): void
{
    echo "Generate hidden admin access values.\n\n";
    echo "Options:\n";
    echo "  --users=one,two       Two admin usernames.\n";
    echo "  --path=hidden-slug    Optional hidden login path.\n";
    echo "  --write               Backup and update .env automatically.\n";
    echo "  --help                Show this help.\n";
}

function parseUsers(string $value): array
{
    if ($value === '') {
        return [];
    }

    return array_values(array_filter(array_map('normalizeUsername', explode(',', $value))));
}

function prompt(string $label): string
{
    echo $label;
    return trim((string)fgets(STDIN));
}

function normalizeUsername(string $username): string
{
    $username = strtolower(trim($username));
    return preg_replace('/[^a-z0-9._-]+/', '_', $username);
}

function envKey(string $username): string
{
    return trim(preg_replace('/[^A-Z0-9]+/', '_', strtoupper($username)), '_');
}

function generatePassword(int $length): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789_-!';
    $password = '';
    $max = strlen($alphabet) - 1;

    for ($i = 0; $i < $length; $i++) {
        $password .= $alphabet[random_int(0, $max)];
    }

    return $password;
}

function base32Encode(string $bytes): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    $output = '';

    foreach (str_split($bytes) as $byte) {
        $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
    }

    foreach (str_split($bits, 5) as $chunk) {
        if (strlen($chunk) < 5) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
        }
        $output .= $alphabet[bindec($chunk)];
    }

    return $output;
}

function buildEnvBlock(string $accessPath, array $admins): string
{
    $usernames = implode(',', array_column($admins, 'username'));
    $lines = [
        '# Hidden admin access',
        'ADMIN_ACCESS_PATH=' . $accessPath,
        'ADMIN_USERS=' . $usernames,
        'ADMIN_REQUIRE_TOTP=true',
        'ADMIN_SESSION_LIFETIME_SECONDS=28800',
        'ADMIN_SESSION_IDLE_SECONDS=3600',
    ];

    foreach ($admins as $admin) {
        $lines[] = '';
        $lines[] = 'ADMIN_' . $admin['env_key'] . '_PASSWORD=' . $admin['password'];
        $lines[] = 'ADMIN_' . $admin['env_key'] . '_TOTP_SECRET=' . $admin['secret'];
    }

    return implode(PHP_EOL, $lines);
}

function writeEnvBlock(string $envFile, string $envBlock): void
{
    if (!is_file($envFile)) {
        fwrite(STDERR, ".env file not found: {$envFile}\n");
        exit(1);
    }

    $current = file_get_contents($envFile);
    if ($current === false) {
        fwrite(STDERR, "Unable to read .env file.\n");
        exit(1);
    }

    $backupFile = $envFile . '.admin-backup-' . date('YmdHis');
    if (file_put_contents($backupFile, $current) === false) {
        fwrite(STDERR, "Unable to create .env backup.\n");
        exit(1);
    }

    $cleaned = preg_replace('/\R?# Hidden admin access\R(?:ADMIN_[A-Z0-9_]+=[^\r\n]*\R?|\R)*/', "\n", $current);
    $cleaned = preg_replace('/\R?ADMIN_ACCESS_PATH=[^\r\n]*/', '', $cleaned);
    $cleaned = preg_replace('/\R?ADMIN_USERS=[^\r\n]*/', '', $cleaned);
    $cleaned = preg_replace('/\R?ADMIN_AUTH_USER=[^\r\n]*/', '', $cleaned);
    $cleaned = preg_replace('/\R?ADMIN_PASSWORD=[^\r\n]*/', '', $cleaned);
    $cleaned = preg_replace('/\R?ADMIN_TOTP_SECRET=[^\r\n]*/', '', $cleaned);
    $cleaned = preg_replace('/\R?ADMIN_REQUIRE_TOTP=[^\r\n]*/', '', $cleaned);
    $cleaned = preg_replace('/\R?ADMIN_SESSION_LIFETIME_SECONDS=[^\r\n]*/', '', $cleaned);
    $cleaned = preg_replace('/\R?ADMIN_SESSION_IDLE_SECONDS=[^\r\n]*/', '', $cleaned);
    $cleaned = preg_replace('/\R?ADMIN_[A-Z0-9_]+_(PASSWORD|PASSWORD_HASH|TOTP_SECRET|DISPLAY_NAME|AUDIT_USER_ID|REQUIRE_TOTP)=[^\r\n]*/', '', $cleaned);
    $cleaned = rtrim((string)$cleaned);

    $next = $cleaned . PHP_EOL . PHP_EOL . $envBlock . PHP_EOL;

    if (file_put_contents($envFile, $next) === false) {
        fwrite(STDERR, "Unable to write .env file. Backup kept at {$backupFile}\n");
        exit(1);
    }
}
