<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ONLYOFFICE Document Server (Temps 2)
    |--------------------------------------------------------------------------
    |
    | Si désactivé, les fichiers Office restent en mode téléchargement (Temps 1).
    |
    */

    'enabled' => (bool) env('ONLYOFFICE_ENABLED', false),

    /*
    | URL vue par le navigateur (chargement de api.js + iframe éditeur).
    | Local Docker : http://localhost:8080
    | VPS : https://docs.exemple.gouv
    */
    'url' => rtrim((string) env('ONLYOFFICE_URL', 'http://localhost:8080'), '/'),

    /*
    | URL Document Server vue depuis Laravel (téléchargements callback).
    | Laisser vide = ONLYOFFICE_URL. Utile si le DS renvoie un hôte Docker
    | injoignable depuis l’hôte (ex. http://onlyoffice) alors que Laravel
    | doit passer par http://localhost:8080.
    */
    'internal_url' => rtrim((string) env('ONLYOFFICE_INTERNAL_URL', ''), '/'),

    /*
    | URL de l’application Laravel vue par le Document Server
    | (téléchargement fichier + callback). Sur Docker Desktop :
    | http://host.docker.internal:8000
    | En production : URL publique HTTPS de l’app.
    */
    'app_url' => rtrim((string) env('ONLYOFFICE_APP_URL', env('APP_URL', 'http://localhost')), '/'),

    'jwt_secret' => (string) env('ONLYOFFICE_JWT_SECRET', ''),

    'jwt_header' => (string) env('ONLYOFFICE_JWT_HEADER', 'Authorization'),

    'jwt_ttl' => (int) env('ONLYOFFICE_JWT_TTL', 3600),

    /*
    | Durée de vie des URLs signées exposées au Document Server (minutes).
    */
    'file_url_ttl_minutes' => (int) env('ONLYOFFICE_FILE_URL_TTL', 120),

];
