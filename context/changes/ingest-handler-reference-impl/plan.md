# Reference IngestHandler Implementation — Implementation Plan

## Overview

`InstallSnippetBuilder` currently emits a Monolog wiring snippet that *references* a class, `App\MonitoringWebowi\Handler\IngestHandler`, which doesn't exist anywhere — the snippet is aspirational text with no real code behind it. This plan builds that class for real (plus a small transport layer it depends on), covers it with unit tests to this repo's normal mutation-testing bar, and changes `InstallSnippetBuilder` to embed the *actual, tested* source into the snippet it returns — so there is exactly one copy of this code, not a maintained-by-hand duplicate.

## Current State Analysis

- `src/Projects/Application/GetIngestionKey/InstallSnippetBuilder.php` returns a heredoc string: Monolog YAML wiring + a comment block referencing `App\MonitoringWebowi\Handler\IngestHandler` with `$url`/`$apiKey` constructor args. No such class exists in the codebase.
- Three handlers consume `InstallSnippetBuilder::build()`: `GenerateIngestionKeyHandler`, `GetIngestionKeyHandler`, `RotateIngestionKeyHandler` — all three mock `InstallSnippetBuilder` entirely in their tests (`tests/Unit/Projects/Application/{GenerateIngestionKey,GetIngestionKey,RotateIngestionKey}/*HandlerTest.php`), so changing the builder's internal output format does not require touching those tests.
- `InstallSnippetBuilderTest` (`tests/Unit/Projects/Application/GetIngestionKey/InstallSnippetBuilderTest.php`) directly asserts on the returned string (key value present, ingestion URL present, trailing-slash stripped) — this test needs new assertions for the embedded class source.
- The server-side ingest contract is `POST /api/v1/logs/ingest` (`src/Logging/Ui/Ingest/IngestLogController.php`), authenticated via the `X-Ingestion-Key` header (`src/Projects/Infrastructure/Security/IngestionKeyAuthenticator.php`), expecting a JSON body matching `IngestLogInput`: `datetime` (ISO-8601/ATOM), `level` (one of `LogSeverityEnum`'s lowercase PSR-3 values), `message` (string), `context` (array, optional).
- Monolog 3.5 is installed (`composer.lock`), so records are `Monolog\LogRecord` value objects and severities are the `Monolog\Level` backed enum — `$record->level->toPsrLogLevel()` maps directly onto `LogSeverityEnum`'s values with no translation table.
- `App\` autoloads from `src/` (PSR-4, `composer.json`), so a class declared as `App\MonitoringWebowi\Handler\IngestHandler` must physically live at `src/MonitoringWebowi/Handler/IngestHandler.php` to autoload in this codebase — this reuses the exact FQCN the snippet already references today, so no rename is needed anywhere.
- `config/services.yaml` already binds `$appUrl: '%env(APP_URL)%'` onto `InstallSnippetBuilder` — the same binding pattern will carry the new `%kernel.project_dir%` argument.
- Server-side ingestion behavior (auth, rate limiting, listing) is already covered by `tests/Behat/Features/Logging/ingestAndList.feature` and `ingestionAuth.feature` — this change does not touch that surface and does not need new Behat coverage.

## Desired End State

- `src/MonitoringWebowi/Handler/Transport/TransportInterface.php`, `CurlTransport.php`, and `TransportException.php` exist, fully unit-tested.
- `src/MonitoringWebowi/Handler/IngestHandler.php` exists, extends `Monolog\Handler\AbstractProcessingHandler`, and is fully unit-tested against a mocked `TransportInterface`.
- `InstallSnippetBuilder::build()` returns a snippet whose embedded PHP code is read verbatim from these two real files — never hand-duplicated.
- A real Symfony app, with nothing but the snippet's contents pasted in and a valid ingestion key, can send a log line that shows up via `GET /api/v1/projects/{uuid}/logs` — verified manually once against the running dev stack.
- `make phpunit` and `make infection` (100% MSI / covered-MSI) both pass including the new files.

### Key Discoveries:

- `AbstractProcessingHandler`'s constructor already provides level filtering and bubbling for free (`parent::__construct($level, $bubble)`) — no custom filtering logic needed.
- `Monolog\Formatter\NormalizerFormatter::normalize()` safely flattens exceptions/objects/resources found in `$record->context` / `$record->extra` into JSON-safe scalars — reused rather than reinvented.
- All three existing handler tests mock `InstallSnippetBuilder`, so Phase 3's format change is isolated to `InstallSnippetBuilderTest` only.

## What We're NOT Doing

- Not building a distributable/composer-installable package or bundle — this stays a copy-pasteable reference class, matching the roadmap's stated current-phase scope ("today a paste-in Monolog snippet... a real bundle later").
- Not implementing retry, backoff, or a circuit breaker for repeated ingestion failures — every `write()` call attempts the request independently, bounded by a short timeout (confirmed decision — chatty failure loops are an accepted tradeoff for reference-implementation simplicity).
- Not adding non-blocking/async dispatch (e.g. `curl_multi`) on the client side — sync curl with short timeouts, wrapped so failures never bubble up, is the chosen tradeoff.
- Not changing `CreateProjectHandler`, `GenerateIngestionKeyHandler`, `RotateIngestionKeyHandler`, or `GetIngestionKeyHandler` logic — only what `InstallSnippetBuilder` returns changes.
- Not adding a new Behat feature — server-side ingest behavior is unchanged and already covered.

## Implementation Approach

Two new small classes (`CurlTransport`, `IngestHandler`) live under a new `src/MonitoringWebowi/Handler/` tree, outside the existing DDD bounded contexts (`Projects`, `Logging`, `Identity`) — this code doesn't belong to any of them; it's a self-contained reference client. `IngestHandler` depends on `TransportInterface` (constructor-injected, defaulting to `new CurlTransport()`) purely so it can be unit-tested without real network calls; `CurlTransport` itself is tested against a real local PHP built-in server fixture, since it's the one piece that actually has to prove an HTTP round-trip works. `InstallSnippetBuilder` becomes the single place that stitches the two real files' source into the returned snippet text, guaranteeing what a user copies is exactly what's tested.

## Phase 1: Transport layer

### Overview

A minimal, curl-based HTTP transport the handler can call without adding any Composer dependency to a host project, with a real (not mocked) round-trip test.

### Changes Required:

#### 1. Transport contract and exception

**File**: `src/MonitoringWebowi/Handler/Transport/TransportInterface.php`

**Intent**: Define the seam `IngestHandler` depends on, so it can be unit-tested without touching curl.

**Contract**: `interface TransportInterface { public function send(string $url, string $apiKey, string $jsonPayload): void; }` — implementations must throw `TransportException` on any failure (network error, non-2xx response); a normal return means the request was accepted.

**File**: `src/MonitoringWebowi/Handler/Transport/TransportException.php`

**Intent**: A single exception type callers of `send()` need to catch.

**Contract**: `final class TransportException extends \RuntimeException {}` — no custom behavior needed.

#### 2. Curl-based implementation

**File**: `src/MonitoringWebowi/Handler/Transport/CurlTransport.php`

**Intent**: Real HTTP transport using only PHP's ext-curl (no Composer dependency), bounded by short timeouts so a slow/unreachable endpoint can't meaningfully stall the caller.

**Contract**: `final class CurlTransport implements TransportInterface`. `send()` POSTs `$jsonPayload` to `$url` with headers `Content-Type: application/json` and `X-Ingestion-Key: $apiKey`, using `CURLOPT_CONNECTTIMEOUT_MS` ≈ 200 and `CURLOPT_TIMEOUT_MS` ≈ 300. Throws `TransportException` when `curl_errno()` is non-zero, or when the response HTTP status is outside the 200–299 range (the ingest endpoint replies `202 Accepted`, per `IngestLogController`).

### Success Criteria:

#### Automated Verification:

- [ ] Unit tests pass: `make phpunit`
- [ ] Mutation coverage: `make infection` reports 100% MSI / covered-MSI for the new files
- [ ] Static analysis passes: `docker exec -it -u root monitoring-webowi-php vendor/bin/phpstan analyse`
- [ ] Coding standards pass: `make cs-fixer` produces no diff

#### Manual Verification:

- None for this phase — covered by Phase 4's end-to-end pass.

---

## Phase 2: IngestHandler

### Overview

The actual Monolog handler a host Symfony app wires up: turns a `LogRecord` into the JSON shape `IngestLogInput` expects, fails open on any error.

### Changes Required:

#### 1. The handler

**File**: `src/MonitoringWebowi/Handler/IngestHandler.php`

**Intent**: Convert Monolog records above the configured level into the ingestion API's payload shape and send them via the injected transport, without ever throwing back into the host application.

**Contract**: `final class IngestHandler extends \Monolog\Handler\AbstractProcessingHandler`. Constructor: `__construct(string $url, string $apiKey, ?TransportInterface $transport = null, ?callable $onFailure = null, int|string|Level $level = Level::Error, bool $bubble = false)` — calls `parent::__construct($level, $bubble)`, defaults `$transport` to `new CurlTransport()` when null (avoids a non-constant default expression in the signature). `protected function write(LogRecord $record): void` builds the payload — `datetime` via `$record->datetime->format(DATE_ATOM)`, `level` via `$record->level->toPsrLogLevel()`, `message` via `$record->message`, `context` via `NormalizerFormatter::normalize($record->context)` merged with `$record->extra` (non-empty extra nested under a reserved `_monologExtra` key so it can never collide with real context keys) — `json_encode`s it and calls `$this->transport->send($url, $apiKey, $json)` inside a `try/catch (\Throwable $e)` that invokes `$onFailure` if provided and always swallows the exception (never rethrows).

### Success Criteria:

#### Automated Verification:

- [ ] Unit tests pass: `make phpunit` — covering: below-threshold records never reach the transport; at/above-threshold records are sent with the correct URL/key/payload shape; exception objects in context are normalized to JSON-safe data, not left raw; transport failures are swallowed and never propagate; `$onFailure` is invoked on failure and not invoked on success; `$bubble` is respected
- [ ] Mutation coverage: `make infection` reports 100% MSI / covered-MSI for the new file
- [ ] Static analysis passes: `docker exec -it -u root monitoring-webowi-php vendor/bin/phpstan analyse`
- [ ] Coding standards pass: `make cs-fixer` produces no diff

#### Manual Verification:

- None for this phase — covered by Phase 4's end-to-end pass.

---

## Phase 3: Snippet embedding

### Overview

Make `InstallSnippetBuilder` the single source of truth: it reads the three real files from Phase 1/2 and inlines their actual contents into the snippet it returns, instead of a hand-maintained stub.

### Changes Required:

#### 1. Builder changes

**File**: `src/Projects/Application/GetIngestionKey/InstallSnippetBuilder.php`

**Intent**: Replace the aspirational comment block with the real, tested source of `TransportInterface`, `TransportException`, `CurlTransport`, and `IngestHandler`, plus the existing YAML wiring and clear "create these files at these paths" install instructions.

**Contract**: Constructor gains `string $projectDir` (bound to `%kernel.project_dir%`). `build()` reads each of the four files under `$projectDir . '/src/MonitoringWebowi/Handler/'` via `file_get_contents()` and appends them as fenced ` ```php ` blocks after the existing monolog.yaml/services.yaml wiring section, each preceded by a one-line comment naming the destination path in the host project (e.g. `src/MonitoringWebowi/Handler/IngestHandler.php`).

#### 2. Service wiring

**File**: `config/services.yaml`

**Intent**: Supply the new constructor argument.

**Contract**: Under the existing `App\Projects\Application\GetIngestionKey\InstallSnippetBuilder` arguments block, add `$projectDir: '%kernel.project_dir%'` alongside `$appUrl`.

#### 3. Existing test update

**File**: `tests/Unit/Projects/Application/GetIngestionKey/InstallSnippetBuilderTest.php`

**Intent**: Assert the snippet now embeds real, tested source rather than just the URL/key strings.

**Contract**: Each test instantiates `InstallSnippetBuilder` with both `$appUrl` and a `$projectDir` (pointing at the real repo root, e.g. `dirname(__DIR__, 5)` or an equivalent resolvable path) and asserts the returned string `assertStringContainsString`s each of `final class IngestHandler`, `final class CurlTransport`, `interface TransportInterface`. Existing assertions (key value, ingestion URL, trailing-slash stripping) stay as-is.

### Success Criteria:

#### Automated Verification:

- [ ] Unit tests pass: `make phpunit`
- [ ] Mutation coverage: `make infection` reports 100% MSI / covered-MSI including `InstallSnippetBuilder`
- [ ] Static analysis passes: `docker exec -it -u root monitoring-webowi-php vendor/bin/phpstan analyse`
- [ ] Coding standards pass: `make cs-fixer` produces no diff

#### Manual Verification:

- [ ] `POST /api/v1/projects/{uuid}/ingestion-key` (or `GET`) response `snippet` field visibly contains the real class bodies, not a stub comment, when called against the running dev stack

---

## Phase 4: Manual end-to-end verification

### Overview

Prove the whole chain works against a real request, not just mocks: paste the generated snippet's classes into a throwaway script, send a log, see it land.

### Changes Required:

No source changes — this phase is verification-only.

### Success Criteria:

#### Automated Verification:

- None — this phase is manual by design (confirmed decision: automated tests alone can't catch a wrong URL path, header-name typo, or content-type mismatch).

#### Manual Verification:

- [ ] Using the already-generated project (`9aa16c9a-85cf-493c-903a-e2db415df2f9`) and key (`mon_ing_6ab775c25b8071a18c69e8b8f1b501f9`), copy the four emitted classes into a throwaway script, construct `IngestHandler` with the real dev-stack ingest URL and key, log an error-level message through it
- [ ] Confirm the log appears via `GET /api/v1/projects/9aa16c9a-85cf-493c-903a-e2db415df2f9/logs`
- [ ] Confirm a deliberately wrong API key results in no log appearing and no exception thrown back to the caller

**Implementation Note**: Pause here for manual confirmation from the human that the end-to-end pass succeeded before considering this change done.

---

## Testing Strategy

### Unit Tests:

- `CurlTransport`: successful POST against a real local `php -S` fixture server, non-2xx response maps to `TransportException`, unreachable host maps to `TransportException`, correct headers/body sent
- `IngestHandler`: level/bubble filtering, correct payload shape, context/extra normalization (including an exception-in-context case), transport failure swallowed, `$onFailure` invoked only on failure
- `InstallSnippetBuilder`: existing assertions plus new ones for embedded real source

### Integration Tests:

- None new — Phase 4's manual pass is the integration check; server-side ingestion integration is already covered by existing Behat features.

### Manual Testing Steps:

1. Generate/reuse an ingestion key for a real project.
2. Fetch the snippet via `GET /api/v1/projects/{uuid}/ingestion-key`.
3. Drop the four embedded classes into a throwaway script alongside a few lines constructing and invoking `IngestHandler`.
4. Trigger an error-level log and confirm it's listable via the API.
5. Repeat with a wrong key and confirm silence (no exception, no log recorded).

## Performance Considerations

`CurlTransport`'s timeouts (~200ms connect / ~300ms total) bound the worst-case per-call cost on the host application; no backoff/circuit-breaker is implemented by design (see What We're NOT Doing), so a chatty failure loop pays this bounded cost on every call.

## Migration Notes

Not applicable — no existing data or deployed behavior changes; this is new, previously-nonexistent code plus a snippet content change.

## References

- Existing snippet stub: `src/Projects/Application/GetIngestionKey/InstallSnippetBuilder.php`
- Ingest contract: `src/Logging/Ui/Ingest/IngestLogController.php`, `src/Logging/Ui/Ingest/IngestLogInput.php`
- Auth contract: `src/Projects/Infrastructure/Security/IngestionKeyAuthenticator.php`
- Severity values: `src/Logging/Domain/LogSeverityEnum.php`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles.

### Phase 1: Transport layer

#### Automated

- [x] 1.1 Unit tests pass: `make phpunit` — 212188b
- [x] 1.2 Mutation coverage: `make infection` reports 100% MSI / covered-MSI for the new files — 212188b
- [x] 1.3 Static analysis passes: phpstan analyse — 212188b
- [x] 1.4 Coding standards pass: `make cs-fixer` produces no diff — 212188b

### Phase 2: IngestHandler

#### Automated

- [x] 2.1 Unit tests pass: `make phpunit` — 6213c1f
- [x] 2.2 Mutation coverage: `make infection` reports 100% MSI / covered-MSI for the new file — 6213c1f
- [x] 2.3 Static analysis passes: phpstan analyse — 6213c1f
- [x] 2.4 Coding standards pass: `make cs-fixer` produces no diff — 6213c1f

### Phase 3: Snippet embedding

#### Automated

- [x] 3.1 Unit tests pass: `make phpunit` — fc06d78
- [x] 3.2 Mutation coverage: `make infection` reports 100% MSI / covered-MSI including `InstallSnippetBuilder` — fc06d78
- [x] 3.3 Static analysis passes: phpstan analyse — fc06d78
- [x] 3.4 Coding standards pass: `make cs-fixer` produces no diff — fc06d78

#### Manual

- [x] 3.5 Snippet response visibly contains real class bodies against the running dev stack — fc06d78

### Phase 4: Manual end-to-end verification

#### Manual

- [x] 4.1 Log sent through the pasted classes appears via `GET /api/v1/projects/{uuid}/logs`
- [x] 4.2 A wrong API key results in silence — no exception, no log recorded
