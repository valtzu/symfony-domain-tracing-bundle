<?php

namespace Valtzu\DomainTracingBundle\EventListener;

use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Valtzu\DomainTracingBundle\Xdebug\Recorder;

/**
 * Records each main request, and each console command that runs with --profile, from the start of the kernel events
 * to their end.
 */
final readonly class RecordingListener
{
    public function __construct(
        private Recorder $recorder,
    ) {
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $spanId = $this->recorder->start($event->getRequest()->server->all());
        if (null !== $spanId) {
            $event->getRequest()->server->set('DOMAIN_TRACING_SPAN_ID', $spanId);
        }
    }

    public function onException(ExceptionEvent $event): void
    {
        if ($event->isMainRequest()) {
            $this->recorder->recordException($event->getThrowable());
        }
    }

    public function onTerminate(TerminateEvent $event): void
    {
        $this->recorder->stop($event->getResponse()->getStatusCode());
    }

    public function onCommand(): void
    {
        $this->recorder->start($_SERVER);
    }

    public function onConsoleError(ConsoleErrorEvent $event): void
    {
        $this->recorder->recordException($event->getError());
    }

    public function onConsoleTerminate(): void
    {
        $this->recorder->stop(null);
    }
}
