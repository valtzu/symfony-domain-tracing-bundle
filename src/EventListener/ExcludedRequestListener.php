<?php

namespace Valtzu\DomainTracingBundle\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Valtzu\DomainTracingBundle\Storage\TraceStore;
use Valtzu\DomainTracingBundle\Tracing\ExcludedUris;

use function Valtzu\DomainTracingBundle\Prepend\discard_trace;

/**
 * Stops recording a request whose URI the "exclude_uris" option excludes, and removes what was recorded of it.
 */
#[AsEventListener(priority: 4096)]
final readonly class ExcludedRequestListener
{
    public function __construct(
        private ExcludedUris $excluded,
        private TraceStore $store,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $spanId = $event->getRequest()->server->get('DOMAIN_TRACING_SPAN_ID');
        if (!$event->isMainRequest() || !\is_string($spanId) || !$this->excluded->matches($event->getRequest()->getRequestUri())) {
            return;
        }

        if (\function_exists('Valtzu\DomainTracingBundle\Prepend\discard_trace') && $traceFile = xdebug_get_tracefile_name()) {
            discard_trace($traceFile);
        }
        $this->store->remove($spanId);
        unset($_SERVER['DOMAIN_TRACING_SPAN_ID'], $_SERVER['DOMAIN_TRACING_TRACEPARENT']);
        $event->getRequest()->server->remove('DOMAIN_TRACING_SPAN_ID');
    }
}
