<?php

use Omnibridge\Laravel\Routing\ActionRoutes;

// The reference application's routes, read from its actions' #[Route] (the declaration Symfony's router reads on
// the same classes), each answered by omnibase's Negotiation through omnibridge/laravel's Screen\Responder.
ActionRoutes::register(app('router'), ActionRoutes::load([
    'App\\Action\\' => base_path('../../app/src/Action'),
], ActionRoutes::environment(app()->environment())));
