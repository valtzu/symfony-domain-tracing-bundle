<?php

namespace Valtzu\DomainTracingBundle\Tests\Tracing;

use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\TestCase;
use Valtzu\DomainTracingBundle\Tracing\TraceSession;

#[BackupGlobals(true)]
final class TraceSessionTest extends TestCase
{
    public function testNamesTheServiceAfterTheProjectDirectory()
    {
        unset($_SERVER['OTEL_SERVICE_NAME']);
        $_SERVER['SCRIPT_FILENAME'] = '/srv/greetings/public/index.php';

        $this->assertSame('greetings', $this->request()['service']);
    }

    public function testNamesTheServiceAfterTheOpenTelemetryVariable()
    {
        $_SERVER['OTEL_SERVICE_NAME'] = 'shop';
        $_SERVER['SCRIPT_FILENAME'] = '/srv/greetings/public/index.php';

        $this->assertSame('shop', $this->request()['service']);
    }

    private function request(): array
    {
        return json_decode(eval('return '.TraceSession::REQUEST.';'), true);
    }
}
