<?php

namespace Valtzu\DomainTracingBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Valtzu\DomainTracingBundle\DependencyInjection\SerializerDescriberPass;

final class SerializerDescriberPassTest extends TestCase
{
    public function testLetsTheDescriberUseItsDefaultWhenTheSerializerIsDisabled()
    {
        $container = $this->container();

        (new SerializerDescriberPass())->process($container);

        $this->assertSame([], $container->getDefinition('ai.platform.json_schema.describer.serializer')->getArguments());
    }

    public function testKeepsTheMetadataOfTheSerializer()
    {
        $container = $this->container();
        $container->register('serializer.mapping.class_metadata_factory');

        (new SerializerDescriberPass())->process($container);

        $this->assertCount(1, $container->getDefinition('ai.platform.json_schema.describer.serializer')->getArguments());
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition('ai.platform.json_schema.describer.serializer', new Definition(\stdClass::class, [
            new Reference('serializer.mapping.class_metadata_factory', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
        ]));

        return $container;
    }
}
