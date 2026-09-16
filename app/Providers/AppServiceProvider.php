<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (
            env('APP_ENV') === 'production' ||
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
            getenv('HTTPS') === 'on' ||
            (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
        ) {
            URL::forceScheme('https');
        }
    }
}
