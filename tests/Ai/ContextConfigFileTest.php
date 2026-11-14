<?php

declare(strict_types=1);

namespace Valtzu\DomainTracingBundle\Tests\Ai;

use PHPUnit\Framework\TestCase;
use Valtzu\DomainTracingBundle\Ai\ContextConfigFile;

final class ContextConfigFileTest extends TestCase
{
    public function testCreatesTheFile(): void
    {
        static::assertSame(
            "when@dev:\n    domain_tracing:\n        context: |\n            The shop sells shirts.\n",
            ContextConfigFile::withContext(null, 'The shop sells shirts.'),
        );
    }

    public function testReplacesTheContextAndKeepsTheOtherOptionsAndComments(): void
    {
        $yaml = <<<'YAML'
            # yaml-language-server: $schema=../schema.json
            when@dev:
                domain_tracing:
                    context: |
                        Old description.

                        Second paragraph.

                    exclude_uris:
                        # a comment
                        - '[?&]reload='

            YAML;

        static::assertSame(<<<'YAML'
            # yaml-language-server: $schema=../schema.json
            when@dev:
                domain_tracing:
                    context: |
                        New description.

                    exclude_uris:
                        # a comment
                        - '[?&]reload='

            YAML, ContextConfigFile::withContext($yaml, 'New description.'));
    }

    public function testAddsTheContextToTheOtherOptions(): void
    {
        $yaml = "when@dev:\n    domain_tracing:\n        exclude_uris: ['^/health']\n";

        static::assertSame(
            "when@dev:\n    domain_tracing:\n        context: |\n            New description.\n        exclude_uris: ['^/health']\n",
            ContextConfigFile::withContext($yaml, 'New description.'),
        );
    }

    public function testCannotPlaceTheContextWithoutTheKeyOfTheBundle(): void
    {
        static::assertNull(ContextConfigFile::withContext("framework:\n    secret: x\n", 'New description.'));
    }

    public function testKeepsParagraphsAndWrapsLongLines(): void
    {
        $yaml = ContextConfigFile::withContext(null, str_repeat('word ', times: 30)."\n\nSecond paragraph.");

        static::assertSame(
            "when@dev:\n    domain_tracing:\n        context: |\n"
            .'            '.trim(str_repeat('word ', times: 21))."\n"
            .'            '.trim(str_repeat('word ', times: 9))."\n"
            ."\n"
            ."            Second paragraph.\n",
            $yaml,
        );
    }
}
