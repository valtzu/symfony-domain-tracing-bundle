<?php

declare(strict_types=1);

namespace Valtzu\DomainTracingBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Definition;
use Valtzu\DomainTracingBundle\Tests\TestKernel;

final class ServicesTest extends TestCase
{
    public function testWiresItsServicesExplicitly(): void
    {
        $kernel = new TestKernel('test', true);
        $kernel->boot();
        $container = $kernel->containerBuilder();
        $container->compile();

        $services = array_filter($container->getDefinitions(), static fn (Definition $definition): bool => str_starts_with((string) $definition->getClass(), 'Valtzu\DomainTracingBundle\\') && !str_starts_with((string) $definition->getClass(), 'Valtzu\DomainTracingBundle\Tests\\'));
        static::assertNotEmpty($services);
        foreach ($services as $id => $definition) {
            static::assertFalse($definition->isAutowired(), $id.' is autowired');
            static::assertFalse($definition->isAutoconfigured(), $id.' is autoconfigured');
            static::assertStringStartsWith('domain_tracing.', $id);
        }
    }
}
