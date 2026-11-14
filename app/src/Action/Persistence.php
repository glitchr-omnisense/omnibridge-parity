<?php

namespace App\Action;

use Base\Screen\Json;
use Omnibridge\Persistence\Backend;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The persistence this host runs on (omnisense/omnibridge's Backend: "doctrine", "eloquent"), so that the parity suite
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
