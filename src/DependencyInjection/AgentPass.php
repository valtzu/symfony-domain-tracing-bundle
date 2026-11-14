<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Valtzu\DomainTracingBundle\Ai\AgentWithoutTools;
use Valtzu\DomainTracingBundle\Ai\MissingAgent;

/**
 * The application defines the AI agent of the bundle. Without it, the traces are recorded but not described, and the
 * services that need an agent get one that tells how to define it. An agent on the Claude Code platform is called
 * without the tools of the CLI.
 */
final class AgentPass implements CompilerPassInterface
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
            if (self::usesClaudeCode($container, $agent)) {
                $container->removeAlias('domain_tracing.agent');
                $container->setDefinition('domain_tracing.agent', new Definition(AgentWithoutTools::class, [new Reference($agent)]));
            }

            return;
        }

        if (null !== $agent) {
            $container->removeAlias('domain_tracing.agent');
        }
        $container->setDefinition('domain_tracing.agent', new Definition(MissingAgent::class, [null === $agent ? null : substr($agent, \strlen('ai.agent.'))]));
        $container->removeDefinition('domain_tracing.context_generate_command');
    }

    private static function usesClaudeCode(ContainerBuilder $container, string $agent): bool
    {
        $arguments = $container->findDefinition($agent)->getArguments();
        $platform = $arguments['$platform'] ?? $arguments[0] ?? null;
        if (!$platform instanceof Reference || !$container->has((string) $platform)) {
            return false;
        }

        $definition = $container->findDefinition((string) $platform);
        $factory = $definition->getFactory();
        $class = \is_array($factory) && \is_string($factory[0] ?? null) ? $factory[0] : $definition->getClass();

        return \is_string($class) && str_starts_with(ltrim($class, '\\'), 'Symfony\AI\Platform\Bridge\ClaudeCode\\');
    }
}
