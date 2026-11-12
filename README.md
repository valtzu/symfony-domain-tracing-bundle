# symfony-ai-stacktrace

Business-level traces of PHP requests, without instrumenting the code.

## What

[Xdebug](https://xdebug.org/) traces the calls of the application methods of each request, with their arguments,
return values and times, and records which lines of them ran. A recorder that takes part in each request as a
debugging client (DBGp) follows the request across services. A worker turns the recording into OpenTelemetry spans, gives each method a business name ("Apply
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

## How it is put together

- The root directory is the tracing app.
- `demo/`: the demo shop, and a payments service that the shop calls over HTTP. Same codebase, two containers,
  no tracing code. It has its own image.
- The tracing app runs as three containers:
  - `recorder` is the DBGp client. It reads the code of each service through the debugger, so it needs no access to
    it, sets the `X-Request-Id`, and adds the `traceparent` header to outgoing Symfony HttpClient requests. It only
    stops a request at these HTTP calls. It reads the trace file that Xdebug writes for the request into a shared
    directory, from the first method of the entrypoint on. A trace refers to code by git blob ID and line.
  - `tracing-worker` builds the spans, names the operations (once per method and code version), describes the
    request, removes secrets before anything goes to the AI, and exports to Jaeger with OTLP. It reads the source
    from the git repositories in `TRACE_GIT_REPOSITORIES`, including uncommitted files.
  - `tracing` serves the live view and the event stream for the panel.

A service needs the Xdebug settings of `demo/frankenphp/conf.d/xdebug.dev.ini` with the prepend file that limits the
trace and the code coverage to the application and writes the lines that ran next to the trace, `XDEBUG_CONFIG=client_host=recorder`, the shared trace directory mounted at
`/traces`, and an `OTEL_SERVICE_NAME`.

## Limits

- Development only: recording makes requests about 5 times slower. Median of 50 requests in the dev environment:

  | | `GET /` (shop) | `POST /` (shop and payments) |
  |---|---|---|
  | No extension | 3.9 ms | 7.9 ms |
  | Recorded | 18–25 ms | 44–52 ms |

  An earlier version recorded every line with [PHP Debugger](https://php-debugger.dev/) and was about 25 times
  slower (90 ms and 220 ms): a connected debugger disables OPcache for the request, and every breakpoint is checked
  on each function call.
- The lines that ran come from the code coverage of the whole request. When a method runs more than once in a request,
  its span shows the lines of all its calls together.
- The times in a trace include the overhead of Xdebug.
- Only Symfony HttpClient carries the trace to other services.
- A return value is known only when the caller uses it; PHP discards an unused one.
