# Reference IngestHandler Implementation — Plan Brief

> Full plan: `context/changes/ingest-handler-reference-impl/plan.md`

## What & Why

The install snippet returned by the ingestion-key endpoints references `App\MonitoringWebowi\Handler\IngestHandler` — a class that doesn't exist. This plan builds that class (and the small transport it needs) for real, tests it to this repo's normal bar, and makes the snippet embed the actual tested source instead of an aspirational stub — so a user copy-pasting the snippet gets exactly what's been verified to work.

## Starting Point

`InstallSnippetBuilder::build()` (`src/Projects/Application/GetIngestionKey/InstallSnippetBuilder.php`) returns Monolog YAML wiring plus a comment block naming a nonexistent class. All three consumers (`GenerateIngestionKeyHandler`, `GetIngestionKeyHandler`, `RotateIngestionKeyHandler`) mock the builder entirely, so this change is isolated to the builder itself plus two new classes.

## Desired End State

A real Symfony app can paste the snippet's contents in, wire up its ingestion key, and have its error-level logs show up via `GET /api/v1/projects/{uuid}/logs` — without ever blocking or crashing on a bad key or unreachable endpoint.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| HTTP transport | Sync curl, short timeouts (~200/300ms), swallow all errors | Bounded worst-case cost, zero dependencies, matches fail-open product requirement |
| Testability seam | Injectable `TransportInterface` + default `CurlTransport` | Clean DI, fully mockable in `IngestHandler` tests without real network calls |
| Context serialization | Reuse Monolog's `NormalizerFormatter` | Battle-tested handling of exceptions/objects/resources in log context |
| Snippet embedding | `InstallSnippetBuilder` reads the real files from disk at build time | Single source of truth — impossible for snippet and tested code to drift |
| Level filtering | Self-contained via `AbstractProcessingHandler`'s `$level`/`$bubble` | Correct even if wired without the documented YAML `fingers_crossed` wrapper |
| Failure visibility | Silent by default, optional `?callable $onFailure` escape hatch | Matches the product's own freshness-indicator design; still debuggable if needed |
| Backoff on repeated failure | None — every call attempts independently | Simplicity for a reference class; bounded by the short timeout anyway |
| Definition of done | Automated tests + one manual end-to-end pass | Automated tests can't catch a wrong URL/header/content-type — only a real request can |

## Scope

**In scope:**
- `TransportInterface`, `TransportException`, `CurlTransport` (`src/MonitoringWebowi/Handler/Transport/`)
- `IngestHandler` (`src/MonitoringWebowi/Handler/IngestHandler.php`)
- `InstallSnippetBuilder` change to embed real source + its test + `services.yaml` binding
- One manual end-to-end verification pass

**Out of scope:**
- A distributable/composer-installable package or bundle (explicitly deferred by the roadmap)
- Retry/backoff/circuit-breaker logic
- Non-blocking async dispatch (e.g. `curl_multi`)
- Any change to `CreateProjectHandler` or the three existing ingestion-key handlers' logic
- New Behat coverage (server-side ingest behavior is unchanged)

## Architecture / Approach

Two new classes live under `src/MonitoringWebowi/Handler/` — outside the existing DDD bounded contexts, since this is client-side reference code, not part of this app's own domain. `IngestHandler` depends on `TransportInterface` purely for testability; `CurlTransport` is tested against a real local `php -S` fixture rather than mocks, since it's the one piece that has to prove an actual HTTP round-trip. `InstallSnippetBuilder` reads both files' real contents at build time and inlines them into the returned snippet.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Transport layer | `TransportInterface` + `CurlTransport`, tested against a real local server | Curl timeout/error-mapping edge cases |
| 2. IngestHandler | The handler class, tested against a mocked transport | Context/extra serialization safety (exceptions, non-scalars) |
| 3. Snippet embedding | `InstallSnippetBuilder` inlines real source; test + wiring updated | File path resolution (`%kernel.project_dir%`) must be correct in all environments |
| 4. Manual E2E pass | Confirmed real round-trip using the already-generated project/key | None — this phase exists specifically to catch what automation can't |

**Prerequisites:** None — all groundwork (ingestion key generation, ingest endpoint, listing endpoint) already exists and is implemented.
**Estimated effort:** ~1 session across 4 phases.

## Open Risks & Assumptions

- Assumes `%kernel.project_dir%` resolves correctly in every environment the builder runs in (dev, test, prod) — if this ever diverges, the snippet would silently embed a read failure. Worth a defensive check when implementing.
- Assumes host Symfony apps have `ext-curl` available — a safe assumption for any Symfony app but not a build-time-guaranteed dependency.

## Success Criteria (Summary)

- A real log line, sent from pasted-in reference code with nothing but a valid key and URL, is retrievable via this app's own API.
- `make phpunit` and `make infection` (100% MSI) pass for all new code.
- The snippet returned by the ingestion-key endpoints contains real, working class bodies — not a stub.
