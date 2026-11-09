<?php

// omnibridge/laravel's options the reference application sets; the others keep the package's defaults
// (vendor/omnibridge/laravel/config/omnibridge.php).
return [
    'locales' => [
        'available' => ['en-GB', 'fr-FR'],
        'default' => 'en-GB',
    ],
    'translations' => [
        'paths' => [base_path('../../app/translations')],
    ],
    'twig' => [
        'paths' => [[base_path('../../app/templates'), null]],
        'cache' => false,
        'timezone' => 'UTC',
    ],
    'doctrine' => [
        'connection' => 'mysql',
        'entities' => [base_path('../../app/src/Entity')],
    ],
];
