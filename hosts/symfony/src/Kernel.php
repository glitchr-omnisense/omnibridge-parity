<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/** The Symfony host of the reference application: its bundles (config/bundles.php), its configuration (config/). */
final class Kernel extends BaseKernel
{
    use MicroKernelTrait;
}
