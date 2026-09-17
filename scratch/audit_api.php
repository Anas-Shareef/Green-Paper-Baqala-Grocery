<?php

$baseUrl = 'https://baqqala-admin.vercel.app/api/v1';

function callApi($method, $url, $data = null, $token = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
    ];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'body' => $response,
        'error' => $error,
    ];
}

echo "=== BAQQALA API AUDIT ===\n\n";

// 1. Database Health
$res = callApi('GET', "$baseUrl/health/database");
echo "1. GET /health/database: HTTP {$res['code']}\n";
echo "   Body: " . substr($res['body'], 0, 150) . "\n\n";

// 2. Home Feed
$res = callApi('GET', "$baseUrl/home");
echo "2. GET /home: HTTP {$res['code']}\n";
echo "   Body: " . substr($res['body'], 0, 150) . "\n\n";

// 3. Products
$res = callApi('GET', "$baseUrl/products");
echo "3. GET /products: HTTP {$res['code']}\n";
echo "   Body: " . substr($res['body'], 0, 150) . "\n\n";

// 4. Categories
$res = callApi('GET', "$baseUrl/categories");
echo "4. GET /categories: HTTP {$res['code']}\n";
echo "   Body: " . substr($res['body'], 0, 150) . "\n\n";

// 5. Customer Recognition
$res = callApi('POST', "$baseUrl/customer/recognize", ['phone' => '+971501234567']);
echo "5. POST /customer/recognize: HTTP {$res['code']}\n";
echo "   Body: " . substr($res['body'], 0, 150) . "\n\n";

// 6. Admin Login
$loginRes = callApi('POST', "$baseUrl/auth/login", [
    'email' => 'admin@baqqala.com',
    'password' => 'password',
]);
echo "6. POST /auth/login: HTTP {$loginRes['code']}\n";
echo "   Body: " . substr($loginRes['body'], 0, 150) . "\n\n";

$json = json_decode($loginRes['body'], true);
$adminToken = $json['data']['token'] ?? null;

if ($adminToken) {
    // 7. Admin Dashboard
    $res = callApi('GET', "$baseUrl/admin/dashboard", null, $adminToken);
    echo "7. GET /admin/dashboard: HTTP {$res['code']}\n";
    echo "   Body: " . substr($res['body'], 0, 150) . "\n\n";

    // 8. Admin Products
    $res = callApi('GET', "$baseUrl/admin/products", null, $adminToken);
    echo "8. GET /admin/products: HTTP {$res['code']}\n";
    echo "   Body: " . substr($res['body'], 0, 150) . "\n\n";

    // 9. Admin Orders
    $res = callApi('GET', "$baseUrl/admin/orders", null, $adminToken);
    echo "9. GET /admin/orders: HTTP {$res['code']}\n";
    echo "   Body: " . substr($res['body'], 0, 150) . "\n\n";
}

// 11. Customer Submit Order
$orderPayload = [
    'customer_phone' => '+971501234567',
    'customer_name' => 'Abdullah Audit Test',
    'villa_number' => '99',
    'delivery_address' => 'Street 22',
    'zone' => 'Zone Test',
    'payment_method' => 'cod',
    'idempotency_key' => 'audit-' . time(),
    'items' => [
        ['product_id' => 27, 'quantity' => 1]
    ]
];
$res = callApi('POST', "$baseUrl/orders", $orderPayload);
echo "11. POST /orders: HTTP {$res['code']}\n";
echo "   Error: {$res['error']}\n";
echo "   Body: " . substr($res['body'], 0, 200) . "\n\n";
