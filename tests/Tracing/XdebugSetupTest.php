<?php

namespace Valtzu\DomainTracingBundle\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Valtzu\DomainTracingBundle\Tracing\XdebugSetup;

final class XdebugSetupTest extends TestCase
{
    public function testGivesTheSettingsWithThePathsOfTheProject()
    {
        $settings = (new XdebugSetup('/srv/app/var/share/traces'))->settings();

        $this->assertStringContainsString("xdebug.mode=trace,coverage\n", $settings);
        $this->assertStringContainsString("xdebug.output_dir=/srv/app/var/share/traces\n", $settings);
        $this->assertStringContainsString('auto_prepend_file='.realpath(__DIR__.'/../../resources/xdebug-prepend.php')."\n", $settings);
    }

    public function testSaysThatXdebugIsMissing()
    {
        if (\extension_loaded('xdebug')) {
            $this->markTestSkipped('Xdebug is loaded.');
        }

        $this->assertSame(['Xdebug is not loaded.'], (new XdebugSetup('/srv/app/var/share/traces'))->problems());
    }
}
