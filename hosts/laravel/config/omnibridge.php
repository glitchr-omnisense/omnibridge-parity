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
    // analytics, as omnibase's Symfony host counts them. Its project is ../../app, the tree both hosts share: the
    // application's src/ (App\Entity, App\Repository, App\EntitySubscriber...) is read there, as Symfony's
    // services.yaml reads it.
    'omnibase' => ['project_dir' => base_path('../../app')],
    'doctrine' => [
        'connection' => 'mysql',
        'entities' => [base_path('../../app/src/Entity')],
    ],
];
