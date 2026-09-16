<?php

// Prepare writable paths for Vercel serverless environment (/tmp)
$tmpStorage = '/tmp/storage';
$directories = [
    $tmpStorage . '/framework/views',
    $tmpStorage . '/framework/cache/data',
    $tmpStorage . '/framework/sessions',
    $tmpStorage . '/logs',
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
}

// Copy SQLite database to /tmp if not present
$sqliteSource = __DIR__ . '/../database/database.sqlite';
$sqliteTmp = '/tmp/database.sqlite';

if (!file_exists($sqliteTmp)) {
    if (file_exists($sqliteSource)) {
        @copy($sqliteSource, $sqliteTmp);
    } else {
        @touch($sqliteTmp);
    }
}

// Override storage & database paths for serverless
$_ENV['APP_STORAGE'] = $tmpStorage;
$_ENV['VIEW_COMPILED_PATH'] = $tmpStorage . '/framework/views';
$_ENV['DB_DATABASE'] = $sqliteTmp;

// Forward request to Laravel public index
require __DIR__ . '/../public/index.php';
