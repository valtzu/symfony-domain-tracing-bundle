<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Tests\Xdebug;

use PHPUnit\Framework\TestCase;
use Valtzu\DomainTracingBundle\Xdebug\XdebugSetup;

final class XdebugSetupTest extends TestCase
{
    public function testNeedsOnlyTheXdebugMode()
    {
        $this->assertSame("[xdebug]\nxdebug.mode=trace,coverage\n", (new XdebugSetup('/srv/app/var/share/traces'))->settings());
    }

    public function testSaysThatXdebugIsMissing()
    {
        if (\extension_loaded('xdebug')) {
            $this->markTestSkipped('Xdebug is loaded.');
        }

        $this->assertSame(['Xdebug is not loaded.'], (new XdebugSetup('/srv/app/var/share/traces'))->problems());
    }
}
