<?php

namespace App\Tracing;

use App\Ai\SecretRedactor;


/**
 * Records every executed line of the application code of one request, through a DBGp connection from PHP Debugger.
 *
 * The session reads the code through the connection too, so one recorder can serve services whose code it does not
 * have. It does so when the process first loads a class, because the autoloader of the application is ready then and
 * no application code has run yet. Then it sets a call breakpoint on each method of the application: inside a method it steps over line by line. When
 * a step lands outside the application code, the session steps out if application code called it, such as an
 * autoloader, and otherwise resumes until the next call breakpoint. A breakpoint on every line would make the engine
 * check the whole list on each statement, which costs about 0.5 s per request. Stepping over does not enter closures,
 * so each line that declares a closure gets a line breakpoint too.
 *
 * A return breakpoint on each method tells when the method returns, so that its span ends there even when code outside
 * the application runs next. The engine reports a return breakpoint with the position of the caller, while the stack
 * still shows the method that returns.
 *
 * When the request has no X-Request-Id header, the session gives it one, so the page can find its trace. The session
 * also sends the trace context to the services that the request calls: when the request is about to make an
 * HTTP request with Symfony HttpClient, it adds a traceparent header to the options of that request. The services need
 * no code or configuration for it.
 *
 * A trace refers to code by git blob ID and line, without source code. Each method call records its arguments and its
 * return value, limited in depth and size, without secrets.
 *
 * Recording starts when the entrypoint (a controller or a command) runs its first method other than the constructor.
 * The framework code, the constructor and the services built for the action arguments are not part of the flow.
 */
final class TraceSession
{
    /** @var array<string, array> code index by service and fingerprint, shared by the sessions */
    private static array $codeIndexes = [];

    private const AUTOLOADER = 'Composer\\Autoload\\ClassLoader';
    private const HTTP_CLIENTS = [
        'Symfony\\Component\\HttpClient\\CurlHttpClient',
        'Symfony\\Component\\HttpClient\\NativeHttpClient',
        'Symfony\\Component\\HttpClient\\AmpHttpClient',
    ];

    private ?array $request = null;
    private ?array $codeIndex = null;
    private ?string $codeIndexCacheKey = null;
    private ?string $autoloaderBreakpoint = null;
    private ?int $lastStepDepth = null;
    private int $depth = 0;
    private bool $recording = false;
    private float $stoppedSince;
    private ?string $breakPosition = null;

    /** @var list<string> what each pending eval command is for, in the order they were sent */
    private array $pendingEvals = [];

    /** @var array<string, int> index in the file table of the request, by path */
    private array $files = [];

    /** @var array<string, int> index in the function table of the request, by function */
    private array $functions = [];

    /** @var array<int, string> the function running at each call depth */
    private array $frames = [];

    /** @var list<array{int, list<string>}> call depth and parameter names of the pending "context_get" commands */
    private array $pendingArguments = [];

    private ?\SimpleXMLElement $returnValue = null;

    public bool $finished = false;

    public function __construct(
        private readonly DbgpConnection $connection,
        private readonly TraceStore $store,
        private readonly TraceClock $clock,
        private readonly DbgpValue $values,
        private readonly int $stepDelayMs = 0,
    ) {
        $this->stoppedSince = microtime(true);
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
            'run', 'step_over', 'step_out' => null === $this->codeIndex ? $this->onAutoloaderBreak($message) : $this->onBreak($message),
            'stack_depth' => $this->depth = (int) $message['depth'],
            'stack_get' => $this->onFrame($message->stack[0]),
            'context_get' => $this->onArguments($message),
            default => null,
        };
    }

    public function abort(): void
    {
        if (!$this->finished && null !== $this->request) {
            $this->end();
        }
        $this->finished = true;
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
        $this->store->start($this->request['id'], $this->request + ['at' => $this->clock->now($this->request['traceId'])]);

        $this->connection->send('breakpoint_set', \sprintf('-t call -a %s -m loadClass', self::AUTOLOADER));
        $this->resume('run');
    }

    private function onAutoloaderBreak(\SimpleXMLElement $message): void
    {
        if ('break' !== (string) $message['status']) {
            $this->onBreak($message);

            return;
        }

        $this->stoppedSince = microtime(true);
        $this->connection->send('breakpoint_remove', '-d '.$this->autoloaderBreakpoint);
        $this->evaluate('codeIndex', $this->codeIndexCall(false));
    }

    private function onCodeIndex(array $result): void
    {
        if (!isset($result['files'])) {
            $this->codeIndexCacheKey = $this->request['service'].'#'.$result['fingerprint'];
            if (!isset(self::$codeIndexes[$this->codeIndexCacheKey])) {
                $this->evaluate('codeIndex', $this->codeIndexCall(true));

                return;
            }
            $result = self::$codeIndexes[$this->codeIndexCacheKey];
        }

        $this->setBreakpoints(self::$codeIndexes[$this->codeIndexCacheKey] = $result);
    }

    private function setBreakpoints(array $codeIndex): void
    {
        $this->codeIndex = $codeIndex;

        $this->connection->send('feature_set', '-n max_data -v 256');
        $this->connection->send('feature_set', '-n max_depth -v 2');
        $this->connection->send('feature_set', '-n max_children -v 16');
        $this->connection->send('feature_set', '-n breakpoint_include_return_value -v 1');
        foreach (self::HTTP_CLIENTS as $client) {
            $this->connection->send('breakpoint_set', \sprintf('-t call -a %s -m request', $client));
        }
        foreach ($codeIndex['files'] as $path => $file) {
            foreach ($file['methods'] as $method => $lines) {
                $this->connection->send('breakpoint_set', \sprintf('-t call -a %s -m %s', $file['class'], $method));
                $this->connection->send('breakpoint_set', \sprintf('-t return -a %s -m %s', $file['class'], $method));
            }
            foreach ($file['closureLines'] as $line) {
                $this->connection->send('breakpoint_set', \sprintf('-t line -f file://%s -n %d', $path, $line));
            }
        }
        $this->resume('run');
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
        $this->stoppedSince = microtime(true);

        if ('break' !== (string) $message['status']) {
            $this->end();
            $this->connection->send('detach');
            $this->finished = true;

            return;
        }

        $engine = $message->children('https://xdebug.org/dbgp/xdebug');
        $this->returnValue = $engine->return_value?->children('urn:debugger_protocol_v1')->property[0] ?? null;
        $position = $engine->message?->attributes();
        $this->breakPosition = $position ? $position['filename'].':'.$position['lineno'] : null;
        $this->connection->send('stack_depth');
        $this->connection->send('stack_get', '-d 0');
    }

    private function onFrame(\SimpleXMLElement $frame): void
    {
        $path = substr((string) $frame['filename'], \strlen('file://'));
        $function = (string) $frame['where'];
        $file = $this->codeIndex['files'][$path] ?? null;

        if (\in_array(strstr($function, '->', true), self::HTTP_CLIENTS, true) && str_ends_with($function, '->request')) {
            $this->evaluate('ignore', \sprintf('$options[\'headers\'][\'traceparent\'] ??= %s', var_export(TraceContext::traceparent($this->request), true)));
        }

        if ($this->recording && null !== $file && $this->breakPosition !== $frame['filename'].':'.$frame['lineno']) {
            $this->store->addExit($this->request['id'], $this->depth, $this->clock->now($this->request['traceId'], $this->stoppedSince), null !== $this->returnValue ? ['return' => $this->values->convert($this->returnValue, $this->codeIndex['namespaces'])] : []);
            unset($this->frames[$this->depth]);
            $this->lastStepDepth = $this->depth - 1;
            $this->resume('step_over');

            return;
        }

        $this->recording = $this->recording || null !== $file && $file['entrypoint'] && $function !== $file['class'].'->__construct';

        if (!$this->recording) {
            $this->resume('run');

            return;
        }

        if (null === $file) {
            $calledFromApplicationCode = $this->depth > ($this->lastStepDepth ?? \PHP_INT_MAX);
            if (!$calledFromApplicationCode && null !== $this->lastStepDepth) {
                $this->store->addExit($this->request['id'], $this->depth, $this->clock->now($this->request['traceId'], $this->stoppedSince));
                $this->lastStepDepth = null;
            }
            $this->resume($calledFromApplicationCode ? 'step_out' : 'run');

            return;
        }

        if (str_starts_with($function, $file['class'].'->') || str_starts_with($function, $file['class'].'::')) {
            $this->record($path, $file, (int) $frame['lineno'], $function);
            $this->captureArgumentsOnEntry($function, $file['methods'][substr($function, \strlen($file['class']) + 2)][2] ?? []);
        }
        $this->resume('step_over');
    }

    private function resume(string $command): void
    {
        if ($this->stepDelayMs) {
            usleep($this->stepDelayMs * 1000);
        }
        $this->clock->addStopped($this->request['traceId'], microtime(true) - $this->stoppedSince);
        $this->connection->send($command);
    }

    private function record(string $path, array $file, int $line, string $function): void
    {
        $at = $this->clock->now($this->request['traceId'], $this->stoppedSince);

        if (!isset($this->files[$path])) {
            $this->files[$path] = \count($this->files);
            $this->store->describeFile($this->request['id'], [
                'file' => $this->files[$path],
                'blob' => $file['blob'],
                'path' => substr($path, \strlen($this->codeIndex['root']) + 1),
                'at' => $at,
            ]);
        }

        if (!isset($this->functions[$function])) {
            $this->functions[$function] = \count($this->functions);
            [$startLine, $endLine] = $file['methods'][substr($function, \strlen($file['class']) + 2)] ?? [null, null];
            $this->store->describeFunction($this->request['id'], [
                'fn' => $this->functions[$function],
                'function' => $function,
                'file' => $this->files[$path],
                'line' => $startLine,
                'endLine' => $endLine,
                'at' => $at,
            ]);
        }

        $this->lastStepDepth = $this->depth;
        $this->store->addStep($this->request['id'], $this->functions[$function], $line, $this->depth, $at);
    }

    /**
     * @param list<string> $parameters
     */
    private function captureArgumentsOnEntry(string $function, array $parameters): void
    {
        if (($this->frames[$this->depth] ?? null) === $function) {
            return;
        }

        foreach (array_keys($this->frames) as $depth) {
            if ($depth >= $this->depth) {
                unset($this->frames[$depth]);
            }
        }
        $this->frames[$this->depth] = $function;

        if ($parameters) {
            $this->pendingArguments[] = [$this->depth, $parameters];
            $this->connection->send('context_get', '-d 0');
        }
    }

    private function onArguments(\SimpleXMLElement $message): void
    {
        [$depth, $parameters] = array_shift($this->pendingArguments);
        $arguments = [];
        foreach ($message->children('urn:debugger_protocol_v1')->property as $property) {
            $name = ltrim((string) $property->attributes()['name'], '$');
            if (\in_array($name, $parameters, true)) {
                $arguments[$name] = SecretRedactor::isSecretName($name) ? SecretRedactor::PLACEHOLDER : $this->values->convert($property, $this->codeIndex['namespaces']);
            }
        }

        $this->store->addArguments($this->request['id'], $depth, $arguments, $this->clock->now($this->request['traceId'], $this->stoppedSince));
    }

    private function end(): void
    {
        $this->store->end($this->request['id'], $this->clock->now($this->request['traceId']));
    }

    private function codeIndexCall(bool $full): string
    {
        $closure = preg_replace('/^.*?return\s+(static function.*);\s*$/s', '$1', file_get_contents(__DIR__.'/Resources/code_index.php'));

        return \sprintf('(%s)(%s)', $closure, var_export($full, true));
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
