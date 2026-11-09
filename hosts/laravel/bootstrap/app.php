<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php')
    ->withMiddleware(static function (Middleware $middleware): void {
        // omnibase's language: the route's (_locale), else LANG, else the browser's, else the default.
        $middleware->web(append: [Omnibridge\Laravel\Http\Localize::class]);
        $middleware->encryptCookies(except: ['LANG']);
    })
    ->withExceptions(static function (Exceptions $exceptions): void {
    })->create();
