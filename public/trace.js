export function followTrace(id, panel, { onRequest = () => {}, onStep = () => {}, onDone = () => {} } = {}) {
    panel.classList.add('trace-panel');
    panel.innerHTML = '<h2>What happened</h2><p class="trace-status">Waiting for the trace…</p><div class="trace-text"></div>';
    const status = panel.querySelector('.trace-status');
    const text = panel.querySelector('.trace-text');
    let paragraph = null;
    let steps = 0;

    const source = new EventSource(`/_trace/${id}/events`);
    const on = (event, handler) => source.addEventListener(event, message => handler(JSON.parse(message.data)));

    on('request', request => {
        status.textContent = `${request.method} ${request.uri}: recording…`;
        console.groupCollapsed(`${request.method} ${request.uri}: lines executed in src/`);
        onRequest(request);
    });
    on('step', step => {
        steps++;
        status.textContent = status.textContent.replace(/:.*/, `: ${steps} lines recorded…`);
        console.log(`%c[${step.service}] ${step.file}:${step.line}%c ${step.code}`, 'color: gray', '', step.function);
        onStep(step);
    });
    on('span', span => console.log(`%c→ ${span.service} received ${span.method} ${span.uri}`, 'font-weight: bold'));
    on('end', end => {
        console.groupEnd();
        status.textContent = status.textContent.replace(/:.*/, `: ${steps} lines in ${end.durationMs} ms. Explaining…`);
    });
    on('text', chunk => {
        paragraph ??= text.appendChild(document.createElement('p'));
        paragraph.textContent += chunk;
    });
    on('part', () => paragraph = null);
    on('failure', message => {
        const error = text.appendChild(document.createElement('p'));
        error.className = 'trace-error';
        error.textContent = `Explanation failed: ${message}`;
    });
    on('done', () => {
        source.close();
        status.textContent = status.textContent.replace(/ Explaining…$/, '');
        onDone();
    });
    source.onerror = () => source.close();

    return source;
}
