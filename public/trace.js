const SERVICE_COLORS = 4;
// Read when the events come from the server of the page; an MCP app has no URL to start from (about:srcdoc)
const baseUrl = () => new URL('./', import.meta.url);
const ROW_ENTER_MILLISECONDS = 250;
const NAME_ERASE_MILLISECONDS = 300;
const NAME_WRITE_MILLISECONDS = 450;
const MIN_ROUNDS = 3;
const MAX_CALLS_PER_ROUND = 20;
const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');

/**
 * A waterfall of the spans of a trace, like the trace view of Jaeger, that grows while the trace is recorded.
 */
function createWaterfall(container, describing) {
    const spans = new Map();
    const events = new Map();
    const names = new Map();
    const namedAt = new Map();
    const services = [];
    let latest = 0;
    let recordingEnded = false;
    let frame = null;
    let pendingShown = false;
    let pendingChangedAt = 0;
    const shownAt = new Map();

    const serviceColor = service => {
        if (!services.includes(service)) {
            services.push(service);
        }
        return services.indexOf(service) % SERVICE_COLORS;
    };

    const failedSpan = span => (events.get(span.id) ?? []).some(event => event.error) || span.exception || span.status >= 500;

    /**
     * The rows of the waterfall: each span followed by its events and its child spans, in time order, so that an event
     * appears between the operations it happened between. A loop, calls that are made again and again in the same
     * order and make the same calls each time, shows its first round only, with the number of rounds. A round can be
     * one call or several calls one after the other.
     */
    const ordered = () => {
        const children = new Map();
        for (const span of spans.values()) {
            const parent = spans.has(span.parentId) ? span.parentId : null;
            children.set(parent, [...(children.get(parent) ?? []), span]);
        }
        for (const list of children.values()) {
            list.sort((a, b) => a.start - b.start);
        }
        // What a round does: its method, whether it failed, and the calls it made, whatever the number of rounds of
        // its own loops
        const shapes = new Map();
        const shape = span => shapes.get(span.id) ?? shapes.set(span.id, `${span.function}${failedSpan(span) ? '!' : ''}(${
            (children.get(span.id) ?? []).map(shape).filter((child, index, all) => child !== all[index - 1]).join(',')
        })`).get(span.id);

        // The number of calls in a round of the loop that starts at the item, and its number of rounds
        const loopAt = (items, start) => {
            const at = index => items[index]?.span ? shape(items[index].span) : null;
            let best = [1, 1];
            for (let size = 1; size <= MAX_CALLS_PER_ROUND && start + size * MIN_ROUNDS <= items.length; size++) {
                const round = Array.from({ length: size }, (_, offset) => at(start + offset));
                if (round.includes(null)) {
                    break;
                }
                let rounds = 1;
                while (round.every((path, offset) => at(start + rounds * size + offset) === path)) {
                    rounds++;
                }
                if (rounds >= MIN_ROUNDS && size * rounds > best[0] * best[1]) {
                    best = [size, rounds];
                }
            }
            return best;
        };

        const rows = [];
        const visit = (parent, depth) => {
            const items = [
                ...(children.get(parent) ?? []).map(span => ({ at: span.start, span })),
                ...(null === parent ? [] : events.get(parent) ?? []).map(event => ({ at: event.at, event })),
            ].sort((a, b) => a.at - b.at || (a.event ? 1 : -1));
            for (let index = 0; index < items.length;) {
                if (items[index].event) {
                    rows.push({ event: items[index].event, depth });
                    index++;
                    continue;
                }
                const [size, rounds] = loopAt(items, index);
                for (let offset = 0; offset < size; offset++) {
                    const span = items[index + offset].span;
                    rows.push(rounds < MIN_ROUNDS ? { span, depth } : {
                        span,
                        depth,
                        rounds: 0 === offset ? rounds : null,
                        loop: true,
                        last: items[index + (rounds - 1) * size + offset].span,
                    });
                    visit(span.id, depth + 1);
                }
                index += size * rounds;
            }
        };
        visit(null, 0);
        return rows;
    };

    /**
     * The waterfall is rebuilt on each update, so a new row continues its enter animation from where the previous
     * element of that row stopped.
     */
    const enter = (element, key) => {
        const now = performance.now();
        const age = now - (shownAt.get(key) ?? shownAt.set(key, now).get(key));
        if (age < ROW_ENTER_MILLISECONDS) {
            element.classList.add('waterfall-enter');
            element.style.animationDelay = `-${age}ms`;
        }
    };

    /**
     * Erases the label that the row had before its name arrived and types the name, as a typewriter would.
     */
    const rename = (element, span) => {
        const named = namedAt.get(span.function);
        const age = performance.now() - named;
        const name = names.get(span.function);
        if (name === span.name || !(shownAt.get(span.id) < named) || age >=NAME_ERASE_MILLISECONDS + NAME_WRITE_MILLISECONDS || reducedMotion.matches) {
            return;
        }

        const erasing = age < NAME_ERASE_MILLISECONDS;
        element.textContent = erasing
            ? span.name.slice(0, Math.ceil(span.name.length * (1 - age / NAME_ERASE_MILLISECONDS)))
            : name.slice(0, Math.floor(name.length * (age - NAME_ERASE_MILLISECONDS) / NAME_WRITE_MILLISECONDS));
        element.classList.add(erasing ? 'erasing' : 'writing');
        element.setAttribute('aria-label', name);
        element.appendChild(document.createElement('span')).className = 'waterfall-caret';
        update();
    };

    const milliseconds = seconds => seconds * 1000 >= 10 ? `${(seconds * 1000).toFixed(0)} ms` : `${(seconds * 1000).toFixed(2)} ms`;

    function render() {
        frame = null;
        const rows = ordered();
        if (!rows.length) {
            return;
        }
        const spanRows = rows.filter(row => row.span).flatMap(row => row.last ? [row, { span: row.last }] : [row]);
        const start = Math.min(...spanRows.map(row => row.span.start));
        const range = Math.max(latest - start, ...spanRows.map(row => (row.span.end ?? latest) - start), 0.001);

        const table = document.createElement('div');
        table.className = 'waterfall';
        const axis = table.appendChild(document.createElement('div'));
        axis.className = 'waterfall-axis';
        axis.innerHTML = `<span></span><span class="waterfall-ticks">${[0, 0.25, 0.5, 0.75, 1].map(f => `<span style="left: ${f * 100}%">${milliseconds(f * range)}</span>`).join('')}</span>`;

        for (const [index, { span, event, depth, rounds, loop, last }] of rows.entries()) {
            if (event) {
                const item = table.appendChild(document.createElement('div'));
                item.className = `waterfall-row waterfall-event${event.error ? ' error' : ''}`;
                enter(item, event);
                const text = item.appendChild(document.createElement('div'));
                text.className = 'waterfall-event-text';
                text.style.paddingLeft = `${depth * 14}px`;
                text.textContent = event.text;
                const track = item.appendChild(document.createElement('div'));
                track.className = 'waterfall-track';
                const position = (event.at - start) / range;
                const dot = track.appendChild(document.createElement('span'));
                dot.className = 'waterfall-dot';
                dot.style.left = `clamp(4px, ${position * 100}%, 100% - 4px)`;
                const time = track.appendChild(document.createElement('span'));
                time.className = 'waterfall-duration';
                time.textContent = milliseconds(event.at - start);
                if (position > 0.85) {
                    time.style.right = `calc(${(1 - position) * 100}% + 8px)`;
                } else {
                    time.style.left = `calc(${position * 100}% + 8px)`;
                }
                continue;
            }

            const end = (last ?? span).end ?? latest;
            const spanEvents = events.get(span.id) ?? [];
            const failed = failedSpan(span);
            const row = table.appendChild(document.createElement('div'));
            row.className = `waterfall-row service-${serviceColor(span.service)}${failed ? ' failed' : ''}${null === span.end ? ' running' : ''}${loop ? ' waterfall-loop' : ''}`;
            enter(row, span.id);

            const label = row.appendChild(document.createElement('div'));
            label.className = 'waterfall-label';
            label.style.paddingLeft = `${depth * 14}px`;
            label.title = [
                span.function ?? span.uri ?? '',
                span.arguments ? `called with ${JSON.stringify(span.arguments)}` : '',
                'returnValue' in span ? `returned ${JSON.stringify(span.returnValue)}` : '',
                span.status ? `answered with status ${span.status}` : '',
                span.exception ? `threw ${span.exception.class}: ${span.exception.message}` : '',
                rounds ? `called ${rounds} times in a loop, with the same calls each time; the values are those of the first round` : '',
            ].filter(Boolean).join('\n');
            label.innerHTML = '<span class="waterfall-service"></span><span class="waterfall-name"></span>';
            label.querySelector('.waterfall-service').textContent = span.service;
            if (rounds) {
                const repeats = label.appendChild(document.createElement('span'));
                repeats.className = 'waterfall-rounds';
                repeats.textContent = `×${rounds}`;
            }
            if (span.status >= 400) {
                const status = label.appendChild(document.createElement('span'));
                status.className = 'waterfall-status';
                status.textContent = span.status;
            }
            const name = label.querySelector('.waterfall-name');
            name.textContent = names.get(span.function) ?? span.name;
            if (names.has(span.function)) {
                rename(name, span);
            } else if (describing && span.function) {
                name.classList.add('naming');
                name.style.animationDelay = `${-pulsePhase(index)}ms`;
                label.setAttribute('aria-busy', 'true');
            }

            const track = row.appendChild(document.createElement('div'));
            track.className = 'waterfall-track';
            const bar = track.appendChild(document.createElement('div'));
            bar.className = 'waterfall-bar';
            bar.style.left = `${(span.start - start) / range * 100}%`;
            bar.style.width = `max(2px, ${(end - span.start) / range * 100}%)`;
            // A request waits for its service to start before the application handles it
            if (span.handledAt > span.start) {
                const startup = track.appendChild(document.createElement('div'));
                startup.className = 'waterfall-startup';
                startup.style.left = bar.style.left;
                startup.style.width = `${(Math.min(span.handledAt, end) - span.start) / range * 100}%`;
                startup.title = `Getting ready: the ${span.service} service was starting up before it could handle this request (${milliseconds(span.handledAt - span.start)})`;
            }
            const duration = track.appendChild(document.createElement('span'));
            duration.className = 'waterfall-duration';
            duration.style.left = `calc(${(end - start) / range * 100}% + 4px)`;
            duration.textContent = milliseconds(end - span.start);
            if (end - start > range * 0.85) {
                duration.style.left = '';
                duration.style.right = `calc(${(1 - (span.start - start) / range) * 100}% + 4px)`;
            }
            for (const event of spanEvents) {
                const marker = track.appendChild(document.createElement('span'));
                marker.className = `waterfall-marker${event.error ? ' error' : ''}`;
                marker.style.left = `min(${(event.at - start) / range * 100}%, 100% - 2px)`;
                marker.title = event.text;
            }
        }

        const pending = describing && recordingEnded;
        if (pending !== pendingShown) {
            pendingShown = pending;
            pendingChangedAt = performance.now();
        }
        const pendingAge = performance.now() - pendingChangedAt;
        if (pending || pendingAge < ROW_ENTER_MILLISECONDS) {
            const element = table.appendChild(document.createElement('p'));
            element.className = 'waterfall-pending';
            element.append(spinner(), 'Describing what happened…');
            if (pendingAge < ROW_ENTER_MILLISECONDS) {
                element.classList.add(pending ? 'waterfall-enter' : 'waterfall-leave');
                element.style.animationDelay = `-${pendingAge}ms`;
            }
            if (!pending) {
                setTimeout(update, ROW_ENTER_MILLISECONDS - pendingAge);
            }
        }

        table.classList.toggle('single-service', services.length < 2);
        container.replaceChildren(table);
    }

    const update = () => frame ??= requestAnimationFrame(render);

    return {
        span(span) {
            spans.set(span.id, span);
            latest = Math.max(latest, span.end ?? span.start);
            update();
        },
        step(step) {
            latest = Math.max(latest, step.at);
            update();
        },
        name(function_, name) {
            names.set(function_, name);
            namedAt.set(function_, performance.now());
            update();
        },
        event(event) {
            events.set(event.span, [...(events.get(event.span) ?? []), event]);
            update();
        },
        recordingEnded() {
            recordingEnded = true;
            update();
        },
        describingEnded() {
            describing = false;
            update();
        },
    };
}

const NAMING_PULSE_MS = 1600;
const NAMING_PULSE_STEP_MS = 120;

// The rows are rebuilt on each update, so the delay keeps the pulse in phase with the clock instead of restarting it.
function pulsePhase(index) {
    const phase = (performance.now() - index * NAMING_PULSE_STEP_MS) % NAMING_PULSE_MS;

    return phase < 0 ? phase + NAMING_PULSE_MS : phase;
}

const POLL_MILLISECONDS = 1000;

/**
 * Asks for the events that follow the cursor at intervals, for a server that cannot keep a stream open, or for an MCP
 * app, which gets them with a tool call. It has the part of the EventSource interface that the pages use.
 */
class PollingEventSource {
    #listeners = {};
    #closed = false;

    /**
     * @param {function(string): Promise<{events: Array, cursor: string, done: boolean}>} load the events after a cursor
     */
    constructor(load, cursor) {
        this.onerror = null;
        this.#poll(load, String(cursor));
    }

    addEventListener(type, listener) {
        (this.#listeners[type] ??= []).push(listener);
    }

    close() {
        this.#closed = true;
    }

    async #poll(load, cursor) {
        while (!this.#closed) {
            try {
                const response = await load(cursor);
                cursor = response.cursor;
                for (const [type, data] of response.events) {
                    for (const listener of this.#closed ? [] : this.#listeners[type] ?? []) {
                        listener({ data: JSON.stringify(data) });
                    }
                }
                if (response.done) {
                    this.close();
                }
            } catch (error) {
                // Like an EventSource, it tries again unless the error handler closes it
                this.onerror?.(error);
            }
            await new Promise(resolve => setTimeout(resolve, POLL_MILLISECONDS));
        }
    }
}

export function openEvents(url, { poll = false, cursor = 0 } = {}) {
    if (!poll) {
        return new EventSource(url);
    }
    url = new URL(url, location.href);
    return new PollingEventSource(async cursor => {
        url.searchParams.set('cursor', cursor);
        return (await fetch(url)).json();
    }, cursor);
}

function spinner(label = '') {
    const element = document.createElement('span');
    element.className = 'trace-spinner';
    element.setAttribute('role', 'status');
    element.setAttribute('aria-label', label || 'Loading');
    return element;
}

export function followTrace(id, panel, { describe = false, poll = false, load = null, base = baseUrl(), title = 'What happened', onRequest = () => {}, onStep = () => {}, onEnd = () => {}, onDone = () => {} } = {}) {
    panel.classList.add('trace-panel');
    panel.innerHTML = '<h2></h2><p class="trace-status"><span class="trace-status-text">Waiting for the trace…</span></p><div class="trace-waterfall"></div>';
    if (title) {
        panel.querySelector('h2').textContent = title;
    } else {
        panel.querySelector('h2').remove();
    }
    panel.querySelector('.trace-status').prepend(spinner());
    const status = panel.querySelector('.trace-status-text');
    const waterfall = createWaterfall(panel.querySelector('.trace-waterfall'), describe);
    let steps = 0;
    let label = '';

    const source = load
        ? new PollingEventSource(cursor => load(cursor, describe), 0)
        : openEvents(new URL(`${id}/events${describe ? '?describe=1' : ''}`, base), { poll });
    const on = (event, handler) => source.addEventListener(event, message => handler(JSON.parse(message.data)));

    on('request', request => {
        label = `${request.method} ${request.uri}`;
        status.textContent = `${label}: recording…`;
        console.groupCollapsed(`${request.method} ${request.uri}: calls of application methods`);
        onRequest(request);
    });
    on('span', span => waterfall.span(span));
    on('step', step => {
        steps++;
        status.textContent = `${label}: ${steps} calls recorded…`;
        console.log(`%c[${step.service}] ${step.file}:${step.line}%c ${step.code}`, 'color: gray', '', step.function);
        waterfall.step(step);
        onStep(step);
    });
    on('end', () => {
        console.groupEnd();
        waterfall.recordingEnded();
        status.textContent = `${label}: ${steps} calls.${describe ? ' Describing…' : ''}`;
        onEnd();
    });
    on('name', record => waterfall.name(record.function, record.name));
    on('event', event => waterfall.event(event));
    on('failure', message => {
        const error = panel.appendChild(document.createElement('p'));
        error.className = 'trace-error';
        error.textContent = `Describing the trace failed: ${message}`;
    });
    on('done', done => {
        source.close();
        waterfall.describingEnded();
        panel.querySelector('.trace-status .trace-spinner')?.remove();
        status.textContent = status.textContent.replace(/ Describing…$/, '');
        onDone();
    });
    source.onerror = () => {
        source.close();
        waterfall.describingEnded();
        panel.querySelector('.trace-status .trace-spinner')?.remove();
        onDone();
    };

    return source;
}
