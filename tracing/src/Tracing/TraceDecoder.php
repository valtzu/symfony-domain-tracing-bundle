<?php

namespace App\Tracing;

/**
 * Expands the compact records of a trace, which refer to code by file and function tables, into records with the
 * file path, the function name and the source line, read from the blob store.
 */
final class TraceDecoder
{
    /** @var array<string, array<int, array>> files by request and index */
    private array $files = [];

    /** @var array<string, array<int, array>> functions by request and index */
    private array $functions = [];

    public function __construct(
        private readonly BlobStore $blobs,
    ) {
    }

    /**
     * @return array|null null for the records that only describe a table entry
     */
    public function decode(array $record): ?array
    {
        $request = $record['request'];

        return match ($record['type']) {
            'file' => $this->addFile($request, $record),
            'function' => $this->addFunction($request, $record),
            'step' => $this->decodeStep($request, $record),
            'lines' => ['file' => $this->files[$request][$record['file']]['path'] ?? null] + $record,
            default => $record,
        };
    }

    private function addFile(string $request, array $record): null
    {
        $contents = $this->blobs->get($record['blob']);
        $this->files[$request][$record['file']] = $record + ['lines' => null === $contents ? [] : explode("\n", $contents)];

        return null;
    }

    private function addFunction(string $request, array $record): array
    {
        $file = $this->files[$request][$record['file']] ?? ['path' => null, 'lines' => []];
        $this->functions[$request][$record['fn']] = $record + ['path' => $file['path']];

        return [
            'request' => $request,
            'type' => 'function',
            'function' => $record['function'],
            'file' => $file['path'],
            'line' => $record['line'],
            'endLine' => $record['endLine'],
            'code' => null === $record['line'] ? '' : implode("\n", \array_slice($file['lines'], $record['line'] - 1, $record['endLine'] - $record['line'] + 1)),
            'at' => $record['at'],
        ];
    }

    private function decodeStep(string $request, array $record): array
    {
        $function = $this->functions[$request][$record['fn']];

        return [
            'request' => $request,
            'type' => 'step',
            'function' => $function['function'],
            'file' => $function['path'],
            'line' => $record['line'],
            'depth' => $record['depth'],
            'code' => trim($this->files[$request][$function['file']]['lines'][$record['line'] - 1] ?? ''),
            'at' => $record['at'],
        ];
    }
}
