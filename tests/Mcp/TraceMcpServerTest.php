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
use Valtzu\DomainTracingBundle\Tracing\TraceStream;
use Valtzu\DomainTracingBundle\Xdebug\TraceIngester;
use Valtzu\DomainTracingBundle\Xdebug\XdebugValue;

final class TraceMcpServerTest extends KernelTestCase
{
    public function testServesTheTraceToolWithItsApp(): void
    {
        $responses = $this->serve([
            ['method' => 'initialize', 'params' => ['protocolVersion' => '2025-06-18', 'capabilities' => ['extensions' => ['io.modelcontextprotocol/ui' => ['mimeTypes' => ['text/html;profile=mcp-app']]]], 'clientInfo' => ['name' => 'test', 'version' => '1']]],
            ['method' => 'notifications/initialized'],
            ['method' => 'tools/list'],
            ['method' => 'resources/read', 'params' => ['uri' => TraceMcpServer::APP_URI]],
            ['method' => 'tools/call', 'params' => ['name' => 'domain_trace', 'arguments' => ['request' => 'ffffffffffffffff']]],
        ]);

        $tools = array_column($responses[2]['result']['tools'] ?? [], null, 'name');
        static::assertSame(TraceMcpServer::APP_URI, $tools['domain_trace']['_meta']['ui']['resourceUri'] ?? null);
        static::assertSame(['domain_trace'], array_keys($tools));

        $app = $responses[3]['result']['contents'][0] ?? [];
        static::assertSame('text/html;profile=mcp-app', $app['mimeType'] ?? null);
        static::assertStringContainsString('function followTrace', $app['text'] ?? '');
        static::assertStringContainsString('ui/initialize', $app['text'] ?? '');
        static::assertStringContainsString("_meta?.['".TraceMcpServer::META_KEY."']", $app['text'] ?? '');

        static::assertTrue($responses[4]['result']['isError'] ?? false);
        static::assertSame('No request "ffffffffffffffff" was recorded.', $responses[4]['result']['content'][0]['text'] ?? null);
    }

    public function testGivesTheTraceAsTextToTheModelAndItsEventsToTheApp(): void
    {
        $directory = sys_get_temp_dir().'/domain-tracing-'.bin2hex(random_bytes(4));
        $store = new TraceStore($directory.'/traces');
        $blobs = new BlobStore($directory.'/blobs', null);
        $agent = $this->createStub(AgentInterface::class);
        $namer = new OperationNamer($agent, new ArrayAdapter());
        $stream = new TraceStream($store, $blobs, $namer, new TraceIngester($store, new XdebugValue(new SecretRedactor([])), new SecretRedactor([])), new TraceDescriber($store, $blobs, $namer, new TraceNarrator($agent)));
        $server = new TraceMcpServer($store, $stream, new Environment(new ArrayLoader()), true);
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
        static::assertSame(['request', 'span', 'end', 'span', 'done'], array_column($trace['events'] ?? [], 0));
    }

    /**
     * @param list<array<string, mixed>> $messages
     *
     * @return array<int, array<array-key, mixed>> the responses by request ID
     */
    private function serve(array $messages): array
    {
        // The transport waits for input with stream_select(), which needs real streams
        $outputPath = (string) tempnam(sys_get_temp_dir(), 'domain-tracing-mcp-');
        $input = tmpfile();
        $output = fopen($outputPath, 'w');
        static::assertIsResource($input);
        static::assertIsResource($output);
        foreach ($messages as $id => $message) {
            fwrite($input, json_encode(['jsonrpc' => '2.0'] + (str_starts_with($message['method'], 'notifications/') ? [] : ['id' => $id]) + $message, \JSON_THROW_ON_ERROR)."\n");
        }
        rewind($input);

        self::getContainer()->set('test.platform', MockPlatformFactory::createPlatform(static fn (): string => ''));
        $server = self::getContainer()->get('domain_tracing.mcp_server');
        static::assertInstanceOf(TraceMcpServer::class, $server);
        $server->run($input, $output);

        $responses = [];
        $lines = explode("\n", trim((string) file_get_contents($outputPath)));
        unlink($outputPath);
        foreach ($lines as $line) {
            $response = json_decode($line, true, flags: \JSON_THROW_ON_ERROR);
            if (\is_array($response) && isset($response['id'])) {
                $responses[(int) $response['id']] = $response;
            }
        }

        return $responses;
    }
}
