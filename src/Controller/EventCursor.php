<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Controller;

/**
 * Where a browser that polls a trace is. Each poll follows the trace again from its start, and gives the events of
 * the AI after the events of the trace that it reads at once, so their order changes while the trace grows. The order
 * of the events of each kind does not change, so the cursor counts each kind.
 *
 * @internal
 */
final class EventCursor
{
    private const array AI_EVENTS = ['name', 'event', 'failure', 'done'];

    /**
     * @param list<array{string, mixed}> $events all the events of the trace so far
     * @param string                     $cursor the cursor of the previous poll, or "0"
     *
     * @return array{list<array{string, mixed}>, string} the events after the cursor, and the new cursor
     */
    public static function after(array $events, string $cursor): array
    {
        [$traceRead, $aiRead] = array_map(intval(...), explode('.', $cursor.'.0'));
        $trace = $ai = 0;
        $new = [];
        foreach ($events as $event) {
            $read = \in_array($event[0], self::AI_EVENTS, true) ? ++$ai > $aiRead : ++$trace > $traceRead;
            if ($read) {
                $new[] = $event;
            }
        }

        return [$new, $trace.'.'.$ai];
    }
}
