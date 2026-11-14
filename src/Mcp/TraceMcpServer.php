<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Mcp;

use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Content\TextResourceContents;
use Mcp\Schema\Extension\Apps\McpApps;
use Mcp\Schema\Extension\Apps\ToolVisibility;
use Mcp\Schema\Extension\Apps\UiToolMeta;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server;
use Mcp\Server\Transport\StdioTransport;
use Twig\Environment;
use Valtzu\DomainTracingBundle\Storage\TraceStore;
use Valtzu\DomainTracingBundle\Tracing\TracePoller;
use Valtzu\DomainTracingBundle\Tracing\TraceStream;
use Valtzu\DomainTracingBundle\Tracing\TraceTextFormatter;

/**
 * Serves the traces to AI tools as an MCP server on the standard input and output. The "domain_trace" tool gives a
 * trace as text, and hosts that show MCP apps show its waterfall too. The waterfall gets its events with the
 * "domain_trace_events" tool, through the host, so that it needs no access to the network of the application.
 *
 * The MCP SDK (mcp/sdk) is optional: only this server needs it.
 */
final readonly class TraceMcpServer
{
    public const string APP_URI = 'ui://domain-tracing/trace';
    private const int TIMEOUT_SECONDS = 600;

    public function __construct(
        private TraceStore $store,
        private TraceStream $traces,
        private TracePoller $poller,
        private Environment $twig,
        private bool $describes,
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

        Server::builder()
            ->setServerInfo('domain-tracing', '1.0.0')
            ->setInstructions('The traces of the requests of this Symfony application, in the terms of the business: what each request did, decided and why. Use them to find out what a request did, such as why it failed, instead of reading the code.')
            ->enableExtension(new McpApps())
            ->addTool(
                $this->trace(...),
                'domain_trace',
                'Domain trace',
                'Gives what a request of the application did: its operations with business names, in call order, and, when described, the events that tell what happened and why. Wait for the next request with "next", for example while you reproduce a bug.',
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'request' => ['type' => 'string', 'description' => 'The ID of a request, as the web profiler shows it, "latest" for the last request (the default), or "next" to wait for the next one'],
                        'describe' => ['type' => 'boolean', 'description' => 'Describe what happened with AI, unless it is described already. This takes some time.'],
                        'values' => ['type' => 'boolean', 'description' => 'Include the arguments and the return values of the operations'],
                    ],
                ],
                meta: ['ui' => new UiToolMeta(self::APP_URI)],
            )
            ->addTool(
                $this->events(...),
                'domain_trace_events',
                'Domain trace events',
                'Gives the events of a trace after a cursor, for the waterfall of the trace.',
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'cursor' => ['type' => 'string'],
                        'describe' => ['type' => 'boolean'],
                    ],
                    'required' => ['id'],
                ],
                meta: ['ui' => new UiToolMeta(visibility: [ToolVisibility::App])],
            )
            ->addResource(
                fn (): TextResourceContents => new TextResourceContents(self::APP_URI, McpApps::MIME_TYPE, $this->app()),
                self::APP_URI,
                'domain-trace',
                'Domain trace',
                'The waterfall of a trace',
                McpApps::MIME_TYPE,
                meta: ['ui' => McpApps::resourceMarker()],
            )
            ->build()
            ->run(new StdioTransport($input, $output));
    }

    public function trace(string $request = 'latest', bool $describe = false, bool $values = false): CallToolResult
    {
        $startedAt = microtime(true);
        $stop = static fn (): bool => microtime(true) - $startedAt >= self::TIMEOUT_SECONDS;
        $id = $this->store->find($request, $stop);
        if (null === $id) {
            return new CallToolResult([new TextContent(match ($request) {
                'latest' => 'No request was recorded yet. The web server records the requests when its php.ini has xdebug.mode=trace,coverage.',
                'next' => 'No request started.',
                default => \sprintf('No request "%s" was recorded.', $request),
            }), ], isError: true);
        }

        $id = $this->store->rootOf($id);
        $describe = $describe && $this->describes;
        $formatter = new TraceTextFormatter($values);
        $lines = [];
        foreach ($this->traces->follow($id, $describe, $stop) as [$event, $data]) {
            array_push($lines, ...$formatter->format($event, $data));
        }

        return new CallToolResult(
            [new TextContent(implode("\n", $lines))],
            structuredContent: ['id' => $id, 'describe' => $this->describes],
        );
    }

    public function events(string $id, string $cursor = '0', bool $describe = false): CallToolResult
    {
        if (!TraceStore::isValidId($id) || !$this->store->has($id)) {
            return new CallToolResult([new TextContent(\sprintf('No request "%s" was recorded.', $id))], isError: true);
        }

        $events = $this->poller->events($this->store->rootOf($id), $describe && $this->describes, $cursor);

        return new CallToolResult([new TextContent(\sprintf('%d events', \count($events['events'])))], structuredContent: $events);
    }

    private function app(): string
    {
        $public = \dirname(__DIR__, 2).'/public';

        return $this->twig->render('@DomainTracing/mcp_app.html.twig', [
            'style' => (string) file_get_contents($public.'/trace.css'),
            'script' => (string) file_get_contents($public.'/trace.js'),
        ]);
    }
}
