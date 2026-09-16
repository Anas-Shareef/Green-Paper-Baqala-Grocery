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

    // Set writable storage path and log channel for serverless environment
    putenv('APP_STORAGE=' . $tmpStorage);
    $_ENV['APP_STORAGE'] = $tmpStorage;
    $_SERVER['APP_STORAGE'] = $tmpStorage;

    putenv('LOG_CHANNEL=stderr');
    $_ENV['LOG_CHANNEL'] = 'stderr';
    $_SERVER['LOG_CHANNEL'] = 'stderr';

    putenv('VIEW_COMPILED_PATH=' . $tmpStorage . '/framework/views');
    $_ENV['VIEW_COMPILED_PATH'] = $tmpStorage . '/framework/views';
    $_SERVER['VIEW_COMPILED_PATH'] = $tmpStorage . '/framework/views';

    // 2. Database connection & Auto Fallback handling
    $dbConnection = getenv('DB_CONNECTION') ?: ($_ENV['DB_CONNECTION'] ?? 'sqlite');

    // If pgsql driver is requested but not installed in serverless PHP, fallback to SQLite
    if ($dbConnection === 'pgsql' && !extension_loaded('pdo_pgsql')) {
        $dbConnection = 'sqlite';
        putenv('DB_CONNECTION=sqlite');
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_SERVER['DB_CONNECTION'] = 'sqlite';
    }

    if ($dbConnection === 'sqlite') {
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
    }

    // Forward request to Laravel public index
    require __DIR__ . '/../public/index.php';
} catch (\Throwable $e) {
    http_response_code(500);
    echo "<h1>Baqqala Serverless Diagnostics</h1>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . ":" . $e->getLine() . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
