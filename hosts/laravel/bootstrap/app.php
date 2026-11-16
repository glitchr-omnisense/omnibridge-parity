<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php')
    ->withMiddleware(static function (Middleware $middleware): void {
        // omnibase's language: the route's (_locale), else LANG, else the browser's, else the default.
        $middleware->web(append: [Base\Http\Localize::class]);
        $middleware->encryptCookies(except: ['LANG']);
    })
    // The application's listener of omnibase's neutral lifecycle events (App\EntitySubscriber\CommentLifecycle) is
    // found by the layout: an invokable of src/EntitySubscriber, one per request, no tag here (omnibridge/laravel's
    // OmnibaseServices, as omnibridge/symfony's LifecycleListenersPass on the other host).
    ->withExceptions(static function (Exceptions $exceptions): void {
    })->create();
