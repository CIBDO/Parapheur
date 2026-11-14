<?php

return [

    /*
    |--------------------------------------------------------------------------
    | URLs signées (téléchargements / streaming)
    |--------------------------------------------------------------------------
    |
    | Les liens portent un jti consommé côté cache (anti-rejeu).
    | download = usage unique ; stream / onlyoffice = multi-usages limités.
    |
    */

    'signed_urls' => [
        'download_ttl_minutes' => (int) env('SIGNED_URL_DOWNLOAD_TTL', 15),
        'stream_ttl_minutes' => (int) env('SIGNED_URL_STREAM_TTL', 15),
        // Docs peut retenter / multi-GET (preview, conversion, collab) : TTL et plafond larges.
        'onlyoffice_ttl_minutes' => (int) env('SIGNED_URL_ONLYOFFICE_TTL', 120),
        'stream_max_uses' => (int) env('SIGNED_URL_STREAM_MAX_USES', 12),
        'onlyoffice_max_uses' => (int) env('SIGNED_URL_ONLYOFFICE_MAX_USES', 40),
    ],

];
