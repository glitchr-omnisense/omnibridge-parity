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
    // omnibase on (its services, its entities' mapping beside the application's): the pages sent are counted in its
    // analytics, as omnibase's Symfony host counts them (omnibridge/laravel's Omnibase\PageViews). Its project folder
    // is the reference application's (app/): omnibridge/laravel 1.1.0 describes the entities of project_dir/src/Entity
    // for omnibridge/eloquent and does not read doctrine.entities below (docs/differences.md, "What each host needs").
    'omnibase' => ['project_dir' => base_path('../../app')],
    'doctrine' => [
        'connection' => 'mysql',
        'entities' => [base_path('../../app/src/Entity')],
    ],
];
