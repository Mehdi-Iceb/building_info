<?php

return [
    /*
    |--------------------------------------------------------------------------
    | HTTP Client Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure the default options for the HTTP client.
    | You can specify the path to your enterprise certificates or
    | disable SSL verification for development.
    |
    */

    'defaults' => [
        // Pour la production avec certificat d'entreprise :
        // 'verify' => base_path('storage/certs/entreprise.crt'),
        
        // Pour le développement (désactive la vérification SSL) :
        'verify' => false,
    ],

    'timeout' => 30,
    'retry' => [
        'times' => 3,
        'sleep' => 1000,
    ],
];