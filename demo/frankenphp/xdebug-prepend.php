<?php

// Limits the Xdebug trace and the code coverage to the application, from the PSR-4 autoload section of the nearest
// composer.json. The lines that ran are written next to the trace file when the request ends.
(static function (): void {
    if (!\function_exists('xdebug_set_filter')) {
        return;
    }

    for ($directory = getcwd(); !is_file($directory.'/composer.json') && \dirname($directory) !== $directory; $directory = \dirname($directory)) {
    }
    $composer = json_decode((string) @file_get_contents($directory.'/composer.json'), true);
    $autoload = $composer['autoload']['psr-4'] ?? [];
    if (!$autoload) {
        return;
    }

    xdebug_set_filter(XDEBUG_FILTER_TRACING, XDEBUG_NAMESPACE_INCLUDE, array_keys($autoload));

    if (!\function_exists('xdebug_start_code_coverage')) {
        return;
    }

    $paths = [];
    foreach ($autoload as $sourceDirectories) {
        foreach ((array) $sourceDirectories as $sourceDirectory) {
            if ($path = realpath($directory.'/'.$sourceDirectory)) {
                $paths[] = $path.'/';
            }
        }
    }
    xdebug_set_filter(XDEBUG_FILTER_CODE_COVERAGE, XDEBUG_PATH_INCLUDE, $paths);
    xdebug_start_code_coverage();

    register_shutdown_function(static function (): void {
        if ($traceFile = xdebug_get_tracefile_name()) {
            file_put_contents($traceFile.'.lines.json', json_encode(array_map(array_keys(...), xdebug_get_code_coverage())));
        }
    });
})();
