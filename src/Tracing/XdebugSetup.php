<?php

namespace Valtzu\DomainTracingBundle\Tracing;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The Xdebug settings that a request needs to be recorded, and what is wrong with them in the current request.
 */
final readonly class XdebugSetup
{
    private const MODES = ['trace', 'coverage'];

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/share/traces')]
        private string $traceDirectory,
    ) {
    }

    public function settings(): string
    {
        return implode("\n", [
            '[xdebug]',
            'xdebug.mode='.implode(',', self::MODES),
            'xdebug.start_with_request=yes',
            'xdebug.trace_format=1',
            'xdebug.collect_return=1',
            'xdebug.output_dir='.$this->traceDirectory,
            'xdebug.trace_output_name=trace.%t.%r',
            'xdebug.var_display_max_depth=2',
            'xdebug.var_display_max_children=16',
            'xdebug.var_display_max_data=256',
            'auto_prepend_file='.self::prependFile(),
        ])."\n";
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
        if ('yes' !== ini_get('xdebug.start_with_request')) {
            $problems[] = \sprintf('xdebug.start_with_request is "%s", and it must be "yes".', ini_get('xdebug.start_with_request'));
        }
        if ('1' !== ini_get('xdebug.trace_format')) {
            $problems[] = 'xdebug.trace_format must be 1.';
        }
        if (!ini_get('xdebug.collect_return')) {
            $problems[] = 'xdebug.collect_return must be 1.';
        }

        $outputDirectory = (string) ini_get('xdebug.output_dir');
        if (realpath($outputDirectory) !== realpath($this->traceDirectory)) {
            $problems[] = \sprintf('xdebug.output_dir is "%s", and it must be "%s", where the bundle reads the traces.', $outputDirectory, $this->traceDirectory);
        }
        if (!is_dir($outputDirectory)) {
            $problems[] = \sprintf('The trace directory "%s" (xdebug.output_dir) does not exist.', $outputDirectory);
        } elseif (!is_writable($outputDirectory)) {
            $problems[] = \sprintf('PHP cannot write to the trace directory "%s" (xdebug.output_dir).', $outputDirectory);
        } elseif (\in_array('trace', $modes, true) && !xdebug_get_tracefile_name()) {
            $problems[] = 'Xdebug did not write a trace file for this request.';
        }

        if (realpath((string) ini_get('auto_prepend_file')) !== self::prependFile()) {
            $problems[] = \sprintf('auto_prepend_file must be "%s", which starts the record of the request.', self::prependFile());
        }

        return $problems;
    }

    private static function prependFile(): string
    {
        return realpath(__DIR__.'/../../resources/xdebug-prepend.php');
    }
}
