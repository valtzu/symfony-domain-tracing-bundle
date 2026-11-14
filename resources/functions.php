<?php

namespace Valtzu\DomainTracingBundle\Prepend;

/**
 * The W3C Trace Context (https://www.w3.org/TR/trace-context/) of the current request, which is one span: it joins
 * the trace of the caller when a traceparent header arrives. A console command is recorded only with --profile, like
 * its profile. Without OTEL_SERVICE_NAME, the service is named after the project directory, the parent of the
 * directory of the entry script (public/index.php or bin/console).
 *
 * Loaded by the prepend file before the autoloader, so it uses no class.
 *
 * @return array{traceId: string, spanId: string, parentSpanId: string|null, service: string|null, method: string|null, uri: string|null}|null null for the requests that are never recorded
 */
function request_context(array $server): ?array
{
    $method = $server['REQUEST_METHOD'] ?? null;
    $uri = $server['REQUEST_URI'] ?? null;
    if (null !== $uri && preg_match('{^/_(tracing|profiler|wdt)(/|\?|$)}', $uri)) {
        return null;
    }
    if (null === $method && \is_array($argv = $server['argv'] ?? null)) {
        // A console command has a profile, and so a trace, only with --profile
        if (!\in_array('--profile', $argv, true)) {
            return null;
        }
        $binary = isset($server['SYMFONY_CLI_BINARY_NAME']) ? $server['SYMFONY_CLI_BINARY_NAME'].' console' : $argv[0];
        [$method, $uri] = ['CLI', implode(' ', [$binary, ...\array_slice($argv, 1)])];
    }

    $parent = preg_match('/^00-([0-9a-f]{32})-([0-9a-f]{16})-[0-9a-f]{2}$/', $server['HTTP_TRACEPARENT'] ?? '', $match) ? $match : null;
    $script = $server['SCRIPT_FILENAME'] ?? '';
    $project = basename(\dirname(realpath($script) ?: $script, 2));

    return [
        'traceId' => $parent[1] ?? bin2hex(random_bytes(16)),
        'spanId' => bin2hex(random_bytes(8)),
        'parentSpanId' => $parent[2] ?? null,
        'service' => $server['OTEL_SERVICE_NAME'] ?? (getenv('OTEL_SERVICE_NAME') ?: ('' === $project || '.' === $project ? null : $project)),
        'method' => $method,
        'uri' => $uri,
    ];
}

/**
 * The application classes of the project in $root, from the PSR-4 autoload section of its composer.json, with the
 * methods of each class. The fingerprint changes when a file is added, removed or modified. The full index needs the
 * autoloader of the application, so it is built at the end of the request.
 *
 * @return array{fingerprint: string, root?: string, namespaces?: list<string>, files?: array<string, array>}
 */
function code_index(string $root, bool $full): array
{
    $composer = json_decode((string) @file_get_contents($root.'/composer.json'), true) ?: [];

    $files = [];
    foreach ($composer['autoload']['psr-4'] ?? [] as $prefix => $directories) {
        foreach ((array) $directories as $directory) {
            if (!$directory = realpath($root.'/'.$directory)) {
                continue;
            }
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ('php' === $file->getExtension()) {
                    $class = $prefix.strtr(substr($file->getPathname(), \strlen($directory) + 1, -4), '/', '\\');
                    $files[$file->getPathname()] = ['class' => $class, 'modified' => $file->getMTime()];
                }
            }
        }
    }
    ksort($files);
    $fingerprint = md5(json_encode($files));

    if (!$full) {
        return ['fingerprint' => $fingerprint];
    }

    foreach ($files as $path => &$file) {
        $source = file_get_contents($path);
        $file['blob'] = sha1('blob '.\strlen($source)."\0".$source);
        $file['methods'] = [];
        $file['entrypoint'] = false;
        $file['closureLines'] = [];

        $tokens = \PhpToken::tokenize($source);
        foreach ($tokens as $i => $token) {
            $next = $i;
            while (($nextToken = $tokens[++$next] ?? null) && ($nextToken->isIgnorable() || '&' === $nextToken->text)) {
            }
            if ($token->is(\T_FN) || $token->is(\T_FUNCTION) && '(' === $nextToken?->text) {
                $file['closureLines'][$token->line] = $token->line;
            }
        }
        $file['closureLines'] = array_values($file['closureLines']);

        if (!class_exists($file['class']) && !interface_exists($file['class']) && !trait_exists($file['class'])) {
            continue;
        }
        $class = new \ReflectionClass($file['class']);
        $file['entrypoint'] = (bool) ($class->getAttributes('Symfony\Component\Console\Attribute\AsCommand') ?: $class->getAttributes('Symfony\Component\Routing\Attribute\Route'));
        foreach ($class->getMethods() as $method) {
            if ($method->getFileName() === $path) {
                $file['methods'][$method->getName()] = [$method->getStartLine(), $method->getEndLine(), array_map(static fn ($parameter) => $parameter->getName(), $method->getParameters())];
                $file['entrypoint'] = $file['entrypoint'] || $method->getAttributes('Symfony\Component\Routing\Attribute\Route');
            }
        }
    }

    return ['fingerprint' => $fingerprint, 'root' => $root, 'namespaces' => array_keys($composer['autoload']['psr-4'] ?? []), 'files' => $files];
}

/**
 * Stops recording the request. Stopping the trace would make Xdebug start a new one at the next function call, when
 * xdebug.start_with_request is "yes", so the trace goes on with a filter that matches no function, into a file that
 * is removed.
 */
function discard_trace(string $traceFile): void
{
    xdebug_set_filter(\XDEBUG_FILTER_TRACING, \XDEBUG_NAMESPACE_INCLUDE, ['DomainTracingBundle\Discarded']);
    @unlink($traceFile);
    @unlink($traceFile.'.lines.json');
    if (xdebug_code_coverage_started()) {
        xdebug_stop_code_coverage(false);
    }
}
