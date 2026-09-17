<?php

$loginUrl = 'https://baqqala-admin.vercel.app/api/v1/auth/login';

$ch = curl_init($loginUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'email' => 'admin@baqqala.com',
    'password' => 'password',
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
]);

$response = curl_exec($ch);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);
$data = json_decode($body, true);

echo "1. LIVE LOGIN RESPONSE STATUS: " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . "\n";
echo "2. SET-COOKIE HEADERS IN RESPONSE: " . (preg_match('/set-cookie/i', $headers) ? 'YES (BAD)' : 'NONE (CLEAN & STATELESS)') . "\n";

$token = $data['data']['token'] ?? null;
echo "3. RETURNED BEARER TOKEN: " . ($token ? $token : 'FAIL') . "\n";

if ($token) {
    echo "\n=== TESTING AUTHENTICATED ADMIN DASHBOARD API WITH BEARER TOKEN ===\n";
    $ch2 = curl_init('https://baqqala-admin.vercel.app/api/v1/admin/dashboard');
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_HEADER, true);
    curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch2, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$token}",
        'Accept: application/json',
    ]);
    $res2 = curl_exec($ch2);
    $hSize2 = curl_getinfo($ch2, CURLINFO_HEADER_SIZE);
    $headers2 = substr($res2, 0, $hSize2);
    $body2 = substr($res2, $hSize2);

    echo "4. AUTHENTICATED API STATUS: " . curl_getinfo($ch2, CURLINFO_HTTP_CODE) . "\n";
    echo "5. AUTHENTICATED API RESPONSE: " . substr($body2, 0, 200) . "...\n";
}
