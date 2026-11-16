<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Driver antivirus
    |--------------------------------------------------------------------------
    | heuristic : détection locale EICAR / nom .infected (défaut local)
    | null|none : marque toujours « safe » (démo uniquement)
    | clamav    : socket/TCP ClamAV (clamd)
    | http      : scanner HTTP externe (POST multipart file)
    */
    'driver' => env('ANTIVIRUS_DRIVER', 'heuristic'),

    'clamav' => [
        'socket' => env('CLAMAV_SOCKET', '/var/run/clamav/clamd.ctl'),
        'host' => env('CLAMAV_HOST', '127.0.0.1'),
        'port' => (int) env('CLAMAV_PORT', 3310),
        'timeout' => (int) env('CLAMAV_TIMEOUT', 30),
    ],

    'http' => [
        'url' => env('ANTIVIRUS_HTTP_URL', ''),
        'token' => env('ANTIVIRUS_HTTP_TOKEN', ''),
        'timeout' => (int) env('ANTIVIRUS_HTTP_TIMEOUT', 60),
        // Réponse JSON attendue : { "status": "safe"|"infected"|"failed" }
        // ou corps texte contenant "OK" / "FOUND"
    ],
];
