<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

// Use writable /tmp/storage when running in Vercel serverless environment
if (getenv('APP_STORAGE') || isset($_ENV['APP_STORAGE'])) {
    $app->useStoragePath(getenv('APP_STORAGE') ?: $_ENV['APP_STORAGE']);
}

// Failsafe to prevent empty driver strings from breaking Laravel Manager
$app->booting(function () {
    if (empty(config('session.driver'))) config(['session.driver' => 'cookie']);
    if (empty(config('cache.default'))) config(['cache.default' => 'array']);
    if (empty(config('queue.default'))) config(['queue.default' => 'sync']);
    if (empty(config('filesystems.default'))) config(['filesystems.default' => 'local']);
    if (empty(config('mail.default'))) config(['mail.default' => 'log']);
    if (empty(config('database.default'))) config(['database.default' => 'sqlite']);
});

return $app;
