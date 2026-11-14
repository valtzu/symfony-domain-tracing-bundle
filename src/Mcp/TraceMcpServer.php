<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Mcp;

use Mcp\Capability\Registry;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Content\TextResourceContents;
use Mcp\Schema\Extension\Apps\McpApps;
use Mcp\Schema\Extension\Apps\UiToolMeta;
use Mcp\Schema\Implementation;
use Mcp\Schema\Notification\ResourceListChangedNotification;
use Mcp\Schema\Notification\ToolListChangedNotification;
use Mcp\Schema\ResourceDefinition;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\ServerCapabilities;
use Mcp\Schema\Tool;
use Mcp\Server;
use Mcp\Server\ClientGateway;
use Mcp\Server\Configuration;
use Mcp\Server\Handler\Request\InitializeHandler;
use Mcp\Server\Transport\StdioTransport;
use Twig\Environment;
use Valtzu\DomainTracingBundle\Storage\TraceStore;
use Valtzu\DomainTracingBundle\Tracing\TracePoller;
use Valtzu\DomainTracingBundle\Tracing\TraceStream;
use Valtzu\DomainTracingBundle\Tracing\TraceTextFormatter;

/**
 * Serves the traces to AI tools as an MCP server on the standard input and output. The "domain_trace" tool gives a
 * trace as text, and hosts that show MCP apps show its waterfall too. The events of the waterfall are in the "_meta"
 * of the result, not in its structured content, because some hosts give the structured content to the model instead
 * of the text.
 *
 * The MCP SDK (mcp/sdk) is optional: only this server needs it.
 */
final readonly class TraceMcpServer
{
    public const string APP_URI = 'ui://domain-tracing/trace';
    public const string META_KEY = 'valtzu/domain-tracing';
    public function __construct(
        private TraceStore $store,
        private TraceStream $traces,
        private Environment $twig,
        private TracePoller $poller,
        private bool $describes,
        private int $timeoutSeconds = 600,
    ) {
    }

    /**
     * @param resource $input
     * @param resource $output
     */
    public function run($input = \STDIN, $output = \STDOUT): void
    {
        if (!class_exists(Server::class)) {
            throw new \LogicException('The MCP server needs the MCP SDK: run "composer require --dev mcp/sdk".');
        }

        $registry = new Registry();
        $app = $this->app();
        $tool = function (ClientGateway $client, string $request = 'latest', bool $values = false) use ($registry, &$app, &$tool): CallToolResult {
            // The hosts keep the app of a tool until the server says that its tools changed
            $latest = $this->app();
            if ($latest !== $app) {
                $app = $latest;
                $this->register($registry, $app, $tool);
                $client->notify(new ToolListChangedNotification());
                $client->notify(new ResourceListChangedNotification());
            }

            return $this->trace($request, $values);
        };
        $this->register($registry, $app, $tool);

        $serverInfo = new Implementation('domain-tracing', '1.0.0');
        $instructions = 'The traces of the requests of this Symfony application, in the terms of the business: what each request did, decided and why. Use them to find out what a request did, such as why it failed, instead of reading the code.';
        $apps = new McpApps();
        $capabilities = new ServerCapabilities(prompts: false, extensions: [(string) $apps->getId() => $apps->getCapabilities()]);

        Server::builder()
            ->setServerInfo($serverInfo->name, $serverInfo->version)
            ->setInstructions($instructions)
            ->enableExtension($apps)
            ->addRequestHandler(new ListChangedInitializeHandler(new InitializeHandler(new Configuration($serverInfo, $capabilities, instructions: $instructions))))
            ->setRegistry($registry)
            ->build()
            ->run(new StdioTransport($input, $output));
    }

    public function trace(string $request = 'latest', bool $values = false): CallToolResult
    {
        $startedAt = microtime(true);
        $stop = fn (): bool => microtime(true) - $startedAt >= $this->timeoutSeconds;
        // The server reads the next message of the host only when the tool call suspends its fiber. Meanwhile, the host
        // waits for the app of the tool, and the app for its calls.
        $wait = static function (): void {
            null === \Fiber::getCurrent() ? usleep(100_000) : \Fiber::suspend();
        };
        $id = $this->store->find($request, $stop, $wait);
        if (null === $id) {
            return new CallToolResult([new TextContent(match ($request) {
                'latest' => 'No request was recorded yet. The web server records the requests when its php.ini has xdebug.mode=trace,coverage.',
                'next' => 'No request started.',
                default => \sprintf('No request "%s" was recorded.', $request),
            }), ], isError: true);
        }

        $id = $this->store->rootOf($id);
        $formatter = new TraceTextFormatter($values);
        $lines = [];
        $events = [];
        if ($this->describes) {
            // The AI calls would block the server, so another process makes them
            $this->poller->describeInBackground($id);
        }
        foreach ($this->traces->follow($id, $this->describes, $stop, describeHere: false, wait: $wait) as [$event, $data]) {
            array_push($lines, ...$formatter->format($event, $data));
            $events[] = [$event, $data];
        }

        return new CallToolResult(
            [new TextContent(implode("\n", $lines))],
            meta: [self::META_KEY => ['id' => $id, 'describe' => $this->describes, 'events' => $events]],
        );
    }

    private function register(Registry $registry, string $app, \Closure $tool): void
    {
        // The hosts keep the app by its URI, so a new version of the app gets a new URI
        $appUri = self::APP_URI.'-'.substr(hash('xxh128', $app), 0, 12);

        $registry->registerResource(
            new ResourceDefinition($appUri, 'domain-trace', 'Domain trace', 'The waterfall of a trace', McpApps::MIME_TYPE, meta: ['ui' => McpApps::resourceMarker()]),
            static fn (): TextResourceContents => new TextResourceContents($appUri, McpApps::MIME_TYPE, $app),
        );
        $registry->registerTool(new Tool(
            'domain_trace',
            'Domain trace',
            [
                'type' => 'object',
                'properties' => [
                    'request' => ['type' => 'string', 'description' => 'The ID of a request, as the web profiler shows it, "latest" for the last request (the default), or "next" to wait for the next one'],
                    'values' => ['type' => 'boolean', 'description' => 'Include the arguments and the return values of the operations'],
                ],
            ],
            'Gives what a request of the application did: its operations with business names, in call order, and the events that tell what happened and why, which AI describes unless they are described already. This takes some time. Wait for the next request with "next", for example while you reproduce a bug.',
            null,
            meta: ['ui' => new UiToolMeta($appUri)],
        ), $tool);
    }

    private function app(): string
    {
        $public = \dirname(__DIR__, 2).'/public';

        return $this->twig->render('@DomainTracing/mcp_app.html.twig', [
            'style' => (string) file_get_contents($public.'/trace.css'),
            'script' => (string) file_get_contents($public.'/trace.js'),
            'meta_key' => self::META_KEY,
        ]);
    }
}
