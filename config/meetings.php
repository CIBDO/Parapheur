<?php

return [
    'max_upload_kb' => 20480,
    'allowed_mimes' => [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain',
        'text/csv',
        'image/jpeg',
        'image/png',
        'image/webp',
    ],
    'allowed_extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'jpg', 'jpeg', 'png', 'webp'],
    'reminders' => [
        'confirmation_days_before' => [3, 1],
        'meeting_days_before' => [3, 1],
        'meeting_hours_before' => [1],
        'decision_days_before' => [7, 3, 1],
    ],

    /*
    |--------------------------------------------------------------------------
    | Entête institutionnelle (modifiable dans le CR / DOCX / PDF)
    |--------------------------------------------------------------------------
    |
    | Ces valeurs sont injectées à la génération. Une fois dans le document,
    | le rédacteur peut les modifier librement (Tiptap ou OnlyOffice).
    |
    */
    'letterhead' => [
        'ministry_line_1' => env('MEETINGS_LETTERHEAD_MINISTRY_1', "MINISTÈRE DE L'ÉCONOMIE"),
        'ministry_line_2' => env('MEETINGS_LETTERHEAD_MINISTRY_2', 'ET DES FINANCES'),
        'direction_line_1' => env('MEETINGS_LETTERHEAD_DIRECTION_1', 'DIRECTION GÉNÉRALE DU TRÉSOR'),
        'direction_line_2' => env('MEETINGS_LETTERHEAD_DIRECTION_2', 'ET DE LA COMPTABILITÉ PUBLIQUE'),
        'republic' => env('MEETINGS_LETTERHEAD_REPUBLIC', 'RÉPUBLIQUE DU MALI'),
        'motto' => env('MEETINGS_LETTERHEAD_MOTTO', 'Un Peuple - Un But - Une Foi'),
    ],
];
