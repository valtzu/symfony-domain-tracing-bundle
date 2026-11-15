<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Controller;

use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\Routing\Attribute\Route;
use Valtzu\DomainTracingBundle\Mcp\TraceMcpServer;

final readonly class McpController
{
    public function __construct(
        private TraceMcpServer $server,
        private string $sessionDirectory,
    ) {
    }

    #[Route('/_tracing/mcp', name: 'domain_tracing_mcp', methods: ['GET', 'POST', 'DELETE', 'OPTIONS'])]
    public function __invoke(Request $request): Response
    {
        set_time_limit(0);
        $factory = new Psr17Factory();
        $response = $this->server->handle((new PsrHttpFactory($factory, $factory, $factory, $factory))->createRequest($request), $this->sessionDirectory);

        return (new HttpFoundationFactory())->createResponse($response, true);
    }

    public function onTerminate(TerminateEvent $event): void
    {
        if ('domain_tracing_mcp' === $event->getRequest()->attributes->get('_route')) {
            set_time_limit(0);
            $this->server->describeLoaded();
        }
    }
}
