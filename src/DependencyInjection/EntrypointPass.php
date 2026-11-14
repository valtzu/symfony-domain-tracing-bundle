<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Finds the classes that the framework calls first in a request or a command, from the tags of their services, so that
 * they are found however they are configured: with attributes, in YAML or in PHP. The record of a request starts at
 * the first call of one of these classes.
 */
final class EntrypointPass implements CompilerPassInterface
{
    private const array TAGS = [
        'controller.service_arguments',
        'console.command',
        'kernel.event_listener',
        'kernel.event_subscriber',
        'messenger.message_handler',
    ];

    #[\Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('domain_tracing.recorder')) {
            return;
        }

        $classes = [];
        foreach (self::TAGS as $tag) {
            foreach (array_keys($container->findTaggedServiceIds($tag)) as $id) {
                $class = $container->getParameterBag()->resolveValue($container->findDefinition($id)->getClass() ?? $id);
                if (\is_string($class) && !str_starts_with($class, 'Valtzu\DomainTracingBundle\\')) {
                    $classes[ltrim($class, '\\')] = true;
                }
            }
        }
        ksort($classes);

        $container->getDefinition('domain_tracing.recorder')->setArgument('$entrypoints', array_keys($classes));
    }
}
