import mermaid from 'https://cdn.jsdelivr.net/npm/mermaid@11.4.1/dist/mermaid.esm.min.mjs';

mermaid.initialize({
    startOnLoad: false,
    theme: matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'default',
    sequence: { wrap: true, width: 180 },
});

let diagramCount = 0;

/**
 * Builds a sequence diagram from statements that arrive one line at a time. A line is kept only when the diagram
 * still parses with it, so one bad line from the model does not break the whole diagram.
 */
function createDiagram(container) {
    const statements = [];
    let buffer = '';
    let queue = Promise.resolve();

    const source = () => ['sequenceDiagram', ...statements].join('\n');

    async function addLine(line) {
        line = line.trim();
        if ('' === line || line.startsWith('```') || 'sequenceDiagram' === line) {
            return;
        }
        if (false === await mermaid.parse(['sequenceDiagram', ...statements, line].join('\n'), { suppressErrors: true })) {
            console.warn('Skipped a diagram line that does not parse:', line);
            return;
        }
        statements.push(line);
        const { svg } = await mermaid.render(`trace-diagram-${++diagramCount}`, source());
        container.innerHTML = svg;
    }

    return {
        write(text) {
            buffer += text;
            const lines = buffer.split('\n');
            buffer = lines.pop();
            for (const line of lines) {
                queue = queue.then(() => addLine(line));
            }
        },
        flush() {
            const line = buffer;
            buffer = '';
            queue = queue.then(() => addLine(line));
        },
        source,
    };
}

export function followTrace(id, panel, { onRequest = () => {}, onStep = () => {}, onDone = () => {} } = {}) {
    panel.classList.add('trace-panel');
    panel.innerHTML = '<h2>What happened</h2><p class="trace-status">Waiting for the trace…</p><div class="trace-diagram"></div>';
    const status = panel.querySelector('.trace-status');
    const diagram = createDiagram(panel.querySelector('.trace-diagram'));
    let steps = 0;

    const source = new EventSource(`/_trace/${id}/events`);
    const on = (event, handler) => source.addEventListener(event, message => handler(JSON.parse(message.data)));

    on('request', request => {
        status.textContent = `${request.method} ${request.uri}: recording…`;
        console.groupCollapsed(`${request.method} ${request.uri}: lines executed in src/`);
        onRequest(request);
    });
    on('span', span => console.log(`%c→ ${span.service} received ${span.method} ${span.uri}`, 'font-weight: bold'));
    on('step', step => {
        steps++;
        status.textContent = status.textContent.replace(/:.*/, `: ${steps} lines recorded…`);
        console.log(`%c[${step.service}] ${step.file}:${step.line}%c ${step.code}`, 'color: gray', '', step.function);
        onStep(step);
    });
    on('end', end => {
        console.groupEnd();
        status.textContent = status.textContent.replace(/:.*/, `: ${steps} lines in ${end.durationMs} ms. Drawing…`);
    });
    on('text', chunk => diagram.write(chunk));
    on('part', () => diagram.flush());
    on('failure', message => {
        const error = panel.appendChild(document.createElement('p'));
        error.className = 'trace-error';
        error.textContent = `Explanation failed: ${message}`;
    });
    on('done', () => {
        source.close();
        diagram.flush();
        status.textContent = status.textContent.replace(/ Drawing…$/, '');
        console.log(diagram.source());
        onDone();
    });
    source.onerror = () => source.close();

    return source;
}
