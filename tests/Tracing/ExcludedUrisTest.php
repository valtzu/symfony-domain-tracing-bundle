<?php

namespace Valtzu\DomainTracingBundle\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Valtzu\DomainTracingBundle\Tracing\ExcludedUris;

final class ExcludedUrisTest extends TestCase
{
    public function testMatchesThePathAndTheQuery()
    {
        $uris = new ExcludedUris(['[?&]frankenphp_hot_reloading=', '^/health$']);

        $this->assertTrue($uris->matches('/?frankenphp_hot_reloading=true&reload=1794665290468'));
        $this->assertTrue($uris->matches('/health'));
        $this->assertFalse($uris->matches('/healthy'));
        $this->assertFalse($uris->matches('/hello?name=ada'));
    }

    public function testExcludesNothingByDefault()
    {
        $this->assertFalse((new ExcludedUris())->matches('/health'));
    }
}
