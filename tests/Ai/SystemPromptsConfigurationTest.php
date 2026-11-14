<?php

declare(strict_types=1);

namespace Valtzu\DomainTracingBundle\Tests\Ai;

use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Valtzu\DomainTracingBundle\Ai\SystemPrompts;

final class SystemPromptsConfigurationTest extends KernelTestCase
{
    public function testUsesTheConfiguredContext(): void
    {
        $prompts = self::getContainer()->get('domain_tracing.system_prompts');

        static::assertInstanceOf(SystemPrompts::class, $prompts);
        static::assertStringEndsWith("\n\nThe shop sells cotton shirts.", $prompts->get('operation_namer'));
    }

    public function testTheAgentsGetTheConfiguredContext(): void
    {
        self::getContainer()->set('test.platform', MockPlatformFactory::createPlatform(static fn (): string => 'ok'));
        $prompts = self::getContainer()->get('domain_tracing.system_prompts');

        foreach (['domain_tracing.operation_namer', 'domain_tracing.trace_narrator', 'domain_tracing.context_writer'] as $id) {
            $service = self::getContainer()->get($id);
            static::assertIsObject($service);
            static::assertSame($prompts, (new \ReflectionProperty($service, 'prompts'))->getValue($service), $id);
        }
    }
}
