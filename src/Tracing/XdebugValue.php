<?php

namespace App\Tracing;

use App\Ai\SecretRedactor;

/**
 * Parses a value as Xdebug writes it in a trace file, such as 'carol', [0 => 1], class App\Customer { public string
 * $id = 'carol' } or enum App\Tier::Gold('gold'), into a JSON value. Strings go through the secret redactor, the values
 * of secret-like names are left out, and an object outside the application or holding only objects and arrays, such as
 * a service, is shown by its class only. Text that does not parse is kept as it is.
 */
final class XdebugValue
{
    private string $text;
    private int $position;

    /** @var list<string> */
    private array $applicationNamespaces;

    public function __construct(
        private readonly SecretRedactor $redactor,
    ) {
    }

    /**
     * @param list<string> $applicationNamespaces
     */
    public function parse(string $text, array $applicationNamespaces = []): mixed
    {
        $this->text = $text;
        $this->position = 0;
        $this->applicationNamespaces = $applicationNamespaces;

        try {
            $value = $this->value();
            $this->skipSpaces();

            return $this->position === \strlen($text) ? $value : $this->redactor->redact($text);
        } catch (\UnexpectedValueException) {
            return $this->redactor->redact($text);
        }
    }

    private function value(): mixed
    {
        $this->skipSpaces();
        $rest = substr($this->text, $this->position, 6);

        return match (true) {
            str_starts_with($rest, "'") => $this->redactor->redact($this->string()),
            str_starts_with($rest, '[') => $this->array(),
            str_starts_with($rest, 'class ') => $this->object(),
            str_starts_with($rest, 'enum ') => $this->enum(),
            str_starts_with($rest, '...') => $this->expect('...') ?? '…',
            str_starts_with($rest, 'NULL') => $this->expect('NULL') ?? null,
            str_starts_with($rest, 'TRUE') => null === $this->expect('TRUE'),
            str_starts_with($rest, 'FALSE') => null !== $this->expect('FALSE'),
            1 === preg_match('/\G-?\d+(\.\d+)?(E[+-]?\d+)?/i', $this->text, $match, 0, $this->position) => $this->number($match[0]),
            default => throw new \UnexpectedValueException(),
        };
    }

    private function string(): string
    {
        if (!preg_match("/\\G'((?:[^'\\\\]|\\\\.)*)'/s", $this->text, $match, 0, $this->position)) {
            throw new \UnexpectedValueException();
        }
        $this->position += \strlen($match[0]);

        return stripcslashes($match[1]);
    }

    private function number(string $number): int|float
    {
        $this->position += \strlen($number);

        return is_numeric($number) && !preg_match('/[.e]/i', $number) ? (int) $number : (float) $number;
    }

    private function array(): array|string
    {
        $this->expect('[');
        $items = [];
        while (!$this->consume(']')) {
            if ($this->consume('...')) {
                $items['…'] = 'more';
                continue;
            }
            $key = $this->value();
            $this->expect('=>');
            $items[$key] = \is_string($key) && SecretRedactor::isSecretName($key) ? $this->skipValue() : $this->value();
            $this->consume(',');
        }

        return $items;
    }

    private function object(): array|string
    {
        $this->expect('class');
        $class = $this->name();
        $this->expect('{');

        $properties = [];
        $truncated = false;
        while (!$this->consume('}')) {
            if ($this->consume('...')) {
                $truncated = true;
                continue;
            }
            if (!preg_match('/\G[^$]*\$(\w+)\s*=/', $this->text, $match, 0, $this->position)) {
                throw new \UnexpectedValueException();
            }
            $this->position += \strlen($match[0]);
            $properties[$match[1]] = SecretRedactor::isSecretName($match[1]) ? $this->skipValue() : $this->value();
            $this->consume(';');
        }

        $insideApplication = !$this->applicationNamespaces || array_filter($this->applicationNamespaces, static fn (string $namespace) => str_starts_with($class, $namespace));
        $holdsScalars = array_filter($properties, static fn ($value) => null === $value || \is_scalar($value) && !str_starts_with((string) $value, '['));
        if ($truncated || !$insideApplication || $properties && !$holdsScalars) {
            return '['.$class.']';
        }

        return ['@class' => $class] + $properties;
    }

    private function enum(): string
    {
        $this->expect('enum');
        $case = $this->name();
        if ($this->consume('(')) {
            $this->value();
            $this->expect(')');
        }

        return $case;
    }

    private function skipValue(): string
    {
        $this->value();

        return SecretRedactor::PLACEHOLDER;
    }

    private function name(): string
    {
        $this->skipSpaces();
        if (!preg_match('/\G[\w\\\\:@]+/', $this->text, $match, 0, $this->position)) {
            throw new \UnexpectedValueException();
        }
        $this->position += \strlen($match[0]);

        return $match[0];
    }

    private function expect(string $token): null
    {
        if (!$this->consume($token)) {
            throw new \UnexpectedValueException();
        }

        return null;
    }

    private function consume(string $token): bool
    {
        $this->skipSpaces();
        if (substr($this->text, $this->position, \strlen($token)) !== $token) {
            if ($this->position >= \strlen($this->text)) {
                throw new \UnexpectedValueException();
            }

            return false;
        }
        $this->position += \strlen($token);

        return true;
    }

    private function skipSpaces(): void
    {
        $this->position += strspn($this->text, " \t\n", $this->position);
    }
}
