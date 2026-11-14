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
        $stream = new TraceStream($this->store, $blobs, $namer, new TraceIngester($this->store, new XdebugValue(new SecretRedactor([])), new SecretRedactor([])), new TraceDescriber($this->store, $blobs, $namer, new TraceNarrator($agent)));
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

    public function testPrintsHowTheRequestFailed(): void
    {
        $traces = $this->directory.'/traces';
        $fingerprint = str_repeat('c', times: 32);
        $traceFile = $traces.'/trace.1.xt';
        $this->store->start('aaaaaaaaaaaaaaaa', ['traceId' => str_repeat('a', times: 32), 'spanId' => 'aaaaaaaaaaaaaaaa', 'parentSpanId' => null, 'service' => 'shop', 'method' => 'POST', 'uri' => '/', 'traceFile' => $traceFile, 'codeIndex' => $fingerprint]);
        $file = static fn (string $class, bool $entrypoint, string $method): array => ['class' => $class, 'blob' => str_repeat('0', times: 40), 'entrypoint' => $entrypoint, 'methods' => [$method => [5, 9, []]]];
        $files = [
            '/app/src/Shop.php' => $file('App\Shop', entrypoint: true, method: 'order'),
            '/app/src/Gateway.php' => $file('App\Gateway', entrypoint: false, method: 'charge'),
        ];
        file_put_contents($traces.'/codeindex-'.$fingerprint.'.json', json_encode(['root' => '/app', 'namespaces' => ['App\\'], 'files' => $files]));
        file_put_contents($traceFile, implode("\n", [
            'TRACE START ['.\DateTimeImmutable::createFromFormat('U.u', \sprintf('%.6F', $this->store->request('aaaaaaaaaaaaaaaa')['startedAt']))->format('Y-m-d H:i:s.u').']',
            "2\t1\t0\t0.001\t1000\tApp\\Shop->order\t1\t\t/app/src/Shop.php\t5\t0",
            "3\t2\t0\t0.002\t1000\tApp\\Gateway->charge\t1\t\t/app/src/Gateway.php\t5\t0",
            "\t\t\t0.010\t1000",
            'TRACE END',
            '',
        ]));
        $exception = [
            'class' => 'Symfony\Component\HttpClient\Exception\TransportException',
            'message' => 'Could not resolve host for https://shop:hunter2@payments.invalid/charges',
            'functions' => ['Symfony\Component\HttpClient\Response\CurlResponse::perform', 'App\Gateway->charge', 'App\Shop->order'],
        ];
        file_put_contents($traceFile.'.outcome.json', json_encode(['status' => 500, 'exception' => $exception]));

        $this->tester->execute([]);

        static::assertStringContainsString("      ✗ threw Symfony\\Component\\HttpClient\\Exception\\TransportException: Could not resolve host for https://shop:[redacted]@payments.invalid/charges\n", $this->tester->getDisplay());
        static::assertMatchesRegularExpression('/end of POST \/ \[shop\] after \d\.\d{3}s with status 500\n$/', $this->tester->getDisplay());
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
