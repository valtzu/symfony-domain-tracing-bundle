<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Execution\ExecutionInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Filesystem\Filesystem;
use Valtzu\DomainTracingBundle\Ai\OperationNamer;
use Valtzu\DomainTracingBundle\Ai\TraceNarrator;
use Valtzu\DomainTracingBundle\Storage\BlobStore;
use Valtzu\DomainTracingBundle\Storage\TraceStore;
use Valtzu\DomainTracingBundle\Tracing\TraceDescriber;

final class TraceDescriberTest extends TestCase
{
    private const ROOT = '8985fb33258f4229';

    private string $directory;

    #[\Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/domain-tracing-'.bin2hex(random_bytes(4));
    }

    #[\Override]
    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    public function testDescribesALongRequestWithALimitedNumberOfCalls(): void
    {
        $store = new TraceStore($this->directory.'/traces');
        $store->start(self::ROOT, ['spanId' => self::ROOT, 'traceId' => str_repeat('a', 32), 'parentSpanId' => null, 'service' => 'shop', 'method' => 'POST', 'uri' => '/import']);
        $store->describeFunction(self::ROOT, ['fn' => 0, 'function' => 'App\Import->importRow', 'file' => 0, 'line' => 1, 'endLine' => 3]);
        // An import that handles 5000 rows
        for ($row = 0; $row < 5000; $row++) {
            $store->addStep(self::ROOT, 0, 2, 1, $row * 10);
            $store->addExit(self::ROOT, 1, 0.0);
        }
        $store->end(self::ROOT, microtime(true), ['status' => 200]);

        $prompts = [];
        $execution = $this->createStub(ExecutionInterface::class);
        $execution->method('getContent')->willReturnCallback(static fn () => new \ArrayIterator([]));
        $agent = $this->createStub(AgentInterface::class);
        $agent->method('call')->willReturnCallback(static function (MessageBag $messages) use (&$prompts, $execution): ExecutionInterface {
            $prompts[] = (string) $messages->getUserMessage()?->asText();

            return $execution;
        });

        $describer = new TraceDescriber($store, new BlobStore($this->directory.'/blobs', $this->directory), new OperationNamer($agent, new ArrayAdapter()), new TraceNarrator($agent));
        iterator_to_array($describer->describe(self::ROOT), preserve_keys: false);

        $narrations = array_filter($prompts, static fn (string $prompt): bool => str_starts_with($prompt, 'Events so far'));
        static::assertNotEmpty($narrations);
        foreach ($narrations as $prompt) {
            static::assertLessThanOrEqual(200, preg_match_all('/^\d+: /m', $prompt), 'The calls in one prompt are limited');
        }
        static::assertStringContainsString('4800 more calls are not listed', (string) end($narrations));
        static::assertTrue($store->isDescribed(self::ROOT));
    }
}
