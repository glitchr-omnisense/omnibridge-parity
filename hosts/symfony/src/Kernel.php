<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/** The Symfony host of the reference application: its bundles (config/bundles.php), its configuration (config/). */
final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * One container per persistence: omnibridge/symfony chooses it when the container is compiled (OMNIBRIDGE_PERSISTENCE,
     * both bridges being installed), so the Symfony host on Doctrine and the one on Eloquent, the same folder, each
     * keep their own.
     */
    public function getCacheDir(): string
    {
        return $this->getProjectDir().'/var/cache/'.$this->environment.'-'.(getenv('OMNIBRIDGE_PERSISTENCE') ?: 'default');
    }
}
