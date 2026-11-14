<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Tests\Storage;

use PHPUnit\Framework\TestCase;
use Valtzu\DomainTracingBundle\Storage\TraceStore;

final class TraceStoreTest extends TestCase
{
    private const ROOT = '8985fb33258f4229';
    private const CHILD = '97f16856e73a4d4a';
    private const TRACE = '8985fb33258f422981f678dc02d1ee28';

    private string $directory;
    private TraceStore $store;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/domain-tracing-'.bin2hex(random_bytes(4));
        $this->store = new TraceStore($this->directory);
        $this->store->start(self::ROOT, ['traceId' => self::TRACE, 'parentSpanId' => null]);
        $this->store->start(self::CHILD, ['traceId' => self::TRACE, 'parentSpanId' => self::ROOT]);
    }

    public function testFindsTheRequestThatStartedTheTrace()
    {
        $this->assertSame(self::ROOT, $this->store->rootOf(self::CHILD));
        $this->assertSame(self::ROOT, $this->store->rootOf(self::ROOT));
    }

    public function testKeepsTheRequestWhenItRestarts()
    {
        $this->store->addStep(self::ROOT, 0, 12, 1, 100);
        $this->store->end(self::ROOT, 1.0);
        $this->assertTrue($this->store->hasEnded(self::ROOT));

        $this->store->restart(self::ROOT);

        $this->assertFalse($this->store->hasEnded(self::ROOT));
        $this->assertSame(['request'], array_column($this->store->read(self::ROOT)['records'], 'type'));
        $this->assertSame(self::TRACE, $this->store->request(self::ROOT)['traceId']);
    }

    public function testGivesALockToOneHolderAtATime()
    {
        $lock = $this->store->lockSpan(self::ROOT);
        $this->assertNotNull($lock);
        $this->assertNull($this->store->lockSpan(self::ROOT));
        $this->assertNotNull($this->store->lockSpan(self::CHILD));

        fclose($lock);

        $this->assertNotNull($this->store->lockSpan(self::ROOT));
        $this->assertNotNull($description = $this->store->lockDescription(self::ROOT));
        $this->assertNull($this->store->lockDescription(self::ROOT));
        fclose($description);
    }

    public function testKnowsWhenATraceIsDescribed()
    {
        $this->store->addNarration(self::ROOT, ['type' => 'start']);
        $this->assertFalse($this->store->isDescribed(self::ROOT));

        $this->store->addNarration(self::ROOT, ['type' => 'end', 'traceId' => self::TRACE]);
        $this->assertTrue($this->store->isDescribed(self::ROOT));

        $this->store->clearNarration(self::ROOT);
        $this->assertFalse($this->store->isDescribed(self::ROOT));
    }

    public function testRemovesTheTracesOlderThanTheGivenTime()
    {
        $this->store->addNarration(self::ROOT, ['type' => 'start']);
        file_put_contents($this->directory.'/trace.1794671096.0728f2.xt.gz', '');
        file_put_contents($this->directory.'/trace.1794671096.0728f2.xt.gz.lines.json', '[]');
        file_put_contents($this->directory.'/codeindex-6151f083bbd6339185fa5352b12567f4.json', '{}');
        file_put_contents($this->directory.'/notes.txt', '');
        foreach (glob($this->directory.'/*') as $path) {
            touch($path, time() - 3 * 86400);
        }
        touch($this->directory.'/'.self::CHILD.'.jsonl');

        $this->store->removeOlderThan(time() - 2 * 86400);

        $this->assertSame([self::CHILD.'.jsonl', 'notes.txt'], array_map(basename(...), glob($this->directory.'/*')));
    }

    public function testRemovesAllTheTraces()
    {
        file_put_contents($this->directory.'/trace.1794671096.0728f2.xt', '');
        file_put_contents($this->directory.'/notes.txt', '');

        $this->store->clear();

        $this->assertSame(['notes.txt'], array_map(basename(...), glob($this->directory.'/*')));
    }
}
