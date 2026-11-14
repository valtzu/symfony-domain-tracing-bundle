<?php

namespace Valtzu\DomainTracingBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class DomainTracingBundle extends AbstractBundle
{
    #[\Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->stringNode('context')
                    ->info('Description of the application that the AI agents get with their instructions, such as what it does, who reads the traces and the words of the business')
                    ->example('The application is a web shop. The people who read the traces work in customer support.')
                ->end()
                ->arrayNode('exclude_uris')
                    ->info('Regular expressions, without delimiters, matched against the path and the query of the requests not to record. The pages of this bundle and of the profiler are never recorded.')
                    ->example(['^/health$', '[?&]frankenphp_hot_reloading='])
                    ->scalarPrototype()->end()
                ->end()
            ->end();
    }

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

        $container->parameters()->set('domain_tracing.exclude_uris', $config['exclude_uris']);
        $container->import('../config/services.php');
        $container->parameters()->set('domain_tracing.context', $config['context'] ?? null);
    }
}
