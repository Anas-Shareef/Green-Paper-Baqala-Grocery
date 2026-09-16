<?php

try {
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

    // Database connection handling
    $dbConnection = getenv('DB_CONNECTION') ?: ($_ENV['DB_CONNECTION'] ?? 'sqlite');

    if ($dbConnection === 'sqlite') {
        $sqliteSource = __DIR__ . '/../database/database.sqlite';
        $sqliteTmp = '/tmp/database.sqlite';

        if (!file_exists($sqliteTmp)) {
            if (file_exists($sqliteSource)) {
                @copy($sqliteSource, $sqliteTmp);
            } else {
                @touch($sqliteTmp);
            }
        }

        $_ENV['DB_DATABASE'] = $sqliteTmp;
    }

    // Override storage path for serverless
    $_ENV['APP_STORAGE'] = $tmpStorage;
    $_ENV['VIEW_COMPILED_PATH'] = $tmpStorage . '/framework/views';

    // Forward request to Laravel public index
    require __DIR__ . '/../public/index.php';
} catch (\Throwable $e) {
    http_response_code(500);
    echo "<h1>Laravel Server Error</h1>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . ":" . $e->getLine() . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
