<?php

namespace App\Tracing;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Gives each application method a short business name, such as "Apply discounts". A name is asked once and kept until
 * the code of the method changes, so the same operation has the same name in every trace.
 */
final readonly class OperationNamer
{
    public function __construct(
        #[Autowire(service: 'ai.agent.operation_namer')]
        private AgentInterface $agent,
        #[Autowire(service: 'cache.app')]
        private CacheItemPoolInterface $cache,
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
     * @return array<string, string> names by function, including the cached ones
     */
    public function name(array $operations): array
    {
        $names = [];
        $missing = [];
        foreach ($operations as $operation) {
            $name = $this->cachedName($operation['service'], $operation['function'], $operation['code']);
            if (null === $name) {
                $missing[] = $operation;
            } else {
                $names[$operation['function']] = $name;
            }
        }

        if (!$missing) {
            return $names;
        }

        $prompt = "Name these operations:\n\n";
        foreach ($missing as $index => $operation) {
            $prompt .= \sprintf("Operation %d in the %s service, %s():\n%s\n\n", $index, $operation['service'], $operation['function'], $operation['code']);
        }

        $answer = (string) $this->agent->call(new MessageBag(Message::ofUser($prompt)))->getContent();
        foreach (explode("\n", $answer) as $line) {
            $named = json_decode(trim($line), true);
            $operation = $missing[$named['operation'] ?? -1] ?? null;
            if (null === $operation || !\is_string($named['name'] ?? null) || '' === trim($named['name'])) {
                continue;
            }

            $name = mb_substr(trim($named['name']), 0, 60);
            $item = $this->cache->getItem(self::key($operation['service'], $operation['function'], $operation['code']));
            $this->cache->save($item->set($name));
            $names[$operation['function']] = $name;
        }

        return $names;
    }

    private static function key(string $service, string $function, string $code): string
    {
        return 'operation_name.'.hash('sha256', $service."\0".$function."\0".$code);
    }
}
