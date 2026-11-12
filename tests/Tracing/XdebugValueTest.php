<?php

namespace Valtzu\DomainTracingBundle\Tests\Tracing;

use Valtzu\DomainTracingBundle\Ai\SecretRedactor;
use Valtzu\DomainTracingBundle\Tracing\XdebugValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class XdebugValueTest extends TestCase
{
    #[DataProvider('provideValues')]
    public function testParsesValues(string $text, mixed $expected)
    {
        $this->assertSame($expected, (new XdebugValue(new SecretRedactor([])))->parse($text, ['App\\']));
    }

    public static function provideValues(): iterable
    {
        yield 'string' => ["'carol'", 'carol'];
        yield 'escaped string' => ["'it\\'s'", "it's"];
        yield 'int' => ['2900', 2900];
        yield 'negative float' => ['-1.5', -1.5];
        yield 'bool' => ['FALSE', false];
        yield 'null' => ['NULL', null];
        yield 'list' => ["[0 => 'a', 1 => 2]", ['a', 2]];
        yield 'map with truncation' => ["['sku' => 'MS-02', ...]", ['sku' => 'MS-02', '…' => 'more']];
        yield 'enum' => ["enum App\\Checkout\\Model\\CustomerTier::Regular('regular')", 'App\\Checkout\\Model\\CustomerTier::Regular'];
        yield 'entity' => [
            "class App\\Checkout\\Model\\Customer { public string \$id = 'carol'; public App\\Checkout\\Model\\CustomerTier \$tier = enum App\\Checkout\\Model\\CustomerTier::Regular('regular'); public bool \$blocked = FALSE }",
            ['@class' => 'App\\Checkout\\Model\\Customer', 'id' => 'carol', 'tier' => 'App\\Checkout\\Model\\CustomerTier::Regular', 'blocked' => false],
        ];
        yield 'truncated object' => ['class App\\Checkout\\Model\\Customer { ... }', '[App\\Checkout\\Model\\Customer]'];
        yield 'service' => ["class App\\Checkout\\Checkout { private App\\Checkout\\Pricing \$pricing = class App\\Checkout\\Pricing { ... } }", '[App\\Checkout\\Checkout]'];
        yield 'framework object' => ["class Symfony\\Component\\HttpFoundation\\Request { public \$attributes = 'x' }", '[Symfony\\Component\\HttpFoundation\\Request]'];
        yield 'secret property' => ["class App\\Account { public string \$name = 'carol'; private string \$password = 'hunter2' }", ['@class' => 'App\\Account', 'name' => 'carol', 'password' => '[redacted]']];
        yield 'secret key' => ["['apiToken' => 'abc', 'user' => 'bob']", ['apiToken' => '[redacted]', 'user' => 'bob']];
        yield 'unparsable text is kept' => ['resource(5) of type (stream)', 'resource(5) of type (stream)'];
    }
}
