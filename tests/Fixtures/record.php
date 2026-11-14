<?php

declare(strict_types=1);


// Records one request in a separate PHP process, because Xdebug starts a trace only in the trace mode, which PHPUnit
// does not run in.

use Valtzu\DomainTracingBundle\Storage\TraceStore;
use Valtzu\DomainTracingBundle\Tracing\ExcludedUris;
use Valtzu\DomainTracingBundle\Xdebug\Recorder;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/project/src/Greeter.php';

[, $traceDirectory, $uri] = $argv;
$recorder = new Recorder(__DIR__.'/project', new ExcludedUris(['^/health$']), new TraceStore($traceDirectory));
// The time between the start of the script and the start of the recording, as in a worker process
usleep(300_000);
$spanId = $recorder->start(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri, 'SCRIPT_FILENAME' => __DIR__.'/project/public/index.php']);
$greeting = (new Acme\Shop\Greeter())->greet('Ada');
$recorder->recordException(new RuntimeException('Greeting failed'));
$recorder->respond();
// The work after the response is sent, such as the kernel.terminate listeners
usleep(200_000);
$recorder->stop(500);

echo json_encode([
    'spanId' => $spanId,
    'traceparent' => $_SERVER['DOMAIN_TRACING_TRACEPARENT'] ?? null,
    'collectReturn' => ini_get('xdebug.collect_return'),
    'traceFile' => xdebug_get_tracefile_name(),
]);
