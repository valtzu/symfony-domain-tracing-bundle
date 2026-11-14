# Domain Tracing Bundle

Business-level traces of PHP requests, without instrumenting the code.

https://github.com/user-attachments/assets/732670c3-dbde-4fd5-b72f-622840ddfd48


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

Requirements: Symfony 8.1 or later, Xdebug for the PHP of the web server, and a git repository for the code. To
describe the traces with AI, an agent of the [AI bundle](https://symfony.com/doc/current/ai/index.html), such as one
that uses the [Claude Code CLI](https://code.claude.com/docs/en/overview).

1. Require the bundle as a dev dependency. Symfony Flex enables it in the `dev` and `test` environments, with the
   web profiler. The bundle needs no configuration to record and show the traces. [Symfony AI](https://symfony.com/doc/current/ai/index.html) has only beta releases yet, so a project
   that accepts only stable packages, as a new Symfony project does, must allow betas first. With `prefer-stable`, the
   other packages stay stable.

   ```shell
   composer config minimum-stability beta
   composer require --dev valtzu/domain-tracing-bundle
   ```

2. Enable the trace and code coverage modes of Xdebug in the `php.ini` of the web server, and restart it:

   ```ini
   [xdebug]
   xdebug.mode=trace,coverage
   ```

   The bundle writes its traces into `var/share/traces`, and sets the other Xdebug settings that it needs while it records a request. Your own Xdebug
   settings, such as `xdebug.output_dir`, stay as they are. With the [Symfony CLI](https://symfony.com/download),
   put the settings into the `php.ini` of the project directory, which `symfony serve` and `symfony console` read.

   Xdebug runs one trace at a time. When it traces a request already, because of `xdebug.start_with_request=yes` or
   a trigger, the bundle leaves that trace as it is and reads the request from it, if it is in the trace format 1
   (`xdebug.trace_format=1`). In another format, the request is not recorded, and the profiler panel tells why.

3. Open a page of the application, then its "Domain traces" panel in the profiler. When the request was not recorded,
   the panel tells why. `/_tracing/` shows the requests that run application code, live. The pages of the bundle and
   of the profiler are never recorded.

   A console command is recorded only when it runs with `--profile`, like its profile. Its trace is in the "Domain
   traces" panel of that profile.

When a request calls other services with Symfony HttpClient, the bundle adds the `traceparent` header, unless the
request already has one, from OpenTelemetry for example. A service records its requests in the same trace when it
has the bundle too and writes its traces into the same directory. Set `OTEL_SERVICE_NAME` in the environment of each
service to name it; otherwise a service is named after its project directory.

The AI agents name the operations and describe the requests with general instructions, in
[`config/prompts/`](config/prompts/) of the bundle. To get the words of your business in the traces, describe the
application in `config/prompts/domain_tracing_context.md` of the project. The agents get this description after their
instructions:

```text
The application is a web shop. The people who read the traces work in customer support.
Use the words of the shop: customers, products, prices, discounts, stock and payment.
```

`bin/console domain-tracing:context:generate` writes a first version of this file. It sends the README, the routes and
the class names of the application to an AI agent, prints the description, and asks before it writes the file. With
`--write`, it writes the file without asking, and with `--no-interaction` alone, it only prints the description. Read
the description before you commit it. The `context_file` option changes the path of the file:

```yaml
# config/packages/domain_tracing.yaml
when@dev:
    domain_tracing:
        context_file: '%kernel.project_dir%/config/prompts/domain_tracing_context.md'
```

### The AI agent

The application defines the agent that names the operations and describes the traces, and its platform. Without
the agent, the traces are recorded and shown, but not described. The bundle uses the `domain_tracing` agent. With the
Claude Code CLI (logged in, as `claude` on the `PATH`), install its platform:

```shell
composer require --dev symfony/ai-claude-code-platform
```

The AI bundle has no option for this platform yet, so the application defines it as a service:

```yaml
# config/packages/ai.yaml
when@dev:
    services:
        claude_code.platform:
            class: Symfony\AI\Platform\Platform
            factory: ['Symfony\AI\Platform\Bridge\ClaudeCode\Factory', 'createPlatform']
            arguments:
                $workingDirectory: '%kernel.project_dir%/var'
                $timeout: 600

    ai:
        agent:
            domain_tracing:
                platform: claude_code.platform
                model:
                    name: opus
                    options:
                        tools: []
```

Any other platform of the AI bundle that can stream the answer works too, such as `ai.platform.anthropic` with
`model: claude-sonnet-5-5`. The `agent` option names another agent, and `agent: false` turns the description off:

```yaml
# config/packages/domain_tracing.yaml
when@dev:
    domain_tracing:
        agent: my_agent
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
  requests that are not recorded are not traced. The trace starts as a file of the bundle, so the
  Xdebug settings and the traces of the project are left as they are.
- The event stream of the live view and of the profiler panel reads the trace files while Xdebug writes them, from
  the first method of the entrypoint on. One stream reads each trace file, and another one takes over when it stops.
- The stream of the profiler panel also builds the spans, names the operations (once per method and code version),
  describes the request, and removes secrets before anything goes to the AI. It reads the source from the git
  repositories in `TRACE_GIT_REPOSITORIES` (the project directory by default), including uncommitted files.

In the demo, both services write their traces into the same `traces` volume, mounted at `var/share/traces`.

## Limits

- Development only: recording makes requests about 4 times slower. Median of 50 requests in the dev environment of
  the demo, with the web profiler enabled and no page open that follows the traces, in six runs that alternate
  between the two settings:

  | | `GET /` (shop) | `POST /` (shop and payments) |
  |---|---|---|
  | `XDEBUG_MODE=off` | 8.9–16 ms | 19–42 ms |
  | Recorded | 36–59 ms | 83–119 ms |

  The time goes to the Xdebug trace and code coverage.

  An earlier version recorded every line with [PHP Debugger](https://php-debugger.dev/) and was about 25 times
  slower (90 ms and 220 ms): a connected debugger disables OPcache for the request, and every breakpoint is checked
  on each function call.
- The lines that ran come from the code coverage of the whole request. When a method runs more than once in a request,
  its span shows the lines of all its calls together.
- The times in a trace include the overhead of Xdebug.
- With `xdebug.start_with_request=yes`, Xdebug traces the whole request, the framework included, and the bundle
  reads its trace in that case, so a recorded request is much slower than with the trace that the bundle starts.
- The record starts when the kernel handles the request, so application code that runs before, such as the kernel
  class or a request listener with a priority above 4096, is not in the trace.
- Only Symfony HttpClient carries the trace to other services.
- A return value is known only when the caller uses it; PHP discards an unused one.
