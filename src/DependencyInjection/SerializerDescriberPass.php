<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Lets the serializer describer of Symfony AI create its own class metadata when the serializer of the application is
 * disabled, as in a new Symfony 8.1 project. Otherwise the describer gets null and the AI agents fail.
 *
 * Remove it when https://github.com/symfony/ai/pull/2905 is released.
 */
final class SerializerDescriberPass implements CompilerPassInterface
{
    #[\Override]
    public function process(ContainerBuilder $container): void
    {
        if ($container->hasDefinition('ai.platform.json_schema.describer.serializer') && !$container->has('serializer.mapping.class_metadata_factory')) {
            $container->getDefinition('ai.platform.json_schema.describer.serializer')->setArguments([]);
        }
    }
}
