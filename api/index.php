<?php

try {
    // 1. Prepare writable paths for Vercel serverless environment (/tmp)
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

    // Ensure config caching is disabled so Laravel always loads config files dynamically
    $cacheVars = ['APP_CONFIG_CACHE', 'APP_EVENTS_CACHE', 'APP_PACKAGES_CACHE', 'APP_ROUTES_CACHE', 'APP_SERVICES_CACHE'];
    foreach ($cacheVars as $var) {
        putenv($var);
        unset($_ENV[$var], $_SERVER[$var]);
    }

    // Mandatory Environment Variables for Vercel serverless execution
    $forcedEnv = [
        'APP_STORAGE' => $tmpStorage,
        'LOG_CHANNEL' => 'stderr',
        'VIEW_COMPILED_PATH' => $tmpStorage . '/framework/views',
        'APP_DEBUG' => 'true',
        'SESSION_DRIVER' => 'cookie',
        'CACHE_STORE' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'FILESYSTEM_DISK' => 'local',
        'MAIL_MAILER' => 'log',
        'BROADCAST_CONNECTION' => 'null',
        'DB_CONNECTION' => 'sqlite',
        'APP_KEY' => getenv('APP_KEY') ?: 'base64:yH6b2N0U9aL/JgK/sX1u2v3w4x5y6z7A8B9C0D1E2F3=',
    ];

    foreach ($forcedEnv as $key => $val) {
        putenv("{$key}={$val}");
        $_ENV[$key] = $val;
        $_SERVER[$key] = $val;
    }

    // 2. Database Copy to /tmp
    $sqliteSource = __DIR__ . '/../database/database.sqlite';
    $sqliteTmp = '/tmp/database.sqlite';

    if (!file_exists($sqliteTmp) || filesize($sqliteTmp) < 100) {
        if (file_exists($sqliteSource)) {
            @copy($sqliteSource, $sqliteTmp);
        } else {
            @touch($sqliteTmp);
        }
    }

    putenv('DB_DATABASE=' . $sqliteTmp);
    $_ENV['DB_DATABASE'] = $sqliteTmp;
    $_SERVER['DB_DATABASE'] = $sqliteTmp;

    // Forward request to Laravel public index
    require __DIR__ . '/../public/index.php';
} catch (\Throwable $e) {
    http_response_code(500);
    echo "<h1>Baqqala Serverless Diagnostics</h1>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . ":" . $e->getLine() . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
