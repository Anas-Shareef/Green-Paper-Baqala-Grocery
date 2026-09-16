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

    // Clean up any stale bootstrap cache files to ensure fresh provider registration
    $bootstrapCache = __DIR__ . '/../bootstrap/cache';
    if (is_dir($bootstrapCache)) {
        foreach (glob($bootstrapCache . '/*.php') as $file) {
            @unlink($file);
        }
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
    $orig = $e->getPrevious() ?: $e;
    echo "<p><strong>Original Error:</strong> " . htmlspecialchars($orig->getMessage()) . "</p>";
    echo "<p><strong>File:</strong> " . htmlspecialchars($orig->getFile()) . ":" . $orig->getLine() . "</p>";
    echo "<pre>" . htmlspecialchars($orig->getTraceAsString()) . "</pre>";
    if ($e->getPrevious()) {
        echo "<h2>Outer Wrapper Exception</h2>";
        echo "<p><strong>Wrapper Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    }
}
