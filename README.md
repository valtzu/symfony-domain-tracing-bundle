# symfony-ai-stacktrace

Business-level traces of PHP requests, without instrumenting the code.

## What

A recorder attaches to each request through [PHP Debugger](https://php-debugger.dev/) (DBGp), records every line of
application code that runs, with the arguments and return values of each method, and follows the request across
services. A worker turns the recording into OpenTelemetry spans, gives each method a business name ("Apply
discounts", "Charge card") and describes what happened in it ("Card declined: insufficient funds") with
[Symfony AI](https://symfony.com/doc/current/ai/index.html) and Claude Code. The result streams into a waterfall
next to the page while the request runs, and ends up in Jaeger.

## Why

Logs and traces show what the code was told to report. Support and product people want to know what the system
decided for a customer and why. Here the trace comes from the code that actually ran, so it needs no log statements,
no spans in the code and no SDK in the services, and the AI only adds the names and the story on top.

## Quickstart

Requirements: Docker, and the [Claude Code CLI](https://code.claude.com/docs/en/overview) logged in on the host.
The worker uses `~/.local/bin/claude` and `~/.claude/.credentials.json`; set `CLAUDE_CLI` and `CLAUDE_CREDENTIALS`
when they are somewhere else.

```shell
docker compose build
docker compose run --rm --no-deps php composer install
docker compose run --rm --no-deps recorder composer install
docker compose up -d
```

Then place an order in the demo shop and watch the panel under the page.

| What | URL |
|---|---|
| Demo shop | http://localhost:8100 |
| Live traces | http://localhost:8200 |
| Jaeger | http://localhost:16686 |

`orders.http` has one request per checkout scenario: paid, held for review, and the different rejections.

## How it is put together

- `src/`: the demo shop, and a payments service that the shop calls over HTTP. Same codebase, two containers,
  no tracing code.
- `tracing/`: the tracing app, with its own image.
  - `recorder` is the DBGp client. It reads the code of each service through the debugger, so it needs no access to
    it, sets the `X-Request-Id` and adds the `traceparent` header to outgoing Symfony HttpClient requests. A trace
    refers to code by git blob ID and line.
  - `tracing-worker` builds the spans, names the operations (once per method and code version), describes the
    request, removes secrets before anything goes to the AI, and exports to Jaeger with OTLP. It reads the source
    from the git repositories in `TRACE_GIT_REPOSITORIES`, including uncommitted files.
  - `tracing` serves the live view and the event stream for the panel.

A service only needs the PHP Debugger extension pointed at the recorder and an `OTEL_SERVICE_NAME`.

Set `RECORDER_STEP_DELAY=400` to slow every recorded line down, as when stepping through with a debugger, and watch
the trace grow.

## Limits

- Development only: recording makes requests about 25 times slower. Loading the extension alone costs little.
  Median of 50 requests in the dev environment:

  | | `GET /` (shop) | `POST /` (shop and payments) |
  |---|---|---|
  | Extension not loaded | 3.9 ms | 7.9 ms |
  | Loaded, `php_debugger.mode=off` | 3.9 ms | 8.0 ms |
  | Loaded, no client listening | 5.0 ms | 10.0 ms |
  | Recorded | 90 ms | 220 ms |

- The times in a trace are not the real times. The time stopped in the debugger is subtracted, but recorded durations
  are still about 20 times too long (80 ms for `GET /`, 193 ms for `POST /`). The order of the operations is right.
- Only Symfony HttpClient carries the trace to other services.
- A return value is known only when the caller uses it; PHP discards an unused one.
