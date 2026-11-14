<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Xdebug;

/**
 * The Xdebug settings that a request needs to be recorded, and what is wrong with them in the current request. The
 * bundle writes its traces into its own directory, and does not use xdebug.output_dir.
 */
final readonly class XdebugSetup
{
    private const MODES = ['trace', 'coverage'];

    public function __construct(
        private string $traceDirectory,
    ) {
    }

    /**
     * The settings of the php.ini. The other Xdebug settings that a trace needs are set while it runs.
     */
    public function settings(): string
    {
        return "[xdebug]\nxdebug.mode=".implode(',', self::MODES)."\n";
    }

    /**
     * @return list<string>
     */
    public function problems(): array
    {
        if (!\extension_loaded('xdebug')) {
            return ['Xdebug is not loaded.'];
        }

        $problems = [];
        $modes = xdebug_info('mode');
        if ($missing = array_diff(self::MODES, $modes)) {
            $problems[] = \sprintf('xdebug.mode is "%s", and it must also contain %s.', implode(',', $modes), implode(' and ', $missing));
        }
        if (\in_array('trace', $modes, true) && \is_string(xdebug_get_tracefile_name()) && !Recorder::readsTheRunningTrace()) {
            $problems[] = \sprintf('Xdebug traces this request already, because of xdebug.start_with_request or a trigger, in the trace format %s. Xdebug runs one trace at a time, and the bundle reads the format 1 only: set xdebug.trace_format=1, or do not start the trace with the request.', ini_get('xdebug.trace_format'));
        }
        if (is_dir($this->traceDirectory) && !is_writable($this->traceDirectory)) {
            $problems[] = \sprintf('PHP cannot write to the trace directory "%s".', $this->traceDirectory);
        }

        return $problems;
    }
}
