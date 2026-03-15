<?php
$clientId     = '726695024122-ge43n76e5fgkt0omrmlfbl9crr641fd5.apps.googleusercontent.com';
$clientSecret = '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';
$redirectUri  = 'http://localhost/mainwebsite/public/getToken.php';

$code = $_GET['code'] ?? null;

if (!$code) {
    echo 'Žiadny code.';
    exit;
}

$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'code'          => $code,
        'client_id'     => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri'  => $redirectUri,
        'grant_type'    => 'authorization_code',
    ]),
    CURLOPT_RETURNTRANSFER => true,
]);

$response = json_decode(curl_exec($ch), true);
curl_close($ch);

echo '<pre>';
print_r($response);
echo '</pre>';