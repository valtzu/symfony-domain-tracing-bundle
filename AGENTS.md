# AGENTS.md

This repository is a Symfony bundle, `valtzu/domain-tracing-bundle`. `composer.json` gives the versions of PHP,
Symfony and Symfony AI that it supports. `demo/` is a Symfony application that installs the bundle from `..`, to try
it out and to record the demo video.

## Layout

- `src/Xdebug/`: records a request with Xdebug and reads its trace files.
- `src/Storage/`: the files of the traces in `var/share/traces`, and the copies of the source in `var/blobs`.
- `src/Tracing/`: builds the spans of a trace, follows it while it grows, and has it described.
- `src/Ai/`: the AI agents, their prompts (`config/prompts/`) and the secret redaction.
- `src/Controller/`, `templates/`, `public/`: the profiler panel and the live page at `/_tracing/`.
- `config/services.php`: the services are wired explicitly, with `domain_tracing.` IDs, as in other bundles. Don't
  rely on autowiring or autoconfiguration in the bundle.

## Checks

Run them before you commit:

```shell
php vendor/bin/phpunit
vendor/bin/phpcs -q
vendor/bin/mago analyze
```

- The recorder tests run PHP with Xdebug in a separate process, so Xdebug must be installed.
- CI runs the tests on PHP 8.4 and 8.5, and with the lowest dependencies.

## Demo

```shell
docker compose up -d   # from the repository root: .env points to demo/compose.yaml
```

The shop is at http://localhost:8100/. Run its console in the container, such as
`docker compose exec shop bin/console cache:clear`. The AI names of the operations are cached in `cache.app`, which
`cache:clear` keeps: clear it with `cache:pool:clear cache.app` to see them generated again.

## Writing

These rules apply to comments, commit messages, docs and anything written for the team:

- Write plain, factual English, because many readers aren't native speakers. Write one idea per sentence and use
  verbs in their everyday sense. Repeat the noun instead of using a pronoun that makes the reader look back.
- State facts, not how good they are. Avoid words like "robust", "seamless", "powerful" or "simply", exclamation
  marks and emoji.
- Start with what changes for the reader, then explain why. Don't repeat what the code or the diff already shows.
- Use commas, colons and parentheses, not em-dashes.
- A commit message says what changed in one line, plus a sentence or two when the reason isn't obvious.

## Code

- Write a comment only for what the code can't say, such as a reason, a reference to a spec or a non-obvious
  constraint. Don't narrate the code.
- Fix a bug by writing a test that reproduces it first.
- Keep the diff to what the task needs: no unrelated renames, reformatting or refactoring.
- For a performance issue, measure it first, and keep the numbers.

## When a package gets in the way

These rules apply to bugs, missing features and slow code paths in Symfony, Symfony AI, Xdebug or any other package:

- Before you write a workaround, check that the problem is real. Reproduce it in a few lines, compare the installed
  version with the latest release, and search the issues and pull requests of the project.
- Keep the workaround small. Link the workaround to the upstream issue or pull request in a comment, so that it can
  be removed after the fix is released.
- Offer to report it upstream. Draft the issue with the reproduction or the numbers, or a pull request with a test
  when the fix is clear. Ask before posting anything, since it is published under the name of the person you work
  for.
- Report security issues privately, as the security policy of the project says, never in a public issue.
