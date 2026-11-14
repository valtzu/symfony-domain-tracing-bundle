<?php

namespace Valtzu\DomainTracingBundle\Tracing;

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
        private readonly XdebugSetup $xdebug,
    ) {
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $id = (string) $request->server->get('DOMAIN_TRACING_SPAN_ID');
        $recorded = $this->store->has($id);
        $this->data = [
            'id' => $recorded ? $id : null,
            'problems' => $recorded ? [] : $this->xdebug->problems(),
            'settings' => $recorded ? null : $this->xdebug->settings(),
        ];
    }

    public function getName(): string
    {
        return 'domain_traces';
    }

    public function getRequestId(): ?string
    {
        return $this->data['id'];
    }

    /**
     * @return list<string> why the request was not recorded
     */
    public function getProblems(): array
    {
        return $this->data['problems'] ?? [];
    }

    public function getXdebugSettings(): ?string
    {
        return $this->data['settings'] ?? null;
    }
}
