<?php
header('Content-Type: application/xml; charset=UTF-8');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../classes/ClientIpResolver.php';

Dotenv\Dotenv::createImmutable(__DIR__ . '/../')->safeLoad();

$forwardedProto = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ($forwardedProto === 'https' && ClientIpResolver::isTrustedProxyRequest());
$scheme = $isHttps ? 'https' : 'http';

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $scheme . '://' . $host;

$urls = [
    ['loc' => $baseUrl . '/', 'changefreq' => 'daily', 'priority' => '1.0'],
    ['loc' => $baseUrl . '/chatbots', 'changefreq' => 'daily', 'priority' => '0.9'],
    ['loc' => $baseUrl . '/about-us', 'changefreq' => 'monthly', 'priority' => '0.6'],
    ['loc' => $baseUrl . '/contact', 'changefreq' => 'monthly', 'priority' => '0.6'],
    ['loc' => $baseUrl . '/advertise', 'changefreq' => 'monthly', 'priority' => '0.5'],
    ['loc' => $baseUrl . '/submit-chatbot', 'changefreq' => 'weekly', 'priority' => '0.7'],
    ['loc' => $baseUrl . '/privacy-policy', 'changefreq' => 'monthly', 'priority' => '0.4'],
    ['loc' => $baseUrl . '/terms-and-conditions', 'changefreq' => 'monthly', 'priority' => '0.4'],
    ['loc' => $baseUrl . '/affiliate-disclosure', 'changefreq' => 'monthly', 'priority' => '0.4'],
];

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $url): ?>
  <url>
    <loc><?php echo htmlspecialchars($url['loc'], ENT_XML1, 'UTF-8'); ?></loc>
    <changefreq><?php echo htmlspecialchars($url['changefreq'], ENT_XML1, 'UTF-8'); ?></changefreq>
    <priority><?php echo htmlspecialchars($url['priority'], ENT_XML1, 'UTF-8'); ?></priority>
  </url>
<?php endforeach; ?>
</urlset>
