<?php

// Traces only the namespaces of the application, from the PSR-4 autoload section of the nearest composer.json.
(static function (): void {
    for ($directory = getcwd(); !is_file($directory.'/composer.json') && \dirname($directory) !== $directory; $directory = \dirname($directory)) {
    }
    $composer = json_decode((string) @file_get_contents($directory.'/composer.json'), true);
    $namespaces = array_keys($composer['autoload']['psr-4'] ?? []);
    if ($namespaces && \function_exists('xdebug_set_filter')) {
        xdebug_set_filter(XDEBUG_FILTER_TRACING, XDEBUG_NAMESPACE_INCLUDE, $namespaces);
    }
})();
