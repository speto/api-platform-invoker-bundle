<?php

declare(strict_types=1);

namespace Speto\ApiPlatformInvokerBundle\Tests\Fixtures;

use Speto\ApiPlatformInvokerBundle\ApiPlatformInvokerBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new ApiPlatformInvokerBundle();
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'test' => true,
            'secret' => 'test',
            'router' => [
                'utf8' => true,
            ],
        ]);

        $container->services()
            ->defaults()
            ->autowire()
            ->autoconfigure();
    }
}
