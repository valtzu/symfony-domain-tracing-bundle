<?php

namespace App\Tracing;

/**
 * Handles the DBGp connection that Xdebug opens for one request. Xdebug records the calls of the application methods
 * in a trace file, without stopping the process; this session only finds out which request and which trace file it
 * is, and passes the trace context on to the services that the request calls.
 *
 * At the start, the session reads the request and gives it an X-Request-Id header if it has none, so the page can find
 * its trace. It reads the code of the service through the connection, so the recorder needs no access to it: right
 * away when the autoloader of the application is ready, and otherwise when the process first loads a class, before
 * any application code runs. Then it sets a breakpoint on Symfony HttpClient and, each time the request is about to
 * make an HTTP request, adds a traceparent header to its options. The services need no code for it.
 */
final class TraceSession
{
    private const AUTOLOADER = 'Composer\\Autoload\\ClassLoader';
    private const HTTP_CLIENTS = [
        'Symfony\\Component\\HttpClient\\CurlHttpClient',
        'Symfony\\Component\\HttpClient\\NativeHttpClient',
        'Symfony\\Component\\HttpClient\\AmpHttpClient',
    ];

    /** @var array<string, array> code index by service and fingerprint, shared by the sessions */
    private static array $codeIndexes = [];

    private ?array $request = null;
    private ?string $codeIndexCacheKey = null;
    private ?string $autoloaderBreakpoint = null;
    private ?string $traceFile = null;

    /** @var list<string> what each pending eval command is for, in the order they were sent */
    private array $pendingEvals = [];

    public ?XdebugTraceReader $traceReader = null;
    public bool $finished = false;

    public function __construct(
        private readonly DbgpConnection $connection,
        private readonly TraceStore $store,
        private readonly XdebugValue $values,
    ) {
    }

    public function handle(\SimpleXMLElement $message): void
    {
        if ('init' === $message->getName()) {
            $this->connection->send('feature_set', '-n max_data -v 0');
            $this->evaluate('request', "json_encode(['id' => \$_SERVER['HTTP_X_REQUEST_ID'] ?? null, 'traceparent' => \$_SERVER['HTTP_TRACEPARENT'] ?? null, 'service' => \$_SERVER['OTEL_SERVICE_NAME'] ?? (getenv('OTEL_SERVICE_NAME') ?: null), 'method' => \$_SERVER['REQUEST_METHOD'] ?? null, 'uri' => \$_SERVER['REQUEST_URI'] ?? null])");

            return;
        }

        match ((string) $message['command']) {
            'eval' => match (array_shift($this->pendingEvals)) {
                'request' => $this->start($this->evalResult($message)),
                'codeIndex' => $this->onCodeIndex($this->evalResult($message)),
                default => null,
            },
            'breakpoint_set' => $this->autoloaderBreakpoint ??= (string) $message['id'],
            'run' => $this->onBreak($message),
            default => null,
        };
    }

    public function requestId(): ?string
    {
        return $this->request['id'] ?? null;
    }

    private function start(array $request): void
    {
        $this->request = $request;

        if (null !== $request['method'] && !TraceStore::isValidId($request['id'] ?? '')) {
            $this->request['id'] = self::newRequestId();
            $this->evaluate('ignore', \sprintf('$_SERVER[\'HTTP_X_REQUEST_ID\'] = %s', var_export($this->request['id'], true)));
        }

        if (!$this->shouldRecord()) {
            $this->connection->send('detach');
            $this->finished = true;
            $this->request = null;

            return;
        }

        $traceparent = $this->request['traceparent'];
        unset($this->request['traceparent']);
        $this->request += TraceContext::fromHeaders($traceparent, $this->request['id']);
        $this->request['at'] = microtime(true);
        $this->store->start($this->request['id'], $this->request);

        $this->evaluate('codeIndex', $this->codeIndexCall(false));
    }

    private function onAutoloaderBreak(): void
    {
        $this->connection->send('breakpoint_remove', '-d '.$this->autoloaderBreakpoint);
        $this->evaluate('codeIndex', $this->codeIndexCall(false));
    }

    private function onCodeIndex(array $result): void
    {
        if (!$result && null === $this->autoloaderBreakpoint) {
            $this->connection->send('breakpoint_set', \sprintf('-t call -a %s -m loadClass', self::AUTOLOADER));
            $this->connection->send('run');

            return;
        }

        $this->traceFile ??= $result['traceFile'] ?? null;
        if (!isset($result['files'])) {
            $this->codeIndexCacheKey = $this->request['service'].'#'.$result['fingerprint'];
            if (!isset(self::$codeIndexes[$this->codeIndexCacheKey])) {
                $this->evaluate('codeIndex', $this->codeIndexCall(true));

                return;
            }
            $result = self::$codeIndexes[$this->codeIndexCacheKey];
        }

        self::$codeIndexes[$this->codeIndexCacheKey] = $result;
        if (null !== $this->traceFile) {
            $this->traceReader = new XdebugTraceReader($this->traceFile, $this->request['id'], $this->request['at'], $result, $this->store, $this->values);
        }

        foreach (self::HTTP_CLIENTS as $client) {
            $this->connection->send('breakpoint_set', \sprintf('-t call -a %s -m request', $client));
        }
        $this->connection->send('run');
    }

    private function shouldRecord(): bool
    {
        $id = $this->request['id'] ?? null;
        $path = parse_url($this->request['uri'] ?? '', \PHP_URL_PATH) ?? '';

        return \is_string($id) && TraceStore::isValidId($id)
            && !str_starts_with($path, '/_')
            && !str_contains($this->request['uri'], 'frankenphp_hot_reloading');
    }

    private function onBreak(\SimpleXMLElement $message): void
    {
        if ('break' !== (string) $message['status']) {
            $this->connection->send('detach');
            $this->finished = true;

            return;
        }

        if (null === $this->traceReader && null !== $this->autoloaderBreakpoint && null === $this->codeIndexCacheKey) {
            $this->onAutoloaderBreak();

            return;
        }

        $this->evaluate('ignore', \sprintf('$options[\'headers\'][\'traceparent\'] ??= %s', var_export(TraceContext::traceparent($this->request), true)));
        $this->connection->send('run');
    }

    private function codeIndexCall(bool $full): string
    {
        $closure = preg_replace('/^.*?return\s+(static function.*);\s*$/s', '$1', file_get_contents(__DIR__.'/Resources/code_index.php'));

        return \sprintf(
            "json_encode(['traceFile' => function_exists('xdebug_get_tracefile_name') ? (xdebug_get_tracefile_name() ?: null) : null] + json_decode((%s)(%s), true))",
            $closure,
            var_export($full, true),
        );
    }

    private function evaluate(string $purpose, string $code): void
    {
        $this->pendingEvals[] = $purpose;
        $this->connection->send('eval', '', $code);
    }

    private static function newRequestId(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = \chr(\ord($bytes[6]) & 0x0F | 0x40);
        $bytes[8] = \chr(\ord($bytes[8]) & 0x3F | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    private function evalResult(\SimpleXMLElement $message): array
    {
        return json_decode(base64_decode((string) $message->property), true) ?: [];
    }
}
