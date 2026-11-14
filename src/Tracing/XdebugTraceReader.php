<?php

namespace Valtzu\DomainTracingBundle\Tracing;

/**
 * Follows the trace file that Xdebug writes for one request (trace format 1: tab-separated function entries, exits
 * and return values) and records the calls of application methods, from the first method of the entrypoint other than
 * its constructor. The framework code and the services built before that are not part of the flow. When the trace
 * ends, it records which lines of these methods ran, from the code coverage that the prepend file of the service
 * writes next to the trace file.
 *
 * @see https://xdebug.org/docs/trace#trace_format
 */
final class XdebugTraceReader
{
    private ?TraceFile $file = null;
    private string $buffer = '';
    private ?float $traceStartedAt = null;
    private float $lastTime = 0.0;
    private bool $recording = false;
    private bool $ended = false;

    /** @var array<string, array{path: string, file: array}> application classes by name */
    private array $classes = [];

    /** @var array<string, int> */
    private array $files = [];

    /** @var array<string, int> */
    private array $functions = [];

    /** @var array<int, int> call depth of the recorded calls, by function number */
    private array $recordedCalls = [];

    private ?array $pendingExit = null;

    public function __construct(
        private readonly string $path,
        private readonly string $requestId,
        private readonly float $requestStartedAt,
        private readonly array $codeIndex,
        private readonly TraceStore $store,
        private readonly XdebugValue $values,
    ) {
        foreach ($codeIndex['files'] as $path => $file) {
            $this->classes[$file['class']] = ['path' => $path, 'file' => $file];
        }
    }

    /**
     * Reads what was written since the last call.
     *
     * @return bool whether the trace has ended
     */
    public function read(): bool
    {
        if ($this->ended || !is_file($this->path)) {
            return $this->ended;
        }

        $this->file ??= new TraceFile($this->path);
        $lines = explode("\n", $this->buffer.$this->file->read());
        $this->buffer = array_pop($lines);

        foreach ($lines as $line) {
            $this->readLine($line);
        }

        return $this->ended;
    }

    /**
     * @return float|null when the trace ended, as a Unix timestamp
     */
    public function endedAt(): ?float
    {
        return null === $this->traceStartedAt ? null : $this->traceStartedAt + $this->lastTime;
    }

    private function readLine(string $line): void
    {
        if (str_starts_with($line, 'TRACE START')) {
            $this->traceStartedAt = (float) \DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', substr($line, 13, 26), new \DateTimeZone('UTC'))->format('U.u');

            return;
        }
        if (str_starts_with($line, 'TRACE END')) {
            $this->flushExit();
            $this->readExecutedLines();
            $this->ended = true;

            return;
        }

        $fields = explode("\t", $line);
        if (\count($fields) < 3 || null === $this->traceStartedAt) {
            return;
        }
        if ('' === $fields[0] && isset($fields[3]) && is_numeric($fields[3])) {
            $this->lastTime = (float) $fields[3];
        }

        match ($fields[2]) {
            '0' => $this->enter($fields),
            '1' => $this->exit($fields),
            'R' => $this->return($fields),
            default => null,
        };
    }

    private function enter(array $fields): void
    {
        $this->flushExit();
        [$depth, $number, , $time, , $function] = $fields;
        $this->lastTime = (float) $time;

        [$class, $method] = preg_split('/->|::/', $function, 2) + [1 => null];
        $definition = $this->classes[$class] ?? null;
        if (null === $definition || !isset($definition['file']['methods'][$method])) {
            return;
        }

        $this->recording = $this->recording || $definition['file']['entrypoint'] && '__construct' !== $method;
        if (!$this->recording) {
            return;
        }

        $at = $this->traceStartedAt + (float) $time;
        $this->describe($definition, $function, $method, $at);
        $this->recordedCalls[(int) $number] = (int) $depth;
        $this->store->addStep($this->requestId, $this->functions[$function], $definition['file']['methods'][$method][0], (int) $depth, (int) round(($at - $this->requestStartedAt) * 1e6));

        $parameters = $definition['file']['methods'][$method][2];
        $arguments = [];
        foreach (\array_slice($fields, 11) as $index => $text) {
            $arguments[$parameters[$index] ?? (string) $index] = $this->values->parse($text, $this->codeIndex['namespaces']);
        }
        if ($arguments) {
            $this->store->addArguments($this->requestId, (int) $depth, $arguments, $at);
        }
    }

    private function exit(array $fields): void
    {
        $this->flushExit();
        $this->lastTime = (float) $fields[3];
        if (isset($this->recordedCalls[(int) $fields[1]])) {
            $this->pendingExit = ['number' => (int) $fields[1], 'depth' => $this->recordedCalls[(int) $fields[1]], 'at' => $this->traceStartedAt + (float) $fields[3], 'details' => []];
        }
    }

    private function return(array $fields): void
    {
        if (null !== $this->pendingExit && $this->pendingExit['number'] === (int) $fields[1]) {
            $this->pendingExit['details'] = ['return' => $this->values->parse($fields[5] ?? '', $this->codeIndex['namespaces'])];
        }
        $this->flushExit();
    }

    private function flushExit(): void
    {
        if (null !== $exit = $this->pendingExit) {
            $this->pendingExit = null;
            unset($this->recordedCalls[$exit['number']]);
            $this->store->addExit($this->requestId, $exit['depth'], $exit['at'], $exit['details']);
        }
    }

    private function readExecutedLines(): void
    {
        $coverage = json_decode((string) @file_get_contents($this->path.'.lines.json'), true);
        foreach (\is_array($coverage) ? $coverage : [] as $path => $lines) {
            if (isset($this->files[$path]) && \is_array($lines)) {
                $this->store->addExecutedLines($this->requestId, $this->files[$path], array_map(intval(...), $lines), (float) $this->endedAt());
            }
        }
    }

    private function describe(array $definition, string $function, string $method, float $at): void
    {
        $path = $definition['path'];
        if (!isset($this->files[$path])) {
            $this->files[$path] = \count($this->files);
            $this->store->describeFile($this->requestId, [
                'file' => $this->files[$path],
                'blob' => $definition['file']['blob'],
                'path' => substr($path, \strlen($this->codeIndex['root']) + 1),
                'at' => $at,
            ]);
        }

        if (!isset($this->functions[$function])) {
            $this->functions[$function] = \count($this->functions);
            [$startLine, $endLine] = $definition['file']['methods'][$method];
            $this->store->describeFunction($this->requestId, [
                'fn' => $this->functions[$function],
                'function' => $function,
                'file' => $this->files[$path],
                'line' => $startLine,
                'endLine' => $endLine,
                'at' => $at,
            ]);
        }
    }
}
