<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Ai;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;

/**
 * Gives each application method a short business name, such as "Apply discounts". A name is asked once and kept until
 * the code of the method changes, so the same operation has the same name in every trace.
 */
final readonly class OperationNamer
{
    public function __construct(
        private AgentInterface $agent,
        private CacheItemPoolInterface $cache,
        private SystemPrompts $prompts = new SystemPrompts(),
    ) {
    }

    public function cachedName(string $service, string $function, string $code): ?string
    {
        $item = $this->cache->getItem(self::key($service, $function, $code));

        return $item->isHit() ? $item->get() : null;
    }

    /**
     * @param list<array{service: string, function: string, code: string}> $operations
     *
     * @return \Generator<string, string> names by function, the cached ones first
     */
    public function name(array $operations): \Generator
    {
        $missing = [];
        foreach ($operations as $operation) {
            $name = $this->cachedName($operation['service'], $operation['function'], $operation['code']);
            if (null === $name) {
                $missing[] = $operation;
            } else {
                yield $operation['function'] => $name;
            }
        }

        if (!$missing) {
            return;
        }

        $prompt = "Name these operations:\n\n";
        foreach ($missing as $index => $operation) {
            $prompt .= \sprintf("Operation %d in the %s service, %s():\n%s\n\n", $index, $operation['service'], $operation['function'], $operation['code']);
        }

        $buffer = '';
        /** @var iterable<mixed> $deltas */
        $deltas = $this->agent->call(new MessageBag(Message::forSystem($this->prompts->get('operation_namer')), Message::ofUser($prompt)), ['stream' => true])->getContent();
        foreach ($deltas as $delta) {
            if (!$delta instanceof TextDelta) {
                continue;
            }
            $buffer .= $delta->getText();
            while (false !== $newline = strpos($buffer, "\n")) {
                yield from $this->toName(substr($buffer, 0, $newline), $missing);
                $buffer = substr($buffer, $newline + 1);
            }
        }
        yield from $this->toName($buffer, $missing);
    }

    /**
     * @param list<array{service: string, function: string, code: string}> $operations
     *
     * @return iterable<string, string>
     */
    private function toName(string $line, array $operations): iterable
    {
        $named = json_decode(trim($line), true);
        $operation = $operations[$named['operation'] ?? -1] ?? null;
        if (null === $operation || !\is_string($named['name'] ?? null) || '' === trim($named['name'])) {
            return;
        }

        $name = mb_substr(trim($named['name']), 0, 60);
        $item = $this->cache->getItem(self::key($operation['service'], $operation['function'], $operation['code']));
        $this->cache->save($item->set($name));
        yield $operation['function'] => $name;
    }

    private static function key(string $service, string $function, string $code): string
    {
        return 'operation_name.'.hash('sha256', $service."\0".$function."\0".$code);
    }
}
