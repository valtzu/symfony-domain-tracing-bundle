<?php

namespace Valtzu\DomainTracingBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class DomainTracingBundle extends AbstractBundle
{
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/ai.php');
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // The routes and the profiler panel show the code and the data of the requests
        if (!$builder->getParameter('kernel.debug')) {
            return;
        }

        $container->import('../config/services.php');
    }
}
