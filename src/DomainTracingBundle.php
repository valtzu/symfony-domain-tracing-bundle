<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Valtzu\DomainTracingBundle\DependencyInjection\MissingAgentPass;
use Valtzu\DomainTracingBundle\DependencyInjection\SerializerDescriberPass;

final class DomainTracingBundle extends AbstractBundle
{
    #[\Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->stringNode('context_file')
                    ->info('Text file that describes the application to the AI agents, such as what it does, who reads the traces and the words of the business. domain-tracing:context:generate writes a first version. The agents get no description when the file does not exist.')
                    ->defaultValue('%kernel.project_dir%/config/prompts/domain_tracing_context.md')
                ->end()
                ->scalarNode('agent')
                    ->info('Agent of the AI bundle that names the operations and describes the traces. The application defines it under "ai.agent". Without it, or with null or false, the traces are not described.')
                    ->defaultValue('domain_tracing')
                ->end()
            ->end();
    }

    #[\Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new SerializerDescriberPass());
        $container->addCompilerPass(new MissingAgentPass());
    }

    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        // The routes and the profiler panel show the code and the data of the requests
        if (!$container->getParameter('kernel.debug')) {
            return;
        }

        $configurator->parameters()->set('domain_tracing.context_file', $config['context_file'] ?? null);
        $configurator->import('../config/services.php');

        if (\is_string($config['agent']) && '' !== $config['agent']) {
            $agent = 'ai.agent.'.$config['agent'];
            $container->setAlias('domain_tracing.agent', $agent);
            $container->getDefinition('domain_tracing.redact_secrets_input_processor')
                ->addTag('ai.agent.input_processor', ['agent' => $agent, 'priority' => -1000]);
        }
    }
}
