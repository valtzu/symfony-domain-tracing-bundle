<?php

namespace App\Tracing;

use App\Ai\SecretRedactor;

/**
 * Converts a DBGp property, such as a variable from "context_get" or a return value, into a JSON value. Strings go
 * through the secret redactor, and the values of secret-like names, such as "password", are left out. An object that
 * holds only other objects and arrays, such as a service, and an object of a class outside the application, such as a
 * framework object, are shown by their class only.
 */
final readonly class DbgpValue
{
    private const NAMESPACE = 'urn:debugger_protocol_v1';

    public function __construct(
        private SecretRedactor $redactor,
    ) {
    }

    /**
     * @param list<string> $applicationNamespaces the namespaces of the classes whose objects are shown with their values
     */
    public function convert(\SimpleXMLElement $property, array $applicationNamespaces = []): mixed
    {
        $attributes = $property->attributes();
        $type = (string) $attributes['type'];
        $text = (string) $property;
        if ('base64' === (string) $attributes['encoding']) {
            $text = base64_decode($text);
        }

        return match ($type) {
            'int' => (int) $text,
            'float' => (float) $text,
            'bool' => '1' === $text,
            'null', 'uninitialized' => null,
            'string' => $this->redactor->redact($text).((int) $attributes['size'] > \strlen($text) ? '…' : ''),
            'array', 'object' => $this->convertChildren($property, $type, (string) $attributes['classname'], (int) $attributes['numchildren'], $applicationNamespaces),
            default => '['.$type.']',
        };
    }

    private function convertChildren(\SimpleXMLElement $property, string $type, string $class, int $count, array $applicationNamespaces): mixed
    {
        $children = $property->children(self::NAMESPACE)->property;
        $outsideApplication = 'object' === $type && $applicationNamespaces && !array_filter($applicationNamespaces, static fn (string $namespace) => str_starts_with($class, $namespace));
        if ($outsideApplication || $count > 0 && 0 === $children->count()) {
            return 'object' === $type ? '['.$class.']' : '[array('.$count.')]';
        }

        $value = [];
        foreach ($children as $child) {
            $name = (string) $child->attributes()['name'];
            $value[$name] = SecretRedactor::isSecretName($name) ? SecretRedactor::PLACEHOLDER : $this->convert($child, $applicationNamespaces);
        }

        if ('object' === $type && $value && !array_filter($value, static fn ($item) => null === $item || \is_scalar($item) && !str_starts_with((string) $item, '['))) {
            return '['.$class.']';
        }
        if ($count > $children->count()) {
            $value['…'] = \sprintf('%d more', $count - $children->count());
        }

        return 'object' === $type ? ['@class' => $class] + $value : $value;
    }
}
