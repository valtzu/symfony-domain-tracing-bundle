<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Valtzu\DomainTracingBundle\Controller\EventCursor;

final class EventCursorTest extends TestCase
{
    public function testGivesEachEventOnceWhenTheTraceGrowsBetweenTwoPolls(): void
    {
        [$first, $cursor] = EventCursor::after([['request', 1], ['step', 1], ['step', 2], ['event', 'A']], '0');

        // The next poll follows the trace from its start again, and the events of the AI come after the new steps
        [$second] = EventCursor::after([['request', 1], ['step', 1], ['step', 2], ['step', 3], ['step', 4], ['event', 'A'], ['event', 'B'], ['done', null]], $cursor);

        static::assertSame([['request', 1], ['step', 1], ['step', 2], ['event', 'A']], $first);
        static::assertSame([['step', 3], ['step', 4], ['event', 'B'], ['done', null]], $second);
    }
}
