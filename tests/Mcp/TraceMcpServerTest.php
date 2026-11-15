<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Tests\Mcp;

use Symfony\AI\Platform\Test\MockPlatformFactory;
use Mcp\Schema\Content\TextContent;
use Symfony\AI\Agent\AgentInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Valtzu\DomainTracingBundle\Ai\OperationNamer;
use Valtzu\DomainTracingBundle\Ai\SecretRedactor;
use Valtzu\DomainTracingBundle\Ai\TraceNarrator;
use Valtzu\DomainTracingBundle\Mcp\TraceMcpServer;
use Valtzu\DomainTracingBundle\Storage\BlobStore;
use Valtzu\DomainTracingBundle\Storage\TraceStore;
use Valtzu\DomainTracingBundle\Tracing\TraceDescriber;
use Valtzu\DomainTracingBundle\Tracing\TracePoller;
use Valtzu\DomainTracingBundle\Tracing\TraceStream;
use Valtzu\DomainTracingBundle\Xdebug\TraceIngester;
use Valtzu\DomainTracingBundle\Xdebug\XdebugValue;

final class TraceMcpServerTest extends KernelTestCase
{
    private const array INITIALIZE = ['method' => 'initialize', 'params' => ['protocolVersion' => '2025-06-18', 'capabilities' => ['extensions' => ['io.modelcontextprotocol/ui' => ['mimeTypes' => ['text/html;profile=mcp-app']]]], 'clientInfo' => ['name' => 'test', 'version' => '1']]];

    public function testServesTheTraceToolWithItsApp(): void
    {
        $responses = $this->serve([
            self::INITIALIZE,
            ['method' => 'notifications/initialized'],
            ['method' => 'tools/list'],
            ['method' => 'tools/call', 'params' => ['name' => 'domain_trace', 'arguments' => ['request' => 'ffffffffffffffff']]],
        ]);

        $tools = array_column($responses[2]['result']['tools'] ?? [], null, 'name');
        static::assertSame(['domain_trace'], array_keys($tools));
        static::assertSame(['request', 'values'], array_keys($tools['domain_trace']['inputSchema']['properties'] ?? []), 'Calling the tool means that the caller wants the trace described with AI');
        $appUri = $tools['domain_trace']['_meta']['ui']['resourceUri'] ?? null;
        static::assertIsString($appUri);
        static::assertMatchesRegularExpression('{^'.preg_quote(TraceMcpServer::APP_URI).'-[0-9a-f]{12}$}', $appUri, 'A new version of the app gets a new URI, because the hosts keep the app by its URI');

        static::assertTrue($responses[3]['result']['isError'] ?? false);
        static::assertSame('No request "ffffffffffffffff" was recorded.', $responses[3]['result']['content'][0]['text'] ?? null);

        $responses = $this->serve([
            self::INITIALIZE,
            ['method' => 'notifications/initialized'],
            ['method' => 'resources/read', 'params' => ['uri' => $appUri]],
        ]);

        $app = $responses[2]['result']['contents'][0] ?? [];
        static::assertSame($appUri, $app['uri'] ?? null);
        static::assertSame('text/html;profile=mcp-app', $app['mimeType'] ?? null);
        static::assertStringContainsString('function followTrace', $app['text'] ?? '');
        static::assertStringContainsString('ui/initialize', $app['text'] ?? '');
        static::assertStringContainsString("_meta?.['".TraceMcpServer::META_KEY."']", $app['text'] ?? '');
    }

    public function testTellsTheHostWhenTheAppChanges(): void
    {
        $directory = sys_get_temp_dir().'/domain-tracing-'.bin2hex(random_bytes(4));
        $store = new TraceStore($directory.'/traces');
        $blobs = new BlobStore($directory.'/blobs', null);
        $agent = $this->createStub(AgentInterface::class);
        $namer = new OperationNamer($agent, new ArrayAdapter());
        $stream = new TraceStream($store, $blobs, $namer, new TraceIngester($store, new XdebugValue(new SecretRedactor([])), new SecretRedactor([])), new TraceDescriber($store, $blobs, $namer, new TraceNarrator($agent)));
        $twig = new Environment(new ArrayLoader(['@DomainTracing/mcp_app.html.twig' => 'version {{ random() }}']));

        try {
            [$responses, $notifications] = $this->serveWith(new TraceMcpServer($store, $stream, $twig, new TracePoller($store, $stream, $directory, 'test'), false), [
                self::INITIALIZE,
                ['method' => 'notifications/initialized'],
                ['method' => 'tools/list'],
                ['method' => 'tools/call', 'params' => ['name' => 'domain_trace', 'arguments' => ['request' => 'ffffffffffffffff']]],
                ['method' => 'tools/list'],
            ]);
        } finally {
            (new Filesystem())->remove($directory);
        }

        static::assertTrue($responses[0]['result']['capabilities']['tools']['listChanged'] ?? false);
        static::assertTrue($responses[0]['result']['capabilities']['resources']['listChanged'] ?? false);
        static::assertSame(['notifications/tools/list_changed', 'notifications/resources/list_changed'], $notifications);
        $before = $responses[2]['result']['tools'][0]['_meta']['ui']['resourceUri'] ?? null;
        $after = $responses[4]['result']['tools'][0]['_meta']['ui']['resourceUri'] ?? null;
        static::assertIsString($before);
        static::assertIsString($after);
        static::assertNotSame($before, $after);
        static::assertTrue($responses[3]['result']['isError'] ?? false);
    }

    public function testAnswersTheHostWhileTheToolWaits(): void
    {
        $directory = sys_get_temp_dir().'/domain-tracing-'.bin2hex(random_bytes(4));
        $store = new TraceStore($directory.'/traces');
        $blobs = new BlobStore($directory.'/blobs', null);
        $agent = $this->createStub(AgentInterface::class);
        $namer = new OperationNamer($agent, new ArrayAdapter());
        $stream = new TraceStream($store, $blobs, $namer, new TraceIngester($store, new XdebugValue(new SecretRedactor([])), new SecretRedactor([])), new TraceDescriber($store, $blobs, $namer, new TraceNarrator($agent)));
        $server = new TraceMcpServer($store, $stream, new Environment(new ArrayLoader(['@DomainTracing/mcp_app.html.twig' => 'app'])), new TracePoller($store, $stream, $directory, 'test'), false, timeoutSeconds: 1);

        try {
            [$responses] = $this->serveWith($server, [
                self::INITIALIZE,
                ['method' => 'notifications/initialized'],
                ['method' => 'tools/call', 'params' => ['name' => 'domain_trace', 'arguments' => ['request' => 'next']]],
                ['method' => 'tools/list'],
            ], openSeconds: 2);
        } finally {
            (new Filesystem())->remove($directory);
        }

        static::assertSame([0, 3, 2], array_keys($responses), 'The host reads the app while the tool waits for the next request');
        static::assertSame('No request started.', $responses[2]['result']['content'][0]['text'] ?? null);
    }

    public function testGivesTheAppTheTraceOfTheLastCallAsAResource(): void
    {
        $directory = sys_get_temp_dir().'/domain-tracing-'.bin2hex(random_bytes(4));
        $store = new TraceStore($directory.'/traces');
        $blobs = new BlobStore($directory.'/blobs', null);
        $agent = $this->createStub(AgentInterface::class);
        $namer = new OperationNamer($agent, new ArrayAdapter());
        $stream = new TraceStream($store, $blobs, $namer, new TraceIngester($store, new XdebugValue(new SecretRedactor([])), new SecretRedactor([])), new TraceDescriber($store, $blobs, $namer, new TraceNarrator($agent)));
        $server = static fn (): TraceMcpServer => new TraceMcpServer($store, $stream, new Environment(new ArrayLoader(['@DomainTracing/mcp_app.html.twig' => 'app'])), new TracePoller($store, $stream, $directory, 'test'), false);
        $store->start('aaaaaaaaaaaaaaaa', ['traceId' => str_repeat('a', times: 32), 'spanId' => 'aaaaaaaaaaaaaaaa', 'parentSpanId' => null, 'service' => 'shop', 'method' => 'GET', 'uri' => '/products']);
        $store->end('aaaaaaaaaaaaaaaa', microtime(true));
        $read = static fn (int $cursor): array => ['method' => 'resources/read', 'params' => ['uri' => TraceMcpServer::EVENTS_URI.'/'.$cursor]];

        try {
            [$before] = $this->serveWith($server(), [self::INITIALIZE, ['method' => 'notifications/initialized'], $read(0)]);
            $this->serveWith($server(), [self::INITIALIZE, ['method' => 'notifications/initialized'], ['method' => 'tools/call', 'params' => ['name' => 'domain_trace', 'arguments' => []]]]);
            // Claude Desktop lets an app read resources, but not call tools, and the CLI that runs the server may use another process
            [$after] = $this->serveWith($server(), [self::INITIALIZE, ['method' => 'notifications/initialized'], ['method' => 'resources/templates/list'], $read(0)]);
        } finally {
            (new Filesystem())->remove($directory);
        }

        static::assertSame([TraceMcpServer::EVENTS_URI.'/{cursor}'], array_column($after[2]['result']['resourceTemplates'] ?? [], 'uriTemplate'));
        static::assertSame(['id' => null], json_decode($before[2]['result']['contents'][0]['text'] ?? '', true), 'No tool call followed a trace yet');
        $trace = json_decode($after[3]['result']['contents'][0]['text'] ?? '', true);
        static::assertSame('aaaaaaaaaaaaaaaa', $trace['id'] ?? null);
        static::assertSame(['request', 'span', 'end', 'span', 'done'], array_column($trace['events'] ?? [], 0));
        static::assertTrue($trace['done'] ?? null);
    }

    public function testGivesTheTraceAsTextToTheModelAndItsEventsToTheApp(): void
    {
        $directory = sys_get_temp_dir().'/domain-tracing-'.bin2hex(random_bytes(4));
        $store = new TraceStore($directory.'/traces');
        $blobs = new BlobStore($directory.'/blobs', null);
        $agent = $this->createStub(AgentInterface::class);
        $namer = new OperationNamer($agent, new ArrayAdapter());
        $stream = new TraceStream($store, $blobs, $namer, new TraceIngester($store, new XdebugValue(new SecretRedactor([])), new SecretRedactor([])), new TraceDescriber($store, $blobs, $namer, new TraceNarrator($agent)));
        $server = new TraceMcpServer($store, $stream, new Environment(new ArrayLoader()), new TracePoller($store, $stream, $directory, 'test'), false);
        $store->start('aaaaaaaaaaaaaaaa', ['traceId' => str_repeat('a', times: 32), 'spanId' => 'aaaaaaaaaaaaaaaa', 'parentSpanId' => null, 'service' => 'shop', 'method' => 'GET', 'uri' => '/products']);
        $store->end('aaaaaaaaaaaaaaaa', microtime(true));

        try {
            $result = $server->trace('latest');
        } finally {
            (new Filesystem())->remove($directory);
        }

        static::assertNull($result->structuredContent, 'Some hosts give the structured content to the model instead of the text');
        static::assertInstanceOf(TextContent::class, $result->content[0]);
        static::assertStringContainsString('GET /products [shop]', $result->content[0]->text);
        $trace = $result->meta[TraceMcpServer::META_KEY] ?? [];
        static::assertSame('aaaaaaaaaaaaaaaa', $trace['id'] ?? null);
        static::assertFalse($trace['describe'] ?? null);
        static::assertSame(['request', 'span', 'end', 'span', 'done'], array_column($trace['events'] ?? [], 0));
    }

    /**
     * @param list<array<string, mixed>> $messages
     *
     * @return array<int, array<array-key, mixed>> the responses by request ID
     */
    private function serve(array $messages): array
    {
        self::getContainer()->set('test.platform', MockPlatformFactory::createPlatform(static fn (): string => ''));
        $server = self::getContainer()->get('domain_tracing.mcp_server');
        static::assertInstanceOf(TraceMcpServer::class, $server);

        return $this->serveWith($server, $messages)[0];
    }

    /**
     * @param list<array<string, mixed>> $messages
     *
     * @return array{array<int, array<array-key, mixed>>, list<string>} the responses by request ID, and the methods of the notifications
     */
    private function serveWith(TraceMcpServer $server, array $messages, int $openSeconds = 0): array
    {
        // The transport waits for input with stream_select(), which needs real streams
        $inputPath = (string) tempnam(sys_get_temp_dir(), 'domain-tracing-mcp-');
        $outputPath = (string) tempnam(sys_get_temp_dir(), 'domain-tracing-mcp-');
        foreach ($messages as $id => $message) {
            file_put_contents($inputPath, json_encode(['jsonrpc' => '2.0'] + (str_starts_with($message['method'], 'notifications/') ? [] : ['id' => $id]) + $message, \JSON_THROW_ON_ERROR)."\n", \FILE_APPEND);
        }
        // The transport stops at the end of the input, so a host that waits for an answer keeps its input open
        $input = 0 === $openSeconds ? fopen($inputPath, 'r') : popen(\sprintf('cat %s; sleep %d', escapeshellarg($inputPath), $openSeconds), 'r');
        $output = fopen($outputPath, 'w');
        static::assertIsResource($input);
        static::assertIsResource($output);

        $server->run($input, $output);
        unlink($inputPath);

        $responses = [];
        $notifications = [];
        $lines = explode("\n", trim((string) file_get_contents($outputPath)));
        unlink($outputPath);
        foreach ($lines as $line) {
            $message = json_decode($line, true, flags: \JSON_THROW_ON_ERROR);
            if (\is_array($message) && isset($message['id'])) {
                $responses[(int) $message['id']] = $message;
            } elseif (\is_array($message) && \is_string($message['method'] ?? null)) {
                $notifications[] = $message['method'];
            }
        }

        return [$responses, $notifications];
    }
}
