# symfony-domain-tracing-bundle

Business-level traces of PHP requests, without instrumenting the code.

https://github.com/user-attachments/assets/39e56c64-3818-459c-b766-c72d5c74a27f

## What

[Xdebug](https://xdebug.org/) traces the calls of the application methods of each request, with their arguments,
return values and times, and records which lines of them ran. The bundle reads the trace files while Xdebug writes
them, follows the request across services with the W3C `traceparent` header, turns the recording into spans, gives
each method a business name ("Apply discounts", "Charge card") and describes what happened in it ("Card declined:
insufficient funds") with [Symfony AI](https://symfony.com/doc/current/ai/index.html) and Claude Code. The result
streams into a waterfall while the request runs. No other process runs next to the application.

## Why

Logs and traces show what the code was told to report. Support and product people want to know what the system
decided for a customer and why. Here the trace comes from the code that actually ran, so it needs no log statements,
no spans in the code and no SDK in the services, and the AI only adds the names and the story on top.

## Quickstart

Requirements: Docker, and the [Claude Code CLI](https://code.claude.com/docs/en/overview) logged in on the host.
The shop container uses `~/.local/bin/claude` and `~/.claude/.credentials.json`; set `CLAUDE_CLI` and
`CLAUDE_CREDENTIALS` when they are somewhere else.

```shell
docker compose up -d
```

Then open the live traces and place an order in the demo shop.

| What | URL |
|---|---|
| Demo shop | http://localhost:8100 |
| Live traces | http://localhost:8100/_tracing/ |
| Trace of one request | the "Domain traces" panel of the Symfony profiler |

## Install in an application

Requirements: Symfony 8.1 or later, Xdebug for the PHP of the web server, a git repository for the code, and the
[Claude Code CLI](https://code.claude.com/docs/en/overview) logged in, as `claude` on the `PATH`.

1. Require the bundle as a dev dependency. Symfony Flex enables it in the `dev` and `test` environments, with the
   web profiler. The bundle needs no configuration, and the empty `config/packages/ai.yaml` of the AI bundle can stay
   as it is. [Symfony AI](https://symfony.com/doc/current/ai/index.html) has only beta releases yet, so a project
   that accepts only stable packages, as a new Symfony project does, must allow betas first. With `prefer-stable`, the
   other packages stay stable.

   ```shell
   composer config minimum-stability beta
   composer require --dev valtzu/domain-tracing-bundle
   ```

2. Create the trace directory, and add the Xdebug settings to the `php.ini` of the web server. The command prints
   them with the paths of the application. Restart the web server after that.

   ```shell
   mkdir -p var/share/traces
   bin/console domain-tracing:xdebug-settings
   ```

   With the [Symfony CLI](https://symfony.com/download), write them into the `php.ini` of the project directory
   instead, which `symfony serve` and `symfony console` read:

   ```shell
   mkdir -p var/share/traces
   symfony console domain-tracing:xdebug-settings > php.ini
   ```

3. Open a page of the application, then its "Domain traces" panel in the profiler. When the request was not recorded,
   the panel tells why. `/_tracing/` shows the requests live.

   A console command is recorded only when it runs with `--profile`, like its profile. Its trace is in the "Domain
   traces" panel of that profile.

When a request calls other services with Symfony HttpClient, the bundle adds the `traceparent` header, unless the
request already has one, from OpenTelemetry for example. A service records its requests in the same trace when it
has the bundle too and writes its traces into the same directory. Set `OTEL_SERVICE_NAME` in the environment of each
service to name it; otherwise a service is named after its project directory.

The `exclude_uris` option lists the requests not to record, as regular expressions matched against the path and the
query. The pages of the bundle and of the profiler are never recorded.

```yaml
# config/packages/domain_tracing.yaml
when@dev:
    domain_tracing:
        exclude_uris:
            - '^/health$'
```

The AI agents name the operations and describe the requests with general instructions, in
[`config/prompts/`](config/prompts/). To get the words of your business in the traces, describe the application in
`config/domain_tracing.md`. The agents get this description after their instructions:

```text
The application is a web shop. The people who read the traces work in customer support.
Use the words of the shop: customers, products, prices, discounts, stock and payment.
```

`bin/console domain-tracing:context:generate` writes a first version of this file. It sends the README, the routes and
the class names of the application to an AI agent. Read the description before you commit it. The `context_file`
option changes the path of the file.

The agents use Claude Code with the `opus` model. To use another platform of the AI bundle, one that can stream the
answer, configure the agents of the bundle in `config/packages/ai.yaml`:

```yaml
ai:
    agent:
        domain_tracing_operation_namer: &domain_tracing_agent
            platform: ai.platform.anthropic
            model: claude-sonnet-5-5
        domain_tracing_trace_narrator: *domain_tracing_agent
        domain_tracing_context_writer: *domain_tracing_agent
```

## Traces in the terminal

`bin/console debug:domain:trace` prints a trace while it is recorded, one line per operation, indented under its
caller. Coding agents can read it, too: run it in the background, send the request, and read the output while it
comes.

```shell
bin/console debug:domain:trace next                   # waits for the next request
bin/console debug:domain:trace latest --values        # with the arguments and the return values
bin/console debug:domain:trace 3f2a9c1b7d4e8a60 --describe   # with the events that the AI writes
```

```text
  +0.000s POST / [shop]
            → Check out cart  App\Controller\ShopController->checkout
  +0.010s   Place order [shop]  App\Checkout\Checkout->placeOrder
  +0.010s     Apply discounts [shop]  App\Checkout\Pricing->applyDiscounts
                arguments: {"couponCode":"WELCOME10", …}
  +0.011s     Charge payment [shop]  App\Checkout\PaymentGateway->charge
  +0.032s       POST /charges [payments]
  +0.085s end of POST / [shop] after 0.085s
  +0.011s       • Coupon WELCOME10 accepted because the subtotal of 178.00 meets the 50.00 minimum
```

- The argument is the ID of a request, as the profiler shows it, `latest` (the default) or `next`.
- `--describe` asks the AI to describe the trace, unless it is described already. The events appear when the AI
  writes them, after the operations they are about.
- `--json` prints each event as a line of JSON, as the live view receives it.
- The values are redacted like the values that the AI gets.

To tell a coding agent about it, add a line like this to the `AGENTS.md` or `CLAUDE.md` of the project:

```markdown
To see what a request did, run `bin/console debug:domain:trace next --values` in the background before you send
it, then read the output. Add `--describe` for a description in business terms.
```

## How it is put together

- The root directory is a Symfony bundle, `DomainTracingBundle`. In debug mode, it serves the live view and its event
  stream under `/_tracing/`, and adds a "Domain traces" panel to the profiler.
- `demo/`: the demo shop, and a payments service that the shop calls over HTTP. Same codebase, two containers,
  no tracing code. It requires the bundle from the parent directory.
- A listener of the bundle starts the record of each main request, and of each console command that runs with
  `--profile`, when the kernel handles it. It takes the trace context of the `traceparent` header or starts a trace,
  starts the Xdebug trace and the code coverage, limited to the application, and writes the record of the request
  next to the trace file. When the request ends, it writes the lines that ran, the response status and the exception
  that the request did not handle, stops the trace, and writes an index of the application code once per version of
  the code. A trace refers to code by git blob ID and line. The bundle needs no `auto_prepend_file`, and the
  requests that are not recorded are not traced.
- The event stream of the live view and of the profiler panel reads the trace files while Xdebug writes them, from
  the first method of the entrypoint on. One stream reads each trace file, and another one takes over when it stops.
- The stream of the profiler panel also builds the spans, names the operations (once per method and code version),
  describes the request, and removes secrets before anything goes to the AI. It reads the source from the git
  repositories in `TRACE_GIT_REPOSITORIES` (the project directory by default), including uncommitted files.

In the demo, both services write their traces into the same `traces` volume, mounted at `var/share/traces`.

## Limits

- Development only: recording makes requests about 4 times slower. Median of 50 requests in the dev environment of
  the demo, with the web profiler enabled, in three runs:

  | | `GET /` (shop) | `POST /` (shop and payments) |
  |---|---|---|
  | No Xdebug | 7.1–7.9 ms | 16–19 ms |
  | Recorded | 24–29 ms | 53–65 ms |

  The time goes to the Xdebug trace and code coverage.

  An earlier version recorded every line with [PHP Debugger](https://php-debugger.dev/) and was about 25 times
  slower (90 ms and 220 ms): a connected debugger disables OPcache for the request, and every breakpoint is checked
  on each function call.
- The lines that ran come from the code coverage of the whole request. When a method runs more than once in a request,
  its span shows the lines of all its calls together.
- The times in a trace include the overhead of Xdebug.
- The record starts when the kernel handles the request, so application code that runs before, such as the kernel
  class or a request listener with a priority above 4096, is not in the trace.
- Only Symfony HttpClient carries the trace to other services.
- A return value is known only when the caller uses it; PHP discards an unused one.
