<?php

namespace App\Tracing;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Records every executed line of the source directory.
 *
 * A breakpoint on every line makes the engine check the whole list on each statement, which costs about 0.5 s per
 * request. A call breakpoint on each method of the source directory is enough: inside a method the session steps over
 * line by line. When a step lands outside the source directory, the session steps out if traced code called it, such
 * as an autoloader, and otherwise resumes until the next call breakpoint. Stepping over does not enter closures, so
 * each line that declares a closure gets a line breakpoint too.
 *
 * Recording starts when the entrypoint (a controller or a command) runs its first method other than the constructor.
 * The framework code, the constructor and the services built for the action arguments are not part of the flow.
 */
final class TraceSession
{
    private const NAMESPACE = 'App\\';

    private ?array $request = null;
    private ?int $lastStepDepth = null;
    private int $depth = 0;
    private float $startedAt;

    /** @var array<class-string, true|null> */
    private array $entrypoints = [];
    private bool $recording = false;

    /** @var array<string, list<string>> */
    private array $sources = [];

    public bool $finished = false;

    public function __construct(
        private readonly DbgpConnection $connection,
        private readonly string $sourceDir,
        private readonly string $excludedDir,
        private readonly TraceStore $store,
        private readonly int $stepDelayMs = 0,
    ) {
        $this->startedAt = microtime(true);
    }

    public function handle(\SimpleXMLElement $message): void
    {
        if ('init' === $message->getName()) {
            $this->connection->send('eval', '', "json_encode(['id' => \$_SERVER['HTTP_X_REQUEST_ID'] ?? null, 'traceparent' => \$_SERVER['HTTP_TRACEPARENT'] ?? null, 'service' => \$_SERVER['OTEL_SERVICE_NAME'] ?? (getenv('OTEL_SERVICE_NAME') ?: null), 'method' => \$_SERVER['REQUEST_METHOD'] ?? null, 'uri' => \$_SERVER['REQUEST_URI'] ?? null])");

            return;
        }

        match ((string) $message['command']) {
            'eval' => $this->start($message),
            'run', 'step_over', 'step_out' => $this->onBreak($message),
            'stack_depth' => $this->depth = (int) $message['depth'],
            'stack_get' => $this->onFrame($message->stack[0]),
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

    private function start(\SimpleXMLElement $message): void
    {
        $this->request = json_decode(base64_decode((string) $message->property), true) ?: null;

        if (!$this->shouldRecord()) {
            $this->request = null;
            $this->connection->send('detach');
            $this->finished = true;

            return;
        }

        $traceparent = $this->request['traceparent'];
        unset($this->request['traceparent']);
        $this->store->start($this->request['id'], $this->request + TraceContext::fromHeaders($traceparent, $this->request['id']));

        foreach ($this->sourceFiles() as $path) {
            foreach ($this->methods($path) as [$class, $method]) {
                $this->entrypoints[$class] ??= $this->isEntrypoint($class) ?: null;
                $this->connection->send('breakpoint_set', \sprintf('-t call -a %s -m %s', $class, $method));
            }
            foreach ($this->closureLines($path) as $line) {
                $this->connection->send('breakpoint_set', \sprintf('-t line -f file://%s -n %d', $path, $line));
            }
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
            $this->end();
            $this->connection->send('detach');
            $this->finished = true;

            return;
        }

        $this->connection->send('stack_depth');
        $this->connection->send('stack_get', '-d 0');
    }

    private function onFrame(\SimpleXMLElement $frame): void
    {
        $path = substr((string) $frame['filename'], \strlen('file://'));
        $function = (string) $frame['where'];

        $this->recording = $this->recording || $this->isTraced($path) && $this->isEntrypointAction($function);

        if (!$this->recording) {
            $this->connection->send('run');

            return;
        }

        if (!$this->isTraced($path)) {
            $calledFromTracedCode = $this->depth > ($this->lastStepDepth ?? \PHP_INT_MAX);
            $this->connection->send($calledFromTracedCode ? 'step_out' : 'run');

            return;
        }

        if (str_starts_with($function, self::NAMESPACE)) {
            $this->record($path, (int) $frame['lineno'], $function);
        }
        $this->connection->send('step_over');
    }

    private function isEntrypointAction(string $function): bool
    {
        $class = strstr($function, '->', true) ?: strstr($function, '::', true);

        return ($this->entrypoints[$class] ?? false) && $function !== $class.'->__construct';
    }

    private function isEntrypoint(string $class): bool
    {
        $reflection = new \ReflectionClass($class);
        if ($reflection->getAttributes(AsCommand::class) || $reflection->getAttributes(Route::class)) {
            return true;
        }

        foreach ($reflection->getMethods() as $method) {
            if ($method->getAttributes(Route::class)) {
                return true;
            }
        }

        return false;
    }

    private function isTraced(string $path): bool
    {
        return str_starts_with($path, $this->sourceDir.'/') && !str_starts_with($path, $this->excludedDir.'/');
    }

    private function record(string $path, int $line, string $function): void
    {
        $this->lastStepDepth = $this->depth;
        $this->store->addStep($this->request['id'], [
            'file' => substr($path, \strlen(\dirname($this->sourceDir)) + 1),
            'line' => $line,
            'function' => $function,
            'depth' => $this->depth,
            'code' => trim($this->sourceLine($path, $line)),
        ]);
        if ($this->stepDelayMs) {
            usleep($this->stepDelayMs * 1000);
        }
    }

    private function end(): void
    {
        $this->store->end($this->request['id'], (int) round((microtime(true) - $this->startedAt) * 1000));
    }

    /**
     * @return iterable<string>
     */
    private function sourceFiles(): iterable
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->sourceDir, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ('php' === $file->getExtension() && $this->isTraced($file->getPathname())) {
                yield $file->getPathname();
            }
        }
    }

    /**
     * @return iterable<array{class-string, string}>
     */
    private function methods(string $path): iterable
    {
        $class = self::NAMESPACE.strtr(substr($path, \strlen($this->sourceDir) + 1, -4), '/', '\\');
        if (!class_exists($class) && !interface_exists($class) && !trait_exists($class)) {
            return;
        }

        foreach ((new \ReflectionClass($class))->getMethods() as $method) {
            if ($method->getFileName() === $path) {
                yield [$class, $method->getName()];
            }
        }
    }

    /**
     * @return list<int>
     */
    private function closureLines(string $path): array
    {
        $lines = [];
        $tokens = \PhpToken::tokenize(file_get_contents($path));
        foreach ($tokens as $i => $token) {
            if ($token->is(\T_FN) || $token->is(\T_FUNCTION) && '(' === $this->nextSignificantToken($tokens, $i)?->text) {
                $lines[$token->line] = $token->line;
            }
        }

        return array_values($lines);
    }

    /**
     * @param list<\PhpToken> $tokens
     */
    private function nextSignificantToken(array $tokens, int $index): ?\PhpToken
    {
        while (($token = $tokens[++$index] ?? null) && ($token->isIgnorable() || '&' === $token->text)) {
        }

        return $token;
    }

    private function sourceLine(string $path, int $line): string
    {
        $this->sources[$path] ??= file($path, \FILE_IGNORE_NEW_LINES) ?: [];

        return $this->sources[$path][$line - 1] ?? '';
    }
}
