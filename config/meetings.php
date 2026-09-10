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
];
