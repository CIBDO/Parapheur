<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rappels d'échéance (jours relatifs à due_at)
    |--------------------------------------------------------------------------
    | Clé = kind stocké dans task_reminders ; valeur = décalage en jours.
    | Heure d'envoi configurable via reminder_hour.
    */
    'reminders' => [
        'offsets' => [
            'j_minus_3' => -3,
            'j_minus_1' => -1,
            'j_day' => 0,
            'j_plus_1' => 1,
        ],
        'hour' => 8,
        'minute' => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Escalades automatiques (retard en jours ouvrés / calendaires)
    |--------------------------------------------------------------------------
    | after_days_overdue : jours de retard avant escalade
    | notify_roles : rôles Spatie notifiés (si présents)
    | notify_hierarchy : remonter au supérieur de structure si disponible
    */
    'escalations' => [
        'enabled' => true,
        'rules' => [
            [
                'priority' => 'normale',
                'after_days_overdue' => 3,
                'level' => 1,
            ],
            [
                'priority' => 'importante',
                'after_days_overdue' => 2,
                'level' => 1,
            ],
            [
                'priority' => 'urgente',
                'after_days_overdue' => 1,
                'level' => 1,
            ],
            [
                'priority' => 'tres_urgente',
                'after_days_overdue' => 0,
                'level' => 1,
            ],
            // Second niveau (J+N supplémentaires)
            [
                'priority' => '*',
                'after_days_overdue' => 5,
                'level' => 2,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pont Ticketing → Task transversale
    |--------------------------------------------------------------------------
    */
    'ticket_bridge' => [
        'create_transversal_task' => true,
    ],

];
