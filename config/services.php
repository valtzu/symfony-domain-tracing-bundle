<?php

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\KernelEvents;
use Valtzu\DomainTracingBundle\Ai\ContextWriter;
use Valtzu\DomainTracingBundle\Ai\OperationNamer;
use Valtzu\DomainTracingBundle\Ai\ProjectSummary;
use Valtzu\DomainTracingBundle\Ai\RedactSecretsInputProcessor;
use Valtzu\DomainTracingBundle\Ai\SecretRedactor;
use Valtzu\DomainTracingBundle\Ai\SystemPrompts;
use Valtzu\DomainTracingBundle\Ai\TraceNarrator;
use Valtzu\DomainTracingBundle\CacheClearer\TraceCacheClearer;
use Valtzu\DomainTracingBundle\Command\ContextGenerateCommand;
use Valtzu\DomainTracingBundle\Command\TraceCommand;
use Valtzu\DomainTracingBundle\Controller\TraceController;
use Valtzu\DomainTracingBundle\DataCollector\TraceDataCollector;
use Valtzu\DomainTracingBundle\EventListener\RecordingListener;
use Valtzu\DomainTracingBundle\HttpClient\TraceparentHttpClient;
use Valtzu\DomainTracingBundle\Mcp\TraceMcpServer;
use Valtzu\DomainTracingBundle\Storage\BlobStore;
use Valtzu\DomainTracingBundle\Storage\TraceStore;
use Valtzu\DomainTracingBundle\Tracing\TraceDescriber;
use Valtzu\DomainTracingBundle\Tracing\TracePoller;
use Valtzu\DomainTracingBundle\Tracing\TraceStream;
use Valtzu\DomainTracingBundle\Xdebug\Recorder;
use Valtzu\DomainTracingBundle\Xdebug\TraceIngester;
use Valtzu\DomainTracingBundle\Xdebug\XdebugSetup;
use Valtzu\DomainTracingBundle\Xdebug\XdebugValue;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('domain_tracing.trace_store', TraceStore::class)
        ->args(['%kernel.project_dir%/var/share/traces']);

    $services->set('domain_tracing.blob_store', BlobStore::class)
        ->args(['%kernel.project_dir%/var/blobs', '%env(default:kernel.project_dir:TRACE_GIT_REPOSITORIES)%']);

    $services->set('domain_tracing.secret_redactor', SecretRedactor::class)
        ->args([null, service('secrets.vault')->nullOnInvalid()]);

    $services->set('domain_tracing.xdebug_value', XdebugValue::class)
        ->args([service('domain_tracing.secret_redactor')]);

    $services->set('domain_tracing.xdebug_setup', XdebugSetup::class)
        ->args(['%kernel.project_dir%/var/share/traces']);

    $services->set('domain_tracing.recorder', Recorder::class)
        ->args(['%kernel.project_dir%', service('domain_tracing.trace_store')])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set('domain_tracing.recording_listener', RecordingListener::class)
        ->args([service('domain_tracing.recorder')])
        ->tag('kernel.event_listener', ['event' => KernelEvents::REQUEST, 'method' => 'onRequest', 'priority' => 4096])
        ->tag('kernel.event_listener', ['event' => KernelEvents::CONTROLLER, 'method' => 'onController', 'priority' => -4096])
        ->tag('kernel.event_listener', ['event' => KernelEvents::EXCEPTION, 'method' => 'onException', 'priority' => 4096])
        ->tag('kernel.event_listener', ['event' => KernelEvents::RESPONSE, 'method' => 'onResponse', 'priority' => -4096])
        ->tag('kernel.event_listener', ['event' => KernelEvents::TERMINATE, 'method' => 'onTerminate', 'priority' => -4096])
        ->tag('kernel.event_listener', ['event' => ConsoleEvents::COMMAND, 'method' => 'onCommand', 'priority' => 4096])
        ->tag('kernel.event_listener', ['event' => ConsoleEvents::ERROR, 'method' => 'onConsoleError', 'priority' => 4096])
        ->tag('kernel.event_listener', ['event' => ConsoleEvents::TERMINATE, 'method' => 'onConsoleTerminate', 'priority' => -4096]);

    $services->set('domain_tracing.trace_ingester', TraceIngester::class)
        ->args([service('domain_tracing.trace_store'), service('domain_tracing.xdebug_value'), service('domain_tracing.secret_redactor')]);

    $services->set('domain_tracing.system_prompts', SystemPrompts::class)
        ->args([param('domain_tracing.context_file')]);

    $services->set('domain_tracing.operation_namer', OperationNamer::class)
        ->args([service('domain_tracing.agent'), service('cache.app'), service('domain_tracing.system_prompts')]);

    $services->set('domain_tracing.trace_narrator', TraceNarrator::class)
        ->args([service('domain_tracing.agent'), service('domain_tracing.system_prompts')]);

    $services->set('domain_tracing.project_summary', ProjectSummary::class)
        ->args([service('router'), '%kernel.project_dir%']);

    $services->set('domain_tracing.context_writer', ContextWriter::class)
        ->args([service('domain_tracing.agent'), service('domain_tracing.project_summary'), service('domain_tracing.system_prompts')]);

    $services->set('domain_tracing.redact_secrets_input_processor', RedactSecretsInputProcessor::class)
        ->args([service('domain_tracing.secret_redactor')]);

    $services->set('domain_tracing.trace_describer', TraceDescriber::class)
        ->args([service('domain_tracing.trace_store'), service('domain_tracing.blob_store'), service('domain_tracing.operation_namer'), service('domain_tracing.trace_narrator')]);

    $services->set('domain_tracing.trace_stream', TraceStream::class)
        ->args([service('domain_tracing.trace_store'), service('domain_tracing.blob_store'), service('domain_tracing.operation_namer'), service('domain_tracing.trace_ingester'), service('domain_tracing.trace_describer')]);

    $services->set('domain_tracing.trace_poller', TracePoller::class)
        ->args([service('domain_tracing.trace_store'), service('domain_tracing.trace_stream'), '%kernel.project_dir%', '%kernel.environment%']);

    $services->set('domain_tracing.trace_controller', TraceController::class)
        ->public()
        ->args([service('domain_tracing.trace_store'), service('domain_tracing.trace_stream'), service('twig'), service('domain_tracing.trace_poller'), '%domain_tracing.describe%'])
        // The "routing.controllers" resource of the application imports the routes of the tagged controllers
        ->tag('routing.controller');
    // The routes refer to the controller by its class
    $services->alias(TraceController::class, 'domain_tracing.trace_controller')
        ->public();

    $services->set('domain_tracing.mcp_server', TraceMcpServer::class)
        ->args([service('domain_tracing.trace_store'), service('domain_tracing.trace_stream'), service('twig'), '%domain_tracing.describe%']);

    $services->set('domain_tracing.trace_command', TraceCommand::class)
        ->args([service('domain_tracing.trace_store'), service('domain_tracing.trace_stream'), '%domain_tracing.describe%', service('domain_tracing.mcp_server')])
        ->tag('console.command');

    $services->set('domain_tracing.context_generate_command', ContextGenerateCommand::class)
        ->args([service('domain_tracing.context_writer'), param('domain_tracing.context_file')])
        ->tag('console.command');

    $services->set('domain_tracing.cache_clearer', TraceCacheClearer::class)
        ->args([service('domain_tracing.trace_store'), service('domain_tracing.blob_store')])
        ->tag('kernel.cache_clearer');

    $services->set('domain_tracing.traceparent_http_client', TraceparentHttpClient::class)
        ->decorate('http_client.transport', invalidBehavior: ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
        ->args([service('.inner')]);

    $services->set('domain_tracing.data_collector', TraceDataCollector::class)
        ->args([service('domain_tracing.trace_store'), service('domain_tracing.blob_store'), service('domain_tracing.xdebug_setup'), '%domain_tracing.describe%'])
        // right after the "request" collector, which has priority 335
        ->tag('data_collector', ['id' => 'domain_traces', 'template' => '@DomainTracing/collector.html.twig', 'priority' => 334]);
};
