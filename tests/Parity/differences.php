<?php

/*
 * What the two hosts do differently, each entry checked by ParityTest::testEveryListedDifferenceStillHoldsAndIsDocumented
 * and explained in docs/differences.md under its id. `check` is given `$ask(string $side, string $method, string $path,
 * array $headers = [], ?string $body = null)`, which asks a host afresh, and says whether the difference still holds:
 * once it no longer does, the entry leaves this file and the docs.
 */

return [
    'error-pages' => [
        'what' => "A page that does not exist: 404 on both, the error page itself each framework's.",
        'check' => static function (callable $ask): bool {
            [$s, $l] = [$ask('symfony', 'GET', '/notes/no-such-note'), $ask('laravel', 'GET', '/notes/no-such-note')];

            return 404 === $s['status'] && 404 === $l['status'] && $s['body'] !== $l['body'];
        },
    ],
    'not-found-vary' => [
        'what' => "A 404: Symfony's error renderer sends Vary: Accept (it picks the error page's format from Accept), Laravel's sends no Vary.",
        'check' => static function (callable $ask): bool {
            [$s, $l] = [$ask('symfony', 'GET', '/notes/no-such-note'), $ask('laravel', 'GET', '/notes/no-such-note')];

            return 'Accept' === ($s['headers']['vary'] ?? null) && !isset($l['headers']['vary']);
        },
    ],
    'cache-control' => [
        'what' => 'A page no action caches: Symfony says "max-age=0, must-revalidate, private" with an Expires, Laravel "no-cache, private".',
        'check' => static function (callable $ask): bool {
            [$s, $l] = [$ask('symfony', 'GET', '/'), $ask('laravel', 'GET', '/')];

            return 'max-age=0, must-revalidate, private' === ($s['headers']['cache-control'] ?? null) && isset($s['headers']['expires'])
                && 'no-cache, private' === ($l['headers']['cache-control'] ?? null) && !isset($l['headers']['expires']);
        },
    ],
    'session-cookies' => [
        'what' => "The session's cookies: Symfony's PHPSESSID, Laravel's laravel_session and XSRF-TOKEN; LANG is the same on both.",
        'check' => static function (callable $ask): bool {
            [$s, $l] = [$ask('symfony', 'GET', '/'), $ask('laravel', 'GET', '/')];

            return isset($s['cookies']['PHPSESSID'], $l['cookies']['laravel_session'], $l['cookies']['XSRF-TOKEN'])
                && !isset($s['cookies']['laravel_session']) && !isset($l['cookies']['PHPSESSID'])
                && ($s['cookies']['LANG'] ?? null) === ($l['cookies']['LANG'] ?? null);
        },
    ],
    'twig-app-variable' => [
        'what' => "Twig's `app` variable (app.request, app.user, app.session): there under Symfony (TwigBundle), not in omnibridge/laravel's default rendering.",
        'check' => static function (callable $ask): bool {
            return "app is defined\n" === $ask('symfony', 'GET', '/twig-app')['body'] && "app is not defined\n" === $ask('laravel', 'GET', '/twig-app')['body'];
        },
    ],
];
