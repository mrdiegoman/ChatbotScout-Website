<?php
header('Content-Type: application/xml; charset=UTF-8');

$scheme = 'http';
if (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
) {
    $scheme = 'https';
}

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $scheme . '://' . $host;

$urls = [
    ['loc' => $baseUrl . '/', 'changefreq' => 'daily', 'priority' => '1.0'],
    ['loc' => $baseUrl . '/chatbotShowcase.php', 'changefreq' => 'daily', 'priority' => '0.9'],
    ['loc' => $baseUrl . '/aboutUs.php', 'changefreq' => 'monthly', 'priority' => '0.6'],
    ['loc' => $baseUrl . '/contact.php', 'changefreq' => 'monthly', 'priority' => '0.6'],
    ['loc' => $baseUrl . '/advertise.php', 'changefreq' => 'monthly', 'priority' => '0.5'],
    ['loc' => $baseUrl . '/submitChatbot.php', 'changefreq' => 'weekly', 'priority' => '0.7'],
    ['loc' => $baseUrl . '/privacyPolicy.php', 'changefreq' => 'monthly', 'priority' => '0.4'],
    ['loc' => $baseUrl . '/termsAndConditions.php', 'changefreq' => 'monthly', 'priority' => '0.4'],
    ['loc' => $baseUrl . '/affiliateDisclosure.php', 'changefreq' => 'monthly', 'priority' => '0.4'],
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