<?php

$regions = [
    'aws-0-ap-south-1.pooler.supabase.com',
    'aws-0-ap-northeast-1.pooler.supabase.com',
    'aws-0-ap-southeast-2.pooler.supabase.com',
    'aws-0-ca-central-1.pooler.supabase.com',
    'aws-0-eu-west-2.pooler.supabase.com',
    'aws-0-eu-west-3.pooler.supabase.com',
];

$user = 'postgres.aebcwkjzzcstyvdnmwoa';
$pass = 'Database@baqala19';
$db = 'postgres';

foreach ($regions as $host) {
    echo "Testing pooler host: {$host}:6543...\n";
    $dsn = "pgsql:host={$host};port=6543;dbname={$db};sslmode=require";
    try {
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        echo ">>> SUCCESS! Your project pooler host is: {$host} on port 6543 <<<\n";
        break;
    } catch (PDOException $e) {
        echo "  Error: " . $e->getMessage() . "\n";
    }
}
