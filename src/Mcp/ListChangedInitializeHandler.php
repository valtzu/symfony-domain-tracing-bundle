<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Mcp;

use Mcp\Schema\JsonRpc\Error;
use Mcp\Schema\JsonRpc\Request;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Result\InitializeResult;
use Mcp\Schema\ServerCapabilities;
use Mcp\Server\Handler\Request\InitializeHandler;
use Mcp\Server\Handler\Request\RequestHandlerInterface;
use Mcp\Server\Session\SessionInterface;

/**
 * Announces "listChanged" for the tools and the resources in the answer to "initialize". The MCP SDK removes it there,
 * because it sends list changes only through "subscriptions/listen", but this server sends them in the session itself.
 *
 * @see https://github.com/modelcontextprotocol/php-sdk/pull/593
 *
 * @implements RequestHandlerInterface<InitializeResult>
 */
final readonly class ListChangedInitializeHandler implements RequestHandlerInterface
{
    public function __construct(
        private InitializeHandler $handler,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $this->handler->supports($request);
    }

    /**
     * @return Response<InitializeResult>|Error
     */
    public function handle(Request $request, SessionInterface $session): Response|Error
    {
        $response = $this->handler->handle($request, $session);
        $result = $response->result;
        $capabilities = $result->capabilities;

        return new Response($response->id, new InitializeResult(
            new ServerCapabilities(
                $capabilities->tools,
                true,
                $capabilities->resources,
                $capabilities->resourcesSubscribe,
                true,
                $capabilities->prompts,
                $capabilities->promptsListChanged,
                $capabilities->logging,
                $capabilities->completions,
                $capabilities->experimental,
                $capabilities->extensions,
                $capabilities->tasks,
            ),
            $result->serverInfo,
            $result->instructions,
            $result->meta,
            $result->protocolVersion,
        ));
    }
}
