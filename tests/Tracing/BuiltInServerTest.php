<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Valtzu\DomainTracingBundle\Tracing\BuiltInServer;

final class BuiltInServerTest extends TestCase
{
    public function testHasOneWorkerByDefault()
    {
        $this->assertTrue(BuiltInServer::hasOneWorker('cli-server', false));
        $this->assertTrue(BuiltInServer::hasOneWorker('cli-server', '1'));
    }

    public function testHasMoreWorkersWhenConfigured()
    {
        $this->assertFalse(BuiltInServer::hasOneWorker('cli-server', '4'));
    }

    public function testOtherServersAreNotLimited()
    {
        $this->assertFalse(BuiltInServer::hasOneWorker('fpm-fcgi', false));
        $this->assertFalse(BuiltInServer::hasOneWorker('frankenphp', false));
    }
}
