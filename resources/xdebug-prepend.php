<?php

// Starts the record of the request in the trace directory (xdebug.output_dir), next to the trace file that Xdebug
// writes, and limits the trace and the code coverage to the application, from the PSR-4 autoload section of the
// nearest composer.json. When the request ends, it writes the lines that ran next to the trace file, and the index of
// the application code when this version of the code has none yet.
(static function (): void {
    $modes = \function_exists('xdebug_info') ? xdebug_info('mode') : [];
    if (!\in_array('trace', $modes, true) || !$traceFile = xdebug_get_tracefile_name()) {
        return;
    }

    require_once __DIR__.'/functions.php';

    $context = Valtzu\DomainTracingBundle\Prepend\request_context($_SERVER);
    if (null === $context) {
        Valtzu\DomainTracingBundle\Prepend\discard_trace($traceFile);

        return;
    }

    $script = realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: getcwd();
    for ($root = \is_dir($script) ? $script : \dirname($script); !is_file($root.'/composer.json') && \dirname($root) !== $root; $root = \dirname($root)) {
    }
    $autoload = (json_decode((string) @file_get_contents($root.'/composer.json'), true) ?: [])['autoload']['psr-4'] ?? [];
    if (!$autoload) {
        return;
    }

    xdebug_set_filter(XDEBUG_FILTER_TRACING, XDEBUG_NAMESPACE_INCLUDE, array_keys($autoload));

    $directory = \dirname($traceFile);
    $fingerprint = Valtzu\DomainTracingBundle\Prepend\code_index($root, false)['fingerprint'];
    $_SERVER['DOMAIN_TRACING_SPAN_ID'] = $context['spanId'];
    $_SERVER['DOMAIN_TRACING_TRACEPARENT'] = \sprintf('00-%s-%s-01', $context['traceId'], $context['spanId']);
    $startedAt = microtime(true);
    $request = ['type' => 'request', 'startedAt' => $startedAt] + $context + ['traceFile' => $traceFile, 'codeIndex' => $fingerprint, 'at' => $startedAt];
    file_put_contents($directory.'/'.$context['spanId'].'.jsonl', json_encode($request, \JSON_UNESCAPED_SLASHES)."\n");
    file_put_contents($directory.'/trace-'.$context['traceId'].'.spans', $context['spanId']."\n", \FILE_APPEND);

    if (\in_array('coverage', $modes, true)) {
        $paths = [];
        foreach ($autoload as $sourceDirectories) {
            foreach ((array) $sourceDirectories as $sourceDirectory) {
                if ($path = realpath($root.'/'.$sourceDirectory)) {
                    $paths[] = $path.'/';
                }
            }
        }
        xdebug_set_filter(XDEBUG_FILTER_CODE_COVERAGE, XDEBUG_PATH_INCLUDE, $paths);
        xdebug_start_code_coverage();
    }

    register_shutdown_function(static function () use ($traceFile, $directory, $root, $fingerprint): void {
        if (xdebug_get_tracefile_name() !== $traceFile) {
            return;
        }
        if (xdebug_code_coverage_started()) {
            file_put_contents($traceFile.'.lines.json', json_encode(array_map(array_keys(...), xdebug_get_code_coverage())));
        }
        $indexPath = $directory.'/codeindex-'.$fingerprint.'.json';
        if (!is_file($indexPath)) {
            $temporary = $indexPath.'.'.getmypid();
            file_put_contents($temporary, json_encode(Valtzu\DomainTracingBundle\Prepend\code_index($root, true), \JSON_UNESCAPED_SLASHES));
            rename($temporary, $indexPath);
        }
    });
})();
