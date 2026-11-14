<?php

namespace Valtzu\DomainTracingBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\AgentInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Valtzu\DomainTracingBundle\Ai\SecretRedactor;
use Valtzu\DomainTracingBundle\Storage\BlobStore;
use Valtzu\DomainTracingBundle\Ai\OperationNamer;
use Valtzu\DomainTracingBundle\Command\TraceCommand;
use Valtzu\DomainTracingBundle\Tracing\TraceDescriber;
use Valtzu\DomainTracingBundle\Xdebug\TraceIngester;
use Valtzu\DomainTracingBundle\Ai\TraceNarrator;
use Valtzu\DomainTracingBundle\Storage\TraceStore;
use Valtzu\DomainTracingBundle\Tracing\TraceStream;
use Valtzu\DomainTracingBundle\Xdebug\XdebugValue;

final class TraceCommandTest extends TestCase
{
    private string $directory;
    private TraceStore $store;
    private CommandTester $tester;

    #[\Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/domain-tracing-'.bin2hex(random_bytes(4));
        $this->store = new TraceStore($this->directory.'/traces');
        $blobs = new BlobStore($this->directory.'/blobs', null);
        $agent = $this->createStub(AgentInterface::class);
        $namer = new OperationNamer($agent, new ArrayAdapter());
        $stream = new TraceStream($this->store, $blobs, $namer, new TraceIngester($this->store, new XdebugValue(new SecretRedactor([]))), new TraceDescriber($this->store, $blobs, $namer, new TraceNarrator($agent)));
        $this->tester = new CommandTester(new Command(null, new TraceCommand($this->store, $stream)));
    }

    #[\Override]
    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    public function testPrintsTheLatestRequest(): void
    {
        $this->store->start('aaaaaaaaaaaaaaaa', ['traceId' => str_repeat('a', times: 32), 'spanId' => 'aaaaaaaaaaaaaaaa', 'parentSpanId' => null, 'service' => 'shop', 'method' => 'GET', 'uri' => '/products?page=2']);
        $this->store->end('aaaaaaaaaaaaaaaa', microtime(true));

        static::assertSame(Command::SUCCESS, $this->tester->execute([]));
        static::assertMatchesRegularExpression('/^  \+0\.000s GET \/products \[shop\]\n  \+\d\.\d{3}s end of GET \/products \[shop\] after \d\.\d{3}s\n$/', $this->tester->getDisplay());
    }

    public function testPrintsEachEventAsJson(): void
    {
        $this->store->start('aaaaaaaaaaaaaaaa', ['traceId' => str_repeat('a', times: 32), 'spanId' => 'aaaaaaaaaaaaaaaa', 'parentSpanId' => null, 'service' => 'shop', 'method' => 'GET', 'uri' => '/']);
        $this->store->end('aaaaaaaaaaaaaaaa', microtime(true));

        $this->tester->execute(['request' => 'aaaaaaaaaaaaaaaa', '--json' => true]);

        $events = array_map(static fn (string $line): mixed => json_decode($line, associative: true, flags: \JSON_THROW_ON_ERROR)['event'] ?? null, explode("\n", trim($this->tester->getDisplay())));
        static::assertSame(['request', 'span', 'end', 'span', 'done'], $events);
    }

    public function testFailsWhenNoRequestWasRecorded(): void
    {
        static::assertSame(Command::FAILURE, $this->tester->execute([]));
        static::assertSame("No request was recorded yet.\n", $this->tester->getDisplay());
    }

    public function testFailsForAnUnknownRequest(): void
    {
        static::assertSame(Command::FAILURE, $this->tester->execute(['request' => 'bbbbbbbbbbbbbbbb']));
        static::assertSame("No request \"bbbbbbbbbbbbbbbb\" was recorded.\n", $this->tester->getDisplay());
    }
}
