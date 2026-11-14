<?php

namespace Valtzu\DomainTracingBundle\Xdebug;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Service\ResetInterface;
use Valtzu\DomainTracingBundle\Storage\TraceStore;
use Valtzu\DomainTracingBundle\Tracing\ExcludedUris;

/**
 * Records one request at a time: it starts an Xdebug trace of the application, from the PSR-4 autoload section of its
 * composer.json, into the trace store, with the code coverage, and writes the request record. When the request ends,
 * it writes the lines that ran and how the request ended, stops the trace, and writes the index of the application
 * code when this version of the code has none yet.
 *
 * The trace starts when the kernel handles the request, not with the PHP process, so that a worker process that handles
 * many requests records each of them, and so that the requests that are not recorded cost nothing. Only xdebug.mode
 * must be set: the other Xdebug settings that the trace needs are set while it runs, and restored after it.
 *
 * Xdebug runs one trace at a time. When Xdebug traces the request already, because of xdebug.start_with_request or a
 * trigger, the recorder leaves that trace as it is, and reads the request from it when it is in the format that the
 * recorder reads.
 */
final class Recorder implements ResetInterface
{
    private const int MAX_EXCEPTION_FUNCTIONS = 100;

    /** The Xdebug settings of the trace, which the trace files of the bundle and the value parser expect */
    private const array SETTINGS = [
        'xdebug.collect_return' => '1',
        'xdebug.var_display_max_depth' => '2',
        'xdebug.var_display_max_children' => '16',
        'xdebug.var_display_max_data' => '256',
    ];

    /** @var array{traceFile: string, spanId: string, codeIndex: string, ownTrace: bool, ownCoverage: bool, settings: array<string, string|false>}|null */
    private ?array $current = null;
    private ?array $exception = null;
    private bool $shutdownRegistered = false;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly ExcludedUris $excluded,
        private readonly TraceStore $store,
    ) {
    }

    /**
     * @param array<string, mixed> $server the server parameters of the request, or $_SERVER for a console command
     *
     * @return string|null the span ID of the request, or null when it is not recorded
     */
    public function start(array $server): ?string
    {
        if (null !== $this->current || !\function_exists('xdebug_info') || !\in_array('trace', xdebug_info('mode'), true)) {
            return null;
        }

        $context = RequestContext::fromServer($server);
        if (null === $context || 'CLI' !== $context['method'] && null !== $context['uri'] && $this->excluded->matches($context['uri'])) {
            return null;
        }

        $autoload = (json_decode((string) @file_get_contents($this->projectDir.'/composer.json'), true) ?: [])['autoload']['psr-4'] ?? [];
        $runningTrace = xdebug_get_tracefile_name();
        $traced = \is_string($runningTrace);
        if (!$autoload || $traced && !self::readsTheRunningTrace()) {
            return null;
        }

        $settings = [];
        foreach (self::SETTINGS as $name => $value) {
            $settings[$name] = ini_set($name, $value);
        }

        if (!$traced) {
            $this->store->createDirectory();
            xdebug_set_filter(\XDEBUG_FILTER_TRACING, \XDEBUG_NAMESPACE_INCLUDE, array_keys($autoload));
            $traceFile = xdebug_start_trace($this->store->directory().'/trace.'.$context['spanId'], \XDEBUG_TRACE_COMPUTERIZED);
        } else {
            $traceFile = $runningTrace;
        }
        if (!\is_string($traceFile)) {
            self::restore($settings);

            return null;
        }

        $codeIndex = CodeIndex::build($this->projectDir, false)['fingerprint'];
        $this->store->start($context['spanId'], $context + ['traceFile' => $traceFile, 'codeIndex' => $codeIndex]);
        $ownCoverage = \in_array('coverage', xdebug_info('mode'), true) && !xdebug_code_coverage_started();
        $this->current = ['traceFile' => $traceFile, 'spanId' => $context['spanId'], 'codeIndex' => $codeIndex, 'ownTrace' => !$traced, 'ownCoverage' => $ownCoverage, 'settings' => $settings];
        $this->exception = null;
        $_SERVER['DOMAIN_TRACING_TRACEPARENT'] = \sprintf('00-%s-%s-01', $context['traceId'], $context['spanId']);

        if ($ownCoverage) {
            xdebug_set_filter(\XDEBUG_FILTER_CODE_COVERAGE, \XDEBUG_PATH_INCLUDE, $this->sourceDirectories($autoload));
            xdebug_start_code_coverage();
        }

        // Ends the record when the request does not reach its end, such as after exit() or a fatal error
        if (!$this->shutdownRegistered) {
            register_shutdown_function(fn () => $this->stop(http_response_code() ?: null));
            $this->shutdownRegistered = true;
        }

        return $context['spanId'];
    }

    /**
     * Keeps the exception that the request did not handle, with the methods that were running when it was thrown,
     * innermost first, because Xdebug traces do not record exceptions.
     */
    public function recordException(\Throwable $exception): void
    {
        if (null === $this->current) {
            return;
        }

        $functions = [];
        foreach (\array_slice($exception->getTrace(), offset: 0, length: self::MAX_EXCEPTION_FUNCTIONS) as $frame) {
            if (isset($frame['class'], $frame['type'])) {
                $functions[] = $frame['class'].$frame['type'].$frame['function'];
            }
        }
        $this->exception = ['class' => $exception::class, 'message' => $exception->getMessage(), 'functions' => $functions];
    }

    public function stop(?int $status): void
    {
        if (null === $current = $this->current) {
            return;
        }
        $this->current = null;
        unset($_SERVER['DOMAIN_TRACING_TRACEPARENT']);

        if ($current['ownCoverage'] && xdebug_code_coverage_started()) {
            file_put_contents($this->store->linesPath($current['spanId']), json_encode(array_map(array_keys(...), xdebug_get_code_coverage())));
            xdebug_stop_code_coverage();
        }
        file_put_contents($this->store->outcomePath($current['spanId']), json_encode(['status' => $status, 'exception' => $this->exception], \JSON_UNESCAPED_SLASHES | \JSON_INVALID_UTF8_SUBSTITUTE));
        if ($current['ownTrace'] && xdebug_get_tracefile_name() === $current['traceFile']) {
            xdebug_stop_trace();
        }
        self::restore($current['settings']);

        $indexPath = $this->store->directory().'/codeindex-'.$current['codeIndex'].'.json';
        if (!is_file($indexPath)) {
            $temporary = $indexPath.'.'.getmypid();
            file_put_contents($temporary, json_encode(CodeIndex::build($this->projectDir, true), \JSON_UNESCAPED_SLASHES));
            rename($temporary, $indexPath);
        }
    }

    public function spanId(): ?string
    {
        return $this->current['spanId'] ?? null;
    }

    #[\Override]
    public function reset(): void
    {
        $this->stop(null);
    }

    /**
     * The recorder reads the trace format 1 (computerized) only. The format of a running trace cannot change.
     */
    public static function readsTheRunningTrace(): bool
    {
        return '1' === ini_get('xdebug.trace_format');
    }

    /**
     * @param array<string, string|false> $settings
     */
    private static function restore(array $settings): void
    {
        foreach ($settings as $name => $value) {
            if (false !== $value) {
                ini_set($name, $value);
            }
        }
    }

    /**
     * @param array<string, string|list<string>> $autoload
     *
     * @return list<string>
     */
    private function sourceDirectories(array $autoload): array
    {
        $paths = [];
        foreach ($autoload as $directories) {
            foreach ((array) $directories as $directory) {
                if ($path = realpath($this->projectDir.'/'.$directory)) {
                    $paths[] = $path.'/';
                }
            }
        }

        return $paths;
    }
}
