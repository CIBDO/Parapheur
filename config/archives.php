<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Auto-SIP à la prise en charge
    |--------------------------------------------------------------------------
    */
    'auto_sip_on_accept' => (bool) env('ARCHIVES_AUTO_SIP_ON_ACCEPT', false),

    /*
    |--------------------------------------------------------------------------
    | Immutabilité applicative / Object Lock
    |--------------------------------------------------------------------------
    | ARCHIVES_OBJECT_LOCK=true active le refus d'écrasement des chemins AIP.
    | Le verrouillage objet S3 réel dépend de la config bucket côté hébergeur.
    */
    'object_lock' => (bool) env('ARCHIVES_OBJECT_LOCK', false),

    /*
    |--------------------------------------------------------------------------
    | Migration de formats
    |--------------------------------------------------------------------------
    */
    'format_migration' => [
        'enabled' => (bool) env('ARCHIVES_FORMAT_MIGRATION_ENABLED', true),
        'ghostscript_binary' => env('ARCHIVES_GS_BINARY', 'gs'),
        'libreoffice_binary' => env('ARCHIVES_SOFFICE_BINARY', 'soffice'),
        'target_profile' => env('ARCHIVES_FORMAT_PROFILE', 'pdfa'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Seuils climatiques (saisie manuelle)
    |--------------------------------------------------------------------------
    */
    'climate' => [
        'temperature_min' => (float) env('ARCHIVES_TEMP_MIN', 14),
        'temperature_max' => (float) env('ARCHIVES_TEMP_MAX', 22),
        'humidity_min' => (float) env('ARCHIVES_HUMIDITY_MIN', 40),
        'humidity_max' => (float) env('ARCHIVES_HUMIDITY_MAX', 60),
    ],

];
