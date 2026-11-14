<?php

namespace Valtzu\DomainTracingBundle\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * Keeps the exception that a recorded request did not handle, with the methods that were running when it was thrown,
 * innermost first. The prepend file writes it next to the trace file when the request ends, because Xdebug traces do
 * not record exceptions.
 */
#[AsEventListener(priority: 4096)]
final readonly class ExceptionRecorder
{
    private const int MAX_FUNCTIONS = 100;

    public function __invoke(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest() || !\is_string($event->getRequest()->server->get('DOMAIN_TRACING_SPAN_ID'))) {
            return;
        }

        $exception = $event->getThrowable();
        $functions = [];
        foreach (\array_slice($exception->getTrace(), offset: 0, length: self::MAX_FUNCTIONS) as $frame) {
            if (!isset($frame['class'], $frame['type'])) {
                continue;
            }
            $functions[] = $frame['class'].$frame['type'].$frame['function'];
        }

        $_SERVER['DOMAIN_TRACING_EXCEPTION'] = ['class' => $exception::class, 'message' => $exception->getMessage(), 'functions' => $functions];
    }
}
