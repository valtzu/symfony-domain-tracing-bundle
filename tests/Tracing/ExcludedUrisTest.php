<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Valtzu\DomainTracingBundle\Tracing\ExcludedUris;

final class ExcludedUrisTest extends TestCase
{
    public function testMatchesThePathAndTheQuery()
    {
        $uris = new ExcludedUris(['[?&]frankenphp_hot_reloading=', '^/health$']);

        static::assertTrue($uris->matches('/?frankenphp_hot_reloading=true&reload=1794665290468'));
        static::assertTrue($uris->matches('/health'));
        static::assertFalse($uris->matches('/healthy'));
        static::assertFalse($uris->matches('/hello?name=ada'));
    }

    public function testExcludesNothingByDefault()
    {
        static::assertFalse((new ExcludedUris())->matches('/health'));
    }
}
