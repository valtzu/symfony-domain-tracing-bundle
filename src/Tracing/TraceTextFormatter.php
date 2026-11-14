<?php

namespace Valtzu\DomainTracingBundle\Tracing;

/**
 * Writes the events of a trace stream as lines of plain text: a span when it opens, indented under its parent, with
 * the time since the trace started, and the events of the AI under their span.
 */
final class TraceTextFormatter
{
    private const int MAX_VALUE_LENGTH = 1000;

    /** @var array<string, int> nesting level by span ID */
    private array $levels = [];

    /** @var array<string, true> request spans whose action is printed */
    private array $actions = [];

    /** @var array<string, true> spans whose arguments are printed */
    private array $arguments = [];
    private ?float $startedAt = null;

    public function __construct(
        private readonly bool $values = false,
    ) {
    }

    /**
     * @return list<string>
     */
    public function format(string $event, mixed $data): array
    {
        return match ($event) {
            'span' => $this->span($data),
            'name' => [\sprintf('%s   %s is named "%s"', str_repeat(' ', times: 9), $data['function'], $data['name'])],
            'event' => [$this->line($data['at'], ($this->levels[$data['span']] ?? 0) + 1, (($data['error'] ?? false) ? '✗ ' : '• ').$data['text'])],
            'failure' => ['The AI could not describe the trace: '.$data],
            default => [],
        };
    }

    private function span(array $span): array
    {
        $this->startedAt ??= $span['start'];
        if ('open' === $span['change']) {
            if (isset($this->levels[$span['id']])) {
                return [...$this->action($span), ...$this->arguments($span)];
            }
            $this->levels[$span['id']] = null === $span['parentId'] ? 0 : ($this->levels[$span['parentId']] ?? -1) + 1;
            $function = null === $span['function'] ? '' : '  '.$span['function'];

            return [$this->line($span['start'], $this->levels[$span['id']], \sprintf('%s [%s]%s', $span['name'], $span['service'], $function)), ...$this->arguments($span)];
        }

        $level = $this->levels[$span['id']] ?? 0;
        $lines = [];
        if ($this->values && \array_key_exists('returnValue', $span)) {
            $lines[] = $this->line(null, $level + 1, 'returned: '.self::value($span['returnValue']));
        }
        if ('server' === $span['kind'] && null !== $span['end']) {
            $lines[] = $this->line($span['end'], $level, \sprintf('end of %s %s [%s] after %.3fs', $span['method'], parse_url($span['uri'], \PHP_URL_PATH), $span['service'], $span['end'] - $span['start']));
        }

        return $lines;
    }

    /**
     * The span of a request opens before its action is known, and opens again with it.
     */
    private function action(array $span): array
    {
        if ('server' !== $span['kind'] || null === $span['function'] || isset($this->actions[$span['id']])) {
            return [];
        }
        $this->actions[$span['id']] = true;

        return [$this->line(null, $this->levels[$span['id']] + 1, \sprintf('→ %s  %s', $span['name'], $span['function']))];
    }

    private function arguments(array $span): array
    {
        if (!$this->values || !\array_key_exists('arguments', $span) || isset($this->arguments[$span['id']])) {
            return [];
        }
        $this->arguments[$span['id']] = true;

        return [$this->line(null, $this->levels[$span['id']] + 1, 'arguments: '.self::value($span['arguments']))];
    }

    private function line(?float $at, int $level, string $text): string
    {
        $time = null === $at || null === $this->startedAt ? str_repeat(' ', times: 9) : \sprintf('%+8.3fs', $at - $this->startedAt);

        return $time.' '.str_repeat('  ', $level).$text;
    }

    private static function value(mixed $value): string
    {
        $json = json_encode($value, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_INVALID_UTF8_SUBSTITUTE | \JSON_PARTIAL_OUTPUT_ON_ERROR);

        return mb_strlen($json) > self::MAX_VALUE_LENGTH ? mb_substr($json, start: 0, length: self::MAX_VALUE_LENGTH).'…' : $json;
    }
}
