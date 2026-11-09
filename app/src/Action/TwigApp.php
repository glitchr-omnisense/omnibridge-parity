<?php

namespace App\Action;

use Base\Screen\Template;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Whether a template sees Twig's `app` variable (app.request, app.user...): TwigBundle gives it under Symfony;
 * omnibridge/laravel's Negotiation renders without it, a site adding its own (tests/Parity/differences.php,
 * "twig-app-variable"). The reference application's other templates do not read it.
 */
#[Route('/twig-app', name: 'twig_app')]
final class TwigApp
{
    public function __invoke(): Template
    {
        return new Template('twig-app.html.twig', alone: true);
    }
}
