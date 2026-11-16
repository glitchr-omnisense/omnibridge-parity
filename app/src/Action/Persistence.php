<?php

namespace App\Action;

use Base\Contract\Backend;
use Base\Screen\Json;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The persistence this host runs on (omnibase's Base\Contract\Backend: "doctrine", "eloquent"), so that the parity suite
 * knows each cell of the matrix is the one it claims (ParityTest::testEveryCellRunsOnItsPersistence). Not compared
 * between cells: it is what tells them apart.
 */
#[Route('/persistence', name: 'persistence')]
final class Persistence
{
    public function __construct(private readonly Backend $backend)
    {
    }

    public function __invoke(): Json
    {
        return new Json(['persistence' => $this->backend->name()]);
    }
}
