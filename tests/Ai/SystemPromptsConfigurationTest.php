<?php

declare(strict_types=1);

namespace Valtzu\DomainTracingBundle\Tests\Ai;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Valtzu\DomainTracingBundle\Ai\SystemPrompts;

final class SystemPromptsConfigurationTest extends KernelTestCase
{
    public function testUsesTheConfiguredContext(): void
    {
        $prompts = self::getContainer()->get(SystemPrompts::class);

        static::assertInstanceOf(SystemPrompts::class, $prompts);
        static::assertStringEndsWith("\n\nThe shop sells cotton shirts.", $prompts->get('operation_namer'));
    }
}
