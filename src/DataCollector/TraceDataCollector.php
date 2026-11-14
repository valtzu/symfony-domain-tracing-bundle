<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\DataCollector;

use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Valtzu\DomainTracingBundle\Storage\BlobStore;
use Valtzu\DomainTracingBundle\Storage\TraceStore;
use Valtzu\DomainTracingBundle\Tracing\BuiltInServer;
use Valtzu\DomainTracingBundle\Xdebug\XdebugSetup;

/**
 * Shows the recorded trace of a request in the profiler. The traces expire as the profiles do: the file storage of the
 * profiler removes the profiles older than 2 days, on 1 write in 10 (FileProfilerStorage::removeExpiredProfiles()).
 */
final class TraceDataCollector extends AbstractDataCollector
{
    private const int EXPIRY_SECONDS = 2 * 86_400;
    private const int EXPIRY_CHANCE = 10;

    public function __construct(
        private readonly TraceStore $store,
        private readonly BlobStore $blobs,
        private readonly XdebugSetup $xdebug,
        private readonly bool $describes,
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
            'poll' => BuiltInServer::hasOneWorker(),
            'describe' => $this->describes,
        ];

        if (1 === mt_rand(1, self::EXPIRY_CHANCE)) {
            $this->store->removeOlderThan(time() - self::EXPIRY_SECONDS);
            $this->blobs->removeOlderThan(time() - self::EXPIRY_SECONDS);
        }
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

    public function describes(): bool
    {
        return $this->data['describe'] ?? false;
    }

    public function shouldPoll(): bool
    {
        return $this->data['poll'] ?? false;
    }

    public function getXdebugSettings(): ?string
    {
        return $this->data['settings'] ?? null;
    }
}
