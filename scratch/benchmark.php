<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

\Illuminate\Support\Facades\DB::enableQueryLog();

function benchmark($name, callable $fn) {
    \Illuminate\Support\Facades\DB::flushQueryLog();
    $start = microtime(true);
    $result = $fn();
    $durationMs = round((microtime(true) - $start) * 1000, 2);
    $queryLog = \Illuminate\Support\Facades\DB::getQueryLog();
    $queryCount = count($queryLog);
    $totalDbTimeMs = round(array_sum(array_column($queryLog, 'time')), 2);

    echo sprintf("%-30s | Latency: %6.1f ms | Queries: %2d | DB Time: %6.1f ms\n", $name, $durationMs, $queryCount, $totalDbTimeMs);
    return $result;
}

echo "=== INITIAL (COLD) BENCHMARKS ===\n";

function runSuite() {
    // 1. GET /api/v1/home
    benchmark('API: GET /api/v1/home', function() {
        $c = app(\App\Http\Controllers\Api\v1\ProductController::class);
        return $c->home();
    });

    // 2. GET /api/v1/products
    benchmark('API: GET /api/v1/products', function() {
        $c = app(\App\Http\Controllers\Api\v1\ProductController::class);
        $req = \Illuminate\Http\Request::create('/api/v1/products', 'GET');
        return $c->index($req);
    });

    // 3. GET /api/v1/admin/dashboard
    benchmark('API: GET /admin/dashboard', function() {
        $c = app(\App\Http\Controllers\Api\v1\Admin\AdminDashboardController::class);
        return $c->index();
    });

    // 4. Admin Orders Livewire Render
    benchmark('Livewire: /admin/orders', function() {
        $lw = new \App\Livewire\Admin\Orders();
        $lw->mount();
        return $lw->render();
    });

    // 5. Admin Dashboard Livewire Render
    benchmark('Livewire: /admin/dashboard', function() {
        $lw = new \App\Livewire\Admin\Dashboard();
        return $lw->render();
    });
}

runSuite();

echo "\n=== REPEAT (WARM / CACHED) BENCHMARKS ===\n";
runSuite();

