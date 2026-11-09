<?php

namespace App\Action;

use Base\Screen\Json;
use Symfony\Component\Routing\Attribute\Route;

/**
 * omnibase's Base/App precedence, as each host's runtime registers it (master plan §5): an App\ class the
 * application does not define is the Base\ one under another name (an alias, not a subclass); one it defines is
 * its own. App\Entity\Note is this application's; App\Enum\CommentState and App\Enum\Allergen are omnibase's.
 */
#[Route('/aliases', name: 'aliases')]
final class Aliases
{
    public function __invoke(): Json
    {
        $answers = [];
        foreach (['App\\Entity\\Note', 'App\\Enum\\CommentState', 'App\\Enum\\Allergen'] as $class) {
            $answers[$class] = (class_exists($class) || enum_exists($class)) ? (new \ReflectionClass($class))->getName() : null;
        }

        return new Json($answers);
    }
}
