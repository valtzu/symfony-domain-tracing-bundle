<?php

namespace Valtzu\AiStacktrace;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class AiStacktraceBundle extends AbstractBundle
{
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/ai.yaml');
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.yaml');
    }
}
