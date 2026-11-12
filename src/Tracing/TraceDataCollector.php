<?php

namespace Valtzu\AiStacktrace\Tracing;

use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shows the recorded trace of a request in the profiler.
 */
final class TraceDataCollector extends AbstractDataCollector
{
    public function __construct(
        private readonly TraceStore $store,
    ) {
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $id = (string) $request->headers->get('X-Request-Id');
        $this->data = ['id' => $this->store->has($id) ? $id : null];
    }

    public function getName(): string
    {
        return 'domain_traces';
    }

    public function getRequestId(): ?string
    {
        return $this->data['id'];
    }
}
