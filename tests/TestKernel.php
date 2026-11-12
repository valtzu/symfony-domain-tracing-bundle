<?php

namespace Valtzu\AiStacktrace\Tests;

use Symfony\AI\AiBundle\AiBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Valtzu\AiStacktrace\AiStacktraceBundle;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new AiBundle();
        yield new AiStacktraceBundle();
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__);
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['test' => true, 'secret' => 'test']);
    }
}
