<?php

namespace Valtzu\DomainTracingBundle\EventListener;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
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

    #[AsEventListener(KernelEvents::REQUEST, priority: 4096)]
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

    #[AsEventListener(KernelEvents::EXCEPTION, priority: 4096)]
    public function onException(ExceptionEvent $event): void
    {
        if ($event->isMainRequest()) {
            $this->recorder->recordException($event->getThrowable());
        }
    }

    #[AsEventListener(KernelEvents::TERMINATE, priority: -4096)]
    public function onTerminate(TerminateEvent $event): void
    {
        $this->recorder->stop($event->getResponse()->getStatusCode());
    }

    #[AsEventListener(ConsoleEvents::COMMAND, priority: 4096)]
    public function onCommand(): void
    {
        $this->recorder->start($_SERVER);
    }

    #[AsEventListener(ConsoleEvents::ERROR, priority: 4096)]
    public function onConsoleError(ConsoleErrorEvent $event): void
    {
        $this->recorder->recordException($event->getError());
    }

    #[AsEventListener(ConsoleEvents::TERMINATE, priority: -4096)]
    public function onConsoleTerminate(): void
    {
        $this->recorder->stop(null);
    }
}
