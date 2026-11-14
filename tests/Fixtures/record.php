<?php

declare(strict_types=1);


// Records one request in a separate PHP process, because Xdebug starts a trace only in the trace mode, which PHPUnit
// does not run in.

use Valtzu\DomainTracingBundle\Storage\TraceStore;
use Valtzu\DomainTracingBundle\Xdebug\Recorder;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/project/src/Greeter.php';

/** @var list<string> $argv */
[, $traceDirectory, $uri] = $argv;
$option = $argv[3] ?? null;
// As in debug mode, where Symfony turns warnings into exceptions
set_error_handler(static fn (int $type, string $message) => throw new ErrorException($message, 0, $type));
$recorder = new Recorder(__DIR__.'/project', new TraceStore($traceDirectory), ['Acme\Shop\Greeter']);
// The time between the start of the script and the start of the recording, as in a worker process
usleep(300_000);
$spanId = $recorder->start(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri, 'SCRIPT_FILENAME' => __DIR__.'/project/public/index.php', 'REQUEST_TIME_FLOAT' => $_SERVER['REQUEST_TIME_FLOAT']]);
if ('controller' === $option) {
    $recorder->willCall('Acme\Shop\Greeter');
}
$recordedBeforeTheEnd = null !== $spanId && is_file($traceDirectory.'/'.$spanId.'.jsonl');
if ('no-entrypoint' !== $option) {
    $greeting = (new Acme\Shop\Greeter())->greet('Ada');
}
$recorder->recordException(new RuntimeException('Greeting failed'));
$recorder->respond();
// The work after the response is sent, such as the kernel.terminate listeners
usleep(200_000);
if ('remove-directory' === $option) {
    (new Symfony\Component\Filesystem\Filesystem())->remove($traceDirectory);
}
$error = null;
try {
    $recorder->stop(500);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

echo json_encode([
    'spanId' => $spanId,
    'recordedBeforeTheEnd' => $recordedBeforeTheEnd,
    'traceparent' => $_SERVER['DOMAIN_TRACING_TRACEPARENT'] ?? null,
    'collectReturn' => ini_get('xdebug.collect_return'),
    'traceFile' => xdebug_get_tracefile_name(),
    'coverage' => xdebug_code_coverage_started(),
    'error' => $error,
]);
