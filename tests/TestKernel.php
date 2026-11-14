<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Tests;

use Symfony\AI\AiBundle\AiBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Valtzu\DomainTracingBundle\DomainTracingBundle;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new AiBundle();
        yield new DomainTracingBundle();
    }

    /**
     * The container before it is compiled, which keeps how each service is defined.
     */
    public function containerBuilder(): ContainerBuilder
    {
        return $this->buildContainer();
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__);
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['test' => true, 'secret' => 'test']);
        $container->extension('domain_tracing', ['context_file' => __DIR__.'/Fixtures/context.md']);

        if ('agent_override' === $this->environment) {
            $container->services()->set('test.platform')->synthetic()->public();
            $agent = ['platform' => 'test.platform', 'model' => 'other-model'];
            $container->extension('ai', ['agent' => ['domain_tracing_operation_namer' => $agent, 'project_agent' => $agent]]);
        }
    }
}
