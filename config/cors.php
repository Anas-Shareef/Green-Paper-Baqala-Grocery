<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Authoritative CORS configuration for Baqqala Grocery API.
    |
    */

    'paths' => ['api/*', 'v1/*', 'sanctum/csrf-cookie', 'invoice/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => [
        'https://green-paper-baqala-grocery.vercel.app',
        'https://baqqala-admin.vercel.app',
        'http://localhost:5173',
        'http://localhost:5174',
        'http://localhost:8000',
        'http://127.0.0.1:5173',
        'http://127.0.0.1:5174',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'Origin', 'X-Requested-With', 'X-CSRF-TOKEN'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
