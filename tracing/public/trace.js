const SERVICE_COLORS = 4;
const BASE_URL = new URL('/', import.meta.url);

/**
 * A waterfall of the spans of a trace, like the trace view of Jaeger, that grows while the trace is recorded.
 */
function createWaterfall(container) {
    const spans = new Map();
    const events = new Map();
    const names = new Map();
    const services = [];
    let latest = 0;
    let frame = null;

    const serviceColor = service => {
        if (!services.includes(service)) {
            services.push(service);
        }
        return services.indexOf(service) % SERVICE_COLORS;
    };

    const ordered = () => {
        const children = new Map();
        for (const span of spans.values()) {
            const parent = spans.has(span.parentId) ? span.parentId : null;
            children.set(parent, [...(children.get(parent) ?? []), span]);
        }
        const rows = [];
        const visit = (parent, depth) => {
            for (const span of (children.get(parent) ?? []).sort((a, b) => a.start - b.start)) {
                rows.push({ span, depth });
                visit(span.id, depth + 1);
            }
        };
        visit(null, 0);
        return rows;
    };

    const milliseconds = seconds => seconds * 1000 >= 10 ? `${(seconds * 1000).toFixed(0)} ms` : `${(seconds * 1000).toFixed(2)} ms`;

    function render() {
        frame = null;
        const rows = ordered();
        if (!rows.length) {
            return;
        }
        const start = Math.min(...rows.map(row => row.span.start));
        const range = Math.max(latest - start, ...rows.map(row => (row.span.end ?? latest) - start), 0.001);

        const table = document.createElement('div');
        table.className = 'waterfall';
        const axis = table.appendChild(document.createElement('div'));
        axis.className = 'waterfall-axis';
        axis.innerHTML = `<span></span><span class="waterfall-ticks">${[0, 0.25, 0.5, 0.75, 1].map(f => `<span style="left: ${f * 100}%">${milliseconds(f * range)}</span>`).join('')}</span>`;

        for (const { span, depth } of rows) {
            const end = span.end ?? latest;
            const spanEvents = events.get(span.id) ?? [];
            const failed = spanEvents.some(event => event.error);
            const row = table.appendChild(document.createElement('div'));
            row.className = `waterfall-row service-${serviceColor(span.service)}${failed ? ' failed' : ''}${null === span.end ? ' running' : ''}`;

            const label = row.appendChild(document.createElement('div'));
            label.className = 'waterfall-label';
            label.style.paddingLeft = `${depth * 14}px`;
            label.title = [
                span.function ?? span.uri ?? '',
                span.arguments ? `called with ${JSON.stringify(span.arguments)}` : '',
                'returnValue' in span ? `returned ${JSON.stringify(span.returnValue)}` : '',
            ].filter(Boolean).join('\n');
            label.innerHTML = '<span class="waterfall-service"></span><span class="waterfall-name"></span>';
            label.querySelector('.waterfall-service').textContent = span.service;
            label.querySelector('.waterfall-name').textContent = names.get(span.function) ?? span.name;

            const track = row.appendChild(document.createElement('div'));
            track.className = 'waterfall-track';
            const bar = track.appendChild(document.createElement('div'));
            bar.className = 'waterfall-bar';
            bar.style.left = `${(span.start - start) / range * 100}%`;
            bar.style.width = `max(2px, ${(end - span.start) / range * 100}%)`;
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
                marker.style.left = `${(event.at - start) / range * 100}%`;
                marker.title = event.text;
            }

            if (spanEvents.length) {
                const list = table.appendChild(document.createElement('ul'));
                list.className = 'waterfall-events';
                list.style.paddingLeft = `${depth * 14 + 18}px`;
                for (const event of spanEvents) {
                    const item = list.appendChild(document.createElement('li'));
                    item.className = event.error ? 'error' : '';
                    item.textContent = event.text;
                }
            }
        }

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
            update();
        },
        event(event) {
            events.set(event.span, [...(events.get(event.span) ?? []), event]);
            update();
        },
    };
}

export function followTrace(id, panel, { onRequest = () => {}, onStep = () => {}, onDone = () => {} } = {}) {
    panel.classList.add('trace-panel');
    panel.innerHTML = '<h2>What happened</h2><p class="trace-status">Waiting for the trace…</p><div class="trace-waterfall"></div><p class="trace-links"></p>';
    const status = panel.querySelector('.trace-status');
    const waterfall = createWaterfall(panel.querySelector('.trace-waterfall'));
    let steps = 0;

    const source = new EventSource(new URL(`_trace/${id}/events`, BASE_URL));
    const on = (event, handler) => source.addEventListener(event, message => handler(JSON.parse(message.data)));

    on('request', request => {
        status.textContent = `${request.method} ${request.uri}: recording…`;
        console.groupCollapsed(`${request.method} ${request.uri}: lines executed`);
        onRequest(request);
    });
    on('span', span => waterfall.span(span));
    on('step', step => {
        steps++;
        status.textContent = status.textContent.replace(/:.*/, `: ${steps} lines recorded…`);
        console.log(`%c[${step.service}] ${step.file}:${step.line}%c ${step.code}`, 'color: gray', '', step.function);
        waterfall.step(step);
        onStep(step);
    });
    on('end', () => {
        console.groupEnd();
        status.textContent = status.textContent.replace(/:.*/, `: ${steps} lines. Describing…`);
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
        status.textContent = status.textContent.replace(/ Describing…$/, '');
        if (done.jaegerUrl) {
            const link = panel.querySelector('.trace-links').appendChild(document.createElement('a'));
            link.href = done.jaegerUrl;
            link.target = '_blank';
            link.textContent = 'Open in Jaeger';
        }
        onDone();
    });
    source.onerror = () => source.close();

    return source;
}
