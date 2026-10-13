<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Driver antivirus
    |--------------------------------------------------------------------------
    | heuristic : détection locale EICAR / nom .infected (défaut)
    | null|none : marque toujours « safe » (environnements de démo)
    | clamav / http : à brancher ultérieurement sur un moteur externe
    */
    'driver' => env('ANTIVIRUS_DRIVER', 'heuristic'),
];