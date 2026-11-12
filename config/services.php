<?php

use Symfony\AI\Platform\Bridge\ClaudeCode\Factory;
use Symfony\AI\Platform\Platform;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Valtzu\DomainTracingBundle\Tracing\TraceDataCollector;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure();

    $services->load('Valtzu\\DomainTracingBundle\\', '../src/')
        ->exclude(['../src/DomainTracingBundle.php', '../src/Tracing/Resources/']);

    $services->set(TraceDataCollector::class)
        ->autoconfigure(false)
        // right after the "request" collector, which has priority 335
        ->tag('data_collector', ['id' => 'domain_traces', 'template' => '@DomainTracing/collector.html.twig', 'priority' => 334]);

    $services->set('domain_tracing.platform', Platform::class)
        // public so that tests can replace it with a mock platform
        ->public()
        ->factory([Factory::class, 'createPlatform'])
        ->arg('$workingDirectory', '%kernel.project_dir%/var')
        ->arg('$timeout', 600);
};
