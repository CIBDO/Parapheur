<?php

$configured = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', env('APP_URL', 'http://localhost')))
)));

// Origines locales courantes (Laravel serve + Vite) hors production.
// Ne pas appeler app() ici : les fichiers de config sont chargés trop tôt.
$isProduction = env('APP_ENV', 'production') === 'production';

$localDefaults = $isProduction ? [] : [
    'http://localhost',
    'http://localhost:8000',
    'http://127.0.0.1',
    'http://127.0.0.1:8000',
];

$origins = array_values(array_unique(array_filter(array_merge(
    $configured !== [] ? $configured : [],
    $localDefaults,
))));

if ($origins === []) {
    $origins = ['http://localhost'];
}

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => $origins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 600,

    'supports_credentials' => true,

];
