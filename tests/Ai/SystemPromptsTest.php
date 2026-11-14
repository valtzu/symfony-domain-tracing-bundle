<?php

declare(strict_types=1);

namespace Valtzu\DomainTracingBundle\Tests\Ai;

use PHPUnit\Framework\TestCase;
use Valtzu\DomainTracingBundle\Ai\SystemPrompts;

final class SystemPromptsTest extends TestCase
{
    public function testReadsThePromptOfTheBundle(): void
    {
        $prompt = (new SystemPrompts())->get('operation_namer');

        static::assertStringStartsWith('You name the operations of an application', $prompt);
        static::assertStringNotContainsString('About this application', $prompt);
    }

    public function testAddsTheContextOfTheProject(): void
    {
        $prompt = (new SystemPrompts(\dirname(__DIR__).'/Fixtures/context.md'))->get('trace_narrator');

        static::assertStringStartsWith('You describe what happened', $prompt);
        static::assertStringEndsWith("## About this application\n\nThe developers of the application describe it below. Use the description to choose the words and to decide what matters to the business. The rules above still apply.\n\nThe shop sells cotton shirts.", $prompt);
    }

    public function testLeavesOutTheContextWhenTheFileDoesNotExist(): void
    {
        $prompt = (new SystemPrompts(__DIR__.'/missing.md'))->get('operation_namer');

        static::assertStringNotContainsString('About this application', $prompt);
    }
}
