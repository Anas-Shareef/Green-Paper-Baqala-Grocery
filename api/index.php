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

    // Redirect framework cache paths to /tmp to bypass read-only bootstrap/cache files
    $cachePaths = [
        'APP_SERVICES_CACHE' => '/tmp/services.php',
        'APP_PACKAGES_CACHE' => '/tmp/packages.php',
        'APP_CONFIG_CACHE' => '/tmp/config.php',
        'APP_ROUTES_CACHE' => '/tmp/routes.php',
        'APP_EVENTS_CACHE' => '/tmp/events.php',
    ];
    foreach ($cachePaths as $key => $path) {
        putenv("{$key}={$path}");
        $_ENV[$key] = $path;
        $_SERVER[$key] = $path;
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    // Mandatory Environment Variables for Vercel serverless execution
    $forcedEnv = [
        'APP_STORAGE' => $tmpStorage,
        'LOG_CHANNEL' => 'stderr',
        'VIEW_COMPILED_PATH' => $tmpStorage . '/framework/views',
        'APP_DEBUG' => 'true',
        'SESSION_DRIVER' => 'file',
        'CACHE_STORE' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'FILESYSTEM_DISK' => 'local',
        'MAIL_MAILER' => 'log',
        'BROADCAST_CONNECTION' => 'null',
        'DB_CONNECTION' => 'sqlite',
        'APP_MAINTENANCE_DRIVER' => 'file',
        'APP_MAINTENANCE_STORE' => 'array',
        'HTTPS' => 'on',
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'APP_KEY' => getenv('APP_KEY') ?: 'base64:yH6b2N0U9aL/JgK/sX1u2v3w4x5y6z7A8B9C0D1E2F3=',
    ];

    foreach ($forcedEnv as $key => $val) {
        putenv("{$key}={$val}");
        $_ENV[$key] = $val;
        $_SERVER[$key] = $val;
    }

    // 2. Database Copy / Restore to /tmp
    $sqliteTmp = '/tmp/database.sqlite';
    $sqliteSource = __DIR__ . '/../database/database.sqlite';
    $sqliteDump = __DIR__ . '/../database/sqlite_dump.php';

    if (!file_exists($sqliteTmp) || filesize($sqliteTmp) < 10000) {
        if (file_exists($sqliteDump)) {
            $raw = base64_decode(require $sqliteDump);
            @file_put_contents($sqliteTmp, $raw);
        } elseif (file_exists($sqliteSource)) {
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

    $trace = $e->getTrace();
    foreach ($trace as $frame) {
        if (isset($frame['function']) && $frame['function'] === 'createDriver') {
            echo "<div style='background:#fee2e2; border:2px solid #ef4444; padding:15px; border-radius:8px; margin:20px 0;'>";
            echo "<h2 style='color:#991b1b; margin-top:0;'>FAILED MANAGER: " . htmlspecialchars($frame['class'] ?? 'Unknown') . "</h2>";
            echo "<p><strong>Called Method:</strong> " . htmlspecialchars($frame['type'] ?? '') . htmlspecialchars($frame['function'] ?? '') . "</p>";
            echo "<p><strong>Passed Arguments:</strong> <code>" . htmlspecialchars(json_encode($frame['args'] ?? [])) . "</code></p>";
            echo "</div>";
        }
    }

    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
