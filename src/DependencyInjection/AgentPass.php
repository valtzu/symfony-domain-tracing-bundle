<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Valtzu\DomainTracingBundle\Ai\MissingAgent;

/**
 * The application defines the AI agent of the bundle. Without it, the traces are recorded but not described, and the
 * services that need an agent get one that tells how to define it.
 */
final class MissingAgentPass implements CompilerPassInterface
{
    #[\Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('domain_tracing.trace_controller')) {
            return;
        }

        $agent = $container->hasAlias('domain_tracing.agent') ? (string) $container->getAlias('domain_tracing.agent') : null;
        $describe = null !== $agent && $container->has($agent);
        $container->setParameter('domain_tracing.describe', $describe);
        if ($describe) {
            return;
        }

        if (null !== $agent) {
            $container->removeAlias('domain_tracing.agent');
        }
        $container->setDefinition('domain_tracing.agent', new Definition(MissingAgent::class, [null === $agent ? null : substr($agent, \strlen('ai.agent.'))]));
        $container->removeDefinition('domain_tracing.context_generate_command');
    }
}
