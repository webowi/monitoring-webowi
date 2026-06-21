# Ingested Logs Queryable List (S-01) Implementation Plan

## Overview

Build the smallest end-to-end pipe that proves Monitoring Webowi's core hypothesis: a Symfony app's log records, sent via a per-project API key, are accepted without blocking the host app, persisted asynchronously, and retrievable reverse-chronologically by the project's owner via a JWT-authenticated API. This is the roadmap's north star (S-01), and it bundles the still-unbuilt F-01 (async transport foundation) since nothing currently unblocks it.

## Current State Analysis

- `src/Logging/{Domain,Application,Infrastructure,Ui}` are `.gitkeep`-only — zero ingestion/storage/listing code exists.
- `symfony/messenger` is **not** a dependency; no `messenger.yaml`; no `messenger_messages` migration. The roadmap's "F-01: ready" status means unblocked, not built.
- Security is JWT-only today (`config/packages/security.yaml`: `api_auth` firewall open for sign-in/refresh, `api` firewall JWT-guarded for everything else under `/api/v1`). No API-key authentication mechanism exists anywhere in the codebase.
- `src/Projects/Domain/Project.php` and `src/Projects/Domain/IngestionKey.php` are **currently broken in the working tree** (uncommitted `M` changes): `Project::getOrganization()`/`addIngestionKey()` reference an `$organization` object and an `$ingestionKeys` collection that are never declared as properties; `IngestionKey` has no `setProject()` despite being called from `CreateProjectWithFirstKey` (an unwired, roadmap-confirmed-dead project-creation wizard). `ProjectRepository::getById()` also has an alias bug (`c.uuid` should be `p.uuid`). None of this is exercised by any wired code path today, but this plan's ownership check and key lookup depend on these entities working correctly, so fixing them is necessary foundation work, not new feature scope.
- `IngestionKey` already stores only a key hash (`hash_hmac('sha256', $plaintext, $appSecret)`, confirmed in `CreateProjectWithFirstKey`) with `isActive()`/`markUsedNow()`/`revoke()` — reusable as-is, just needs a hash-lookup repository method and a `projectId` getter/setter (neither exists today).
- Ownership chain is flat UUID FKs, no ORM relations elsewhere: `User.organizationId` (readonly public property) and `Project.organizationId` (private, no getter today).
- `fixtures/` (top-level, for real dev/demo seeding via `hautelook/alice-bundle`, already a dependency) is empty. `tests/Behat/Fixtures/` (test-only) has an existing `user.yml` + `_templates.yml` pattern to mirror.
- Testing convention for black-box API behavior is **Behat** (`tests/Behat/Context/JSON/*`, `FixturesContext`, `behat.yml.dist`), not PHPUnit `WebTestCase`. PHPUnit (`tests/Unit/`) is used for pure unit tests.
- `symfony/rate-limiter` is already a dependency, with one existing policy (`gus_api` in `config/packages/rate_limiter.yaml`) to follow as a pattern.
- `docker-compose.yml` has no worker/queue service; F-02 (separately blocked on a Hetzner-vs-OVHcloud decision) is scoped to rebuild this file for production — this plan only needs a dev-usable consumer.

## Desired End State

A Symfony app can `POST` a log record with a valid per-project API key to the ingestion endpoint and get a fast `202 Accepted`; a worker consumes the message asynchronously and persists a normalized `LogEntry`. The project owner, signed in with JWT, can `GET` that project's logs in reverse-chronological order, paginated, and never sees another project's data. Verify via: running the Behat suite (`./vendor/bin/behat`), and manually — POST a log with the seeded fixture key, start the worker, confirm the row appears via the GET endpoint within a few seconds.

### Key Discoveries:

- `src/Projects/Application/Wizard/CreateProjectWithFirstKey.php:35-36` — the hashing formula to reuse: `hash_hmac('sha256', $plaintext, $appSecret)`. The wizard itself stays untouched (dead/unwired, roadmap-confirmed sunk cost).
- `src/Kernel/TranslatableException/TranslatableExceptionListener.php:18-29` — the established error-response contract: throw an `\Exception` implementing the marker `TranslatableExceptionInterface`, `getMessage()` becomes the translated `{"error": ...}` body, `getCode()` becomes the HTTP status.
- `src/Kernel/EventSubscriber/TimestampableSubscriber.php` + `src/Kernel/Security/CurrentUserFetcher.php:18-23` — `prePersist` tries to stamp `createdBy`/`updatedBy` via the current Security token; in a Messenger worker (CLI, no token) `CurrentUserFetcher::fetchUser()` throws `\LogicException`, but the subscriber already catches `\Throwable` and only logs — so `LogEntry` rows persisted from the consumer will have `createdBy = null` and an error-level log line per insert. Not a blocker, just expected noise.
- `config/routes.yaml` registers UI directories by convention (`controllers_projects` → `src/Projects/Ui/`, prefix `/api/v1`, `type: attribute`) — a new `controllers_logging` entry pointing at `src/Logging/Ui/` follows the same pattern.

## What We're NOT Doing

- Not implementing F-02 (prod-safe docker-compose, `/health` endpoint) — separately blocked on a platform decision.
- Not building the actual Monolog HTTP handler / install snippet that runs inside the *host* Symfony app — that's S-04's "install instructions" scope. This plan only builds the receiving endpoint and defines the JSON contract it expects.
- Not implementing severity or HTTP-code *filtering* (S-02) or the freshness indicator (S-03) — this plan only stores the normalized columns S-02 will filter on.
- Not implementing 7-day log retention/pruning — no slice in the roadmap currently owns this NFR; deferred per explicit user decision.
- Not fixing or wiring up `CreateProjectWithFirstKey` — it remains dead code, untouched.
- Not adding a production-shaped Messenger worker deployment (systemd, supervisor, Coolify process) — only a docker-compose dev service.

## Implementation Approach

Five phases, each independently shippable: (1) lay the async-transport + domain-model foundation, (2) add the `LogEntry` storage model, (3) build the API-key-authenticated ingestion endpoint that enqueues, (4) build the JWT-authenticated, ownership-scoped list endpoint, (5) seed fixtures and add Behat/PHPUnit coverage for the full pipe. Each phase after the first builds directly on the previous one; nothing is built speculatively ahead of what the next phase needs.

## Critical Implementation Details

**Security firewall ordering.** Symfony evaluates `firewalls:` and `access_control:` top-to-bottom and stops at the first pattern match. The new ingestion firewall's pattern (matching the ingest route only) must be declared **before** the existing `api: pattern: ^/api/v1` firewall, mirroring how `api_auth` is already listed before `api` for the same reason. The corresponding `access_control` entry for the ingest path must likewise be listed before the catch-all `{ path: ^/api, roles: IS_AUTHENTICATED_FULLY }` entry, or the catch-all will shadow it and reject every ingestion request before the custom authenticator ever runs.

**Custom authenticator needs no `provider:`.** Because the firewall is `stateless: true` and the authenticator resolves its "user" via a `UserBadge` constructed with an explicit loader closure (not a configured `UserProviderInterface`), no `provider:` key is needed on the new firewall — there is no session to refresh, and the closure is self-contained.

## Phase 1: Foundation — async transport + domain model fixes

### Overview

Stand up the Messenger async transport (F-01) and bring `Project`/`IngestionKey` to a consistent, working state so later phases have a correct ownership check and key lookup to build on.

### Changes Required:

#### 1. Messenger dependency and transport

**File**: `composer.json`, `config/packages/messenger.yaml` (new)

**Intent**: Add `symfony/messenger` and `symfony/doctrine-messenger`, configure one `async` transport backed by the existing Doctrine/MySQL connection, routed for the ingestion message introduced in Phase 3.

**Contract**: `framework.messenger.transports.async` uses the `doctrine://default?queue_name=async` DSN; `framework.messenger.routing` maps `App\Logging\Application\Ingest\IngestLogEntryMessage` (introduced in Phase 3) to `async`. No new service/container beyond the existing MySQL one.

#### 2. Messenger transport table migration

**File**: `migrations/Version<timestamp>.php` (new)

**Intent**: Create the standard `messenger_messages` table the Doctrine transport requires, generated via `bin/console doctrine:migrations:diff` after the transport is configured — do not rely on transport `auto_setup` in any environment this plan touches.

**Contract**: Standard symfony/doctrine-messenger schema (id, body, headers, queue_name, created_at, available_at, delivered_at) with the conventional indexes on `queue_name`/`available_at`.

#### 3. Fix `Project` domain entity

**File**: `src/Projects/Domain/Project.php`

**Intent**: Remove the broken `getOrganization()`/`setOrganization()`/`getIngestionKeys()`/`addIngestionKey()` methods and their `Organization`/`Collection` imports — they reference properties that were never declared and have no live caller (only the dead wizard touches this path). Add the missing `getOrganizationId(): Uuid` getter for the existing, already-declared `organizationId` property, and reimplement `belongsToOrganization(Uuid $organizationId)` as a direct scalar comparison against `$this->organizationId` instead of the broken `$this->organization` access.

**Contract**: `Project` keeps `organizationId: Uuid` as its only organization reference (no ORM relation), consistent with `User.organizationId` and `IngestionKey.projectId` elsewhere in the codebase.

#### 4. Fix `ProjectRepository`

**File**: `src/Projects/Infrastructure/ProjectRepository.php`

**Intent**: Fix the `getById()` alias bug (`c.uuid` → `p.uuid`) and change `getByOrganizationId()`/`countByOrganizationId()` to filter on the scalar `p.organizationId` column instead of joining the (now-removed) `p.organization` relation.

**Contract**: Same method signatures as `ProjectRepositoryInterface` today; query bodies only.

#### 5. Extend `IngestionKey` domain entity + repository

**File**: `src/Projects/Domain/IngestionKey.php`, `src/Projects/Domain/IngestionKeyRepositoryInterface.php`, `src/Projects/Infrastructure/IngestionKeyRepository.php`

**Intent**: Add `getProjectId(): Uuid` and `setProjectId(Uuid $projectId): self` (mirroring the existing mutable-setter style of `setUuid()`/`setName()`/`setKeyHash()` — no constructor change). Add a hash-lookup method the new authenticator will call.

**Contract**: `IngestionKeyRepositoryInterface::findOneActiveByKeyHash(string $keyHash): ?IngestionKey` — returns a key only if `status = active` and not expired (reuse `isActive()` after fetch, or filter in SQL; either satisfies the contract).

#### 6. Key-hashing service

**File**: `src/Projects/Infrastructure/Security/IngestionKeyHasher.php` (new), `config/services.yaml`

**Intent**: Centralize the `hash_hmac('sha256', $plaintext, $appSecret)` formula (currently duplicated nowhere reusable — only inlined in the dead wizard with a hardcoded `'123'` placeholder) behind one service bound to the real application secret, so both the new authenticator (Phase 3) and fixtures (Phase 5) compute the same hash from the same secret.

**Contract**: `IngestionKeyHasher::hash(string $plaintext): string`. Bind its constructor `$appSecret` parameter to `%kernel.secret%` via `services.yaml` (not the wizard's hardcoded `'123'`).

#### 7. Dev worker service

**File**: `docker-compose.yml`

**Intent**: Add a `worker` service running the Messenger consumer so the async pipe is testable end-to-end locally, without touching the parts of this file F-02 is scoped to rebuild (MySQL service, networking).

**Contract**: New service reusing the existing PHP image/build context, command `bin/console messenger:consume async -vv`, `depends_on: [mysql]`, `restart: unless-stopped`.

### Success Criteria:

#### Automated Verification:

- Messenger config validates: `bin/console debug:config framework messenger`
- Migration applies cleanly: `bin/console doctrine:migrations:migrate --no-interaction`
- Static analysis passes: `./vendor/bin/phpstan analyse`
- Existing unit tests still pass: `./vendor/bin/phpunit`

#### Manual Verification:

- `docker compose up -d worker` starts and stays up, logs show it connected to the `async` transport.
- A throwaway message dispatched via `bin/console messenger:stats` or a quick `bin/console` one-off confirms the `messenger_messages` table receives and clears rows.

---

## Phase 2: Log domain & storage

### Overview

Introduce the `LogEntry` entity and its repository — the normalized storage shape for ingested log records.

### Changes Required:

#### 1. `LogEntry` entity + severity enum

**File**: `src/Logging/Domain/LogEntry.php` (new), `src/Logging/Domain/LogSeverityEnum.php` (new)

**Intent**: Model one ingested log record with the minimal normalized column set agreed during planning: `id`/`uuid`, `projectId` (scalar `Uuid`, no relation — consistent with `IngestionKey.projectId`), `occurredAt` (from the payload), `receivedAt` (server-assigned), `severity`, `message`, `httpStatusCode` (nullable), `exceptionClass` (nullable), and `context` (raw JSON for drill-in). `LogSeverityEnum` is a string-backed enum with Monolog's eight RFC 5424 levels (`debug`…`emergency`), mirroring the lowercase-value convention of `IngestionKeyStatusEnum`.

**Contract**: ORM attributes following the `Project`/`IngestionKey` pattern (`#[ORM\Entity]`, `#[ORM\Column(type: 'uuid')]` for `projectId`, `enumType:` column option for `severity`). Index on `(projectId, occurredAt)` to support Phase 4's ordered, scoped query.

#### 2. `LogEntry` repository

**File**: `src/Logging/Domain/LogEntryRepositoryInterface.php` (new), `src/Logging/Infrastructure/LogEntryRepository.php` (new)

**Intent**: Persist new entries and fetch a project's entries reverse-chronologically, paginated.

**Contract**: `add(LogEntry $logEntry): void`; `getByProjectId(Uuid $projectId, int $limit, int $offset): iterable` ordered by `occurredAt DESC`.

#### 3. Migration

**File**: `migrations/Version<timestamp>.php` (new)

**Intent**: Create the `log_entry` table for the entity above.

**Contract**: Generated via `bin/console doctrine:migrations:diff` against the new entity mapping.

### Success Criteria:

#### Automated Verification:

- Migration applies cleanly: `bin/console doctrine:migrations:migrate --no-interaction`
- Static analysis passes: `./vendor/bin/phpstan analyse`
- New repository unit-testable in isolation (no test yet — covered in Phase 5)

#### Manual Verification:

- `bin/console doctrine:schema:validate` reports no mapping errors for the new entity.

---

## Phase 3: Ingestion endpoint

### Overview

The API-key-authenticated `POST` endpoint that validates, enqueues, and (via the async handler) normalizes and persists a log record.

### Changes Required:

#### 1. Ingestion authenticator + firewall

**File**: `src/Projects/Infrastructure/Security/IngestionKeyAuthenticator.php` (new), `src/Projects/Infrastructure/Security/IngestionPrincipal.php` (new), `config/packages/security.yaml`

**Intent**: A custom `AbstractAuthenticator` that reads a request header carrying the plaintext API key, hashes it via `IngestionKeyHasher`, looks it up via `IngestionKeyRepositoryInterface::findOneActiveByKeyHash()`, and on success resolves the associated `Project`. `IngestionPrincipal` is a minimal `UserInterface` implementation (mirroring `SymfonyUserAdapter`'s wrapper pattern) carrying the resolved `Project`/`IngestionKey`, used as the `UserBadge`'s loaded user so the controller can fetch it via `getUser()`. On missing/invalid/revoked key, throw a `TranslatableExceptionInterface` exception with `getCode() === 401`. On success, call `IngestionKey::markUsedNow()`.

**Contract**: New firewall block in `security.yaml`, declared *before* the existing `api` firewall (see Critical Implementation Details — ordering), `stateless: true`, no `provider:`, `custom_authenticators: [App\Projects\Infrastructure\Security\IngestionKeyAuthenticator]`. Matching `access_control` entry declared before the `^/api` catch-all.

#### 2. Per-project rate limiter

**File**: `config/packages/rate_limiter.yaml`

**Intent**: Add a sliding-window limiter policy for ingestion, keyed by project UUID, following the existing `gus_api` policy's shape.

**Contract**: New `log_ingestion` policy under `framework.rate_limiter.limiters`; consumed in the controller/handler keyed by the authenticated `Project`'s UUID. Exceeding the limit throws a `TranslatableExceptionInterface` exception with `getCode() === 429`.

#### 3. Ingestion input + controller

**File**: `src/Logging/Ui/Ingest/IngestLogInput.php` (new), `src/Logging/Ui/Ingest/IngestLogController.php` (new), `config/routes.yaml`

**Intent**: Define the JSON contract this endpoint accepts — `datetime` (ISO 8601 string), `level` (string, validated against `LogSeverityEnum`), `message` (string), `context` (optional array; may include an optional `http_status_code` int and an optional `exception.class` string, read by the handler in change #4 below). On valid input, the controller reads the authenticated `IngestionPrincipal` (via `getUser()`), checks the rate limiter, and dispatches `IngestLogEntryMessage` to the message bus, then responds `202`. On `MapRequestPayload` validation failure, the framework's default behavior already yields `422` (matches the agreed error-code contract — no custom handling needed beyond what `#[MapRequestPayload]` already does for invalid JSON/types).

**Contract**: `#[Route(path: '/logs/ingest', name: 'logs_ingest', methods: ['POST'])]`, registered under the new `controllers_logging` entry in `config/routes.yaml` (mirrors `controllers_projects`'s `path`/`namespace`/`prefix: /api/v1`/`type: attribute` shape). Response: `202` body `{"status": "accepted"}`; `401` `{"error": ...}` (invalid key); `422` (malformed payload); `429` `{"error": ...}` (rate limit).

#### 4. Async message + handler

**File**: `src/Logging/Application/Ingest/IngestLogEntryMessage.php` (new), `src/Logging/Application/Ingest/IngestLogEntryMessageHandler.php` (new)

**Intent**: The message carries the validated, already-authenticated payload (projectId, occurredAt, severity, message, context). The `#[AsMessageHandler]` handler extracts `http_status_code` and `exception.class` from `context` into `LogEntry`'s dedicated columns (per the planning decision to normalize at ingest time, not defer to S-02), constructs a `LogEntry`, and persists it via `LogEntryRepositoryInterface::add()`.

**Contract**: Message is a plain, serializable DTO (scalar/array properties only — Messenger's default Doctrine/Symfony serializer must be able to (de)serialize it without custom normalizers).

### Success Criteria:

#### Automated Verification:

- Static analysis passes: `./vendor/bin/phpstan analyse`
- Unit tests for the handler's context-normalization logic pass: `./vendor/bin/phpunit` (tests added in Phase 5)

#### Manual Verification:

- `POST /api/v1/logs/ingest` with the fixture key (once Phase 5 fixtures exist) returns `202` immediately.
- Same request with a missing/garbage key header returns `401`; malformed JSON body returns `422`.
- With the worker (Phase 1) running, the row appears in `log_entry` within a few seconds; `http_status_code`/`exception_class` columns are populated when present in `context`.

---

## Phase 4: List endpoint

### Overview

The JWT-authenticated, ownership-scoped, paginated read side of the pipe.

### Changes Required:

#### 1. List handler with inline ownership check

**File**: `src/Logging/Application/List/ListProjectLogsHandler.php` (new), `src/Logging/Application/List/ProjectNotFoundOrAccessDeniedException.php` (new)

**Intent**: Given a project UUID and the current user's organization, fetch the project and compare `Project::getOrganizationId()` against the authenticated user's `organizationId` (via `CurrentUserFetcher`). On no-match or no-such-project, throw the same `ProjectNotFoundOrAccessDeniedException` for both cases — deliberately not distinguishing "doesn't exist" from "not yours" in the response, so the endpoint cannot be used to probe for the existence of other organizations' projects. On match, delegate to `LogEntryRepositoryInterface::getByProjectId()`.

**Contract**: `ProjectNotFoundOrAccessDeniedException implements TranslatableExceptionInterface`, `getCode() === 404`.

#### 2. List controller + pagination input

**File**: `src/Logging/Ui/List/ListProjectLogsController.php` (new), `src/Logging/Ui/List/ListProjectLogsInput.php` (new)

**Intent**: `GET` endpoint under the existing JWT-guarded `/api/v1` firewall (no new security config needed — it already falls under the catch-all `api` firewall). Query parameters `limit` (default 50, max 200) and `offset` (default 0), validated. Response is the normalized row shape: `occurredAt`, `severity`, `message`, `httpStatusCode`, `exceptionClass`, `context`, ordered most-recent-first.

**Contract**: `#[Route(path: '/projects/{projectUuid}/logs', name: 'logging_list_project_logs', methods: ['GET'])]`, registered under the same `controllers_logging` entry from Phase 3. Response: `200` with a JSON array of rows (empty array, not a 0-row table-shaped object, when no logs exist — matches US-01's acceptance criterion on empty-state data shape; the actual empty-state *UI* is the separate SPA's job).

### Success Criteria:

#### Automated Verification:

- Static analysis passes: `./vendor/bin/phpstan analyse`
- Unit tests for the ownership-check branch pass: `./vendor/bin/phpunit` (tests added in Phase 5)

#### Manual Verification:

- Signed in as the seeded user, `GET /api/v1/projects/{ownProjectUuid}/logs` returns the ingested rows reverse-chronologically.
- The same request for a project UUID belonging to a different organization returns `404`, not `403` (per the no-existence-oracle contract).
- `limit`/`offset` behave correctly against more than one page of seeded rows.

---

## Phase 5: Fixtures & test coverage

### Overview

Seed the data this slice (and S-04) need, and add automated coverage proving the full ingest→store→list pipe and its security boundaries.

### Changes Required:

#### 1. Top-level dev fixtures

**File**: `fixtures/log_monitoring.yaml` (new)

**Intent**: Seed one organization, one user, one project, and one active ingestion key with a fixed, documented plaintext value (per the planning decision) — loadable via `bin/console hautelook:fixtures:load`. The fixture's `keyHash` is computed offline via `IngestionKeyHasher` against the local `.env`'s `APP_SECRET`, not generated at load time.

**Contract**: Alice YAML following the existing `tests/Behat/Fixtures/user.yml`/`_templates.yml` shape (`<uuid()>`, fixed values for anything the manual-testing steps below reference).

#### 2. Behat fixtures + features

**File**: `tests/Behat/Fixtures/log_monitoring.yml` (new), `tests/Behat/Features/Logging/ingest_and_list.feature` (new), `tests/Behat/Features/Logging/ingestion_auth.feature` (new), `tests/Behat/Features/Logging/project_isolation.feature` (new)

**Intent**: `ingest_and_list.feature` covers the happy path (valid key → `202` → list shows the row, including `http_status_code`/`exception_class` normalization from `context`). `ingestion_auth.feature` covers missing/garbage/revoked key → `401`, and malformed payload → `422`. `project_isolation.feature` covers a second organization's user listing the first organization's project → `404`. If `JSONRequestContext`/`JSONMainContext` doesn't already expose a step for setting an arbitrary request header (needed to send the ingestion key header), add one following the existing `$this->headers->set(...)` pattern in `JSONRequestContext`.

**Contract**: Scenarios use the existing `Given the following fixtures are loaded from the files:` / `I send a :method JSON request to :url` / `the response status code should be :code` steps.

#### 3. Unit tests

**File**: `tests/Unit/Logging/Application/Ingest/IngestLogEntryMessageHandlerTest.php` (new), `tests/Unit/Projects/Infrastructure/Security/IngestionKeyHasherTest.php` (new)

**Intent**: Unit-test the context-normalization logic (HTTP status / exception class extraction) in isolation from Doctrine/HTTP, and the hash formula's determinism, mirroring the existing `tests/Unit/Kernel/*` style (plain PHPUnit, no Symfony kernel boot).

**Contract**: Standard PHPUnit `TestCase` classes.

### Success Criteria:

#### Automated Verification:

- Full Behat suite passes: `./vendor/bin/behat`
- Full PHPUnit suite passes: `./vendor/bin/phpunit`
- Static analysis passes: `./vendor/bin/phpstan analyse`

#### Manual Verification:

- `bin/console hautelook:fixtures:load --no-interaction` succeeds against a fresh dev DB.
- The PRD's US-01 walkthrough works end-to-end by hand: sign in as the seeded user, POST a 500-class log with the seeded key, see it appear in the list within a few seconds.

---

## Testing Strategy

### Unit Tests:

- Context normalization (HTTP status / exception class extraction) — valid cases, missing keys, malformed nested shapes.
- Key-hash determinism and the authenticator's active/expired/revoked branches.
- Ownership-check branch (`belongsToOrganization` / the list handler's match vs. no-match paths).

### Integration Tests:

- Behat: full ingest → async consume → list pipe; auth rejection paths; cross-organization isolation.

### Manual Testing Steps:

1. Run `docker compose up -d worker`, confirm it stays up.
2. Load fixtures, sign in as the seeded user via the existing `/api/v1/auth/sign-in` endpoint to get a JWT.
3. POST a log record with the fixture's ingestion key header and a `context.http_status_code: 500`; confirm `202`.
4. GET the project's logs with the JWT; confirm the row appears with `httpStatusCode: 500` populated, within the NFR's freshness window.
5. Repeat the GET as a different organization's user; confirm `404`.

## Performance Considerations

The ingestion controller does no DB write in the request path (auth lookup is a single indexed read on `key_hash`); the freshness NFR (p95 ≤ 5s) is a property of the worker actually running promptly, not of any logic added here.

## Migration Notes

Two new migrations in this plan (`messenger_messages` in Phase 1, `log_entry` in Phase 2) are additive — no existing table is altered, no backfill needed.

## References

- Roadmap: `context/foundation/roadmap.md` (S-01, F-01)
- PRD: `context/foundation/prd.md` (FR-001–003, FR-005–007, NFRs)
- Hashing formula precedent: `src/Projects/Application/Wizard/CreateProjectWithFirstKey.php:35-36`
- Error-response precedent: `src/Kernel/TranslatableException/TranslatableExceptionListener.php`
- Behat conventions: `tests/Behat/Context/JSON/JSONRequestContext.php`, `tests/Behat/Fixtures/user.yml`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Foundation — async transport + domain model fixes

#### Automated

- [x] 1.1 Messenger config validates: `bin/console debug:config framework messenger` — 8185209
- [x] 1.2 Migration applies cleanly: `bin/console doctrine:migrations:migrate --no-interaction` — 8185209
- [x] 1.3 Static analysis passes: `./vendor/bin/phpstan analyse` — 8185209
- [x] 1.4 Existing unit tests still pass: `./vendor/bin/phpunit` — 8185209

#### Manual

- [x] 1.5 Worker service starts and stays up, connected to the `async` transport — 8185209
- [x] 1.6 A throwaway dispatched message is received and cleared from `messenger_messages` — 8185209

### Phase 2: Log domain & storage

#### Automated

- [x] 2.1 Migration applies cleanly: `bin/console doctrine:migrations:migrate --no-interaction` — be90db5
- [x] 2.2 Static analysis passes: `./vendor/bin/phpstan analyse` — be90db5

#### Manual

- [x] 2.3 `bin/console doctrine:schema:validate` reports no mapping errors for `LogEntry` — be90db5

### Phase 3: Ingestion endpoint

#### Automated

- [x] 3.1 Static analysis passes: `./vendor/bin/phpstan analyse`
- [ ] 3.2 Handler normalization unit tests pass: `./vendor/bin/phpunit`

#### Manual

- [ ] 3.3 Valid key → `202` immediately
- [ ] 3.4 Invalid/missing key → `401`; malformed payload → `422`
- [ ] 3.5 Row appears in `log_entry` within a few seconds with `httpStatusCode`/`exceptionClass` populated when present in `context`

### Phase 4: List endpoint

#### Automated

- [ ] 4.1 Static analysis passes: `./vendor/bin/phpstan analyse`
- [ ] 4.2 Ownership-check unit tests pass: `./vendor/bin/phpunit`

#### Manual

- [ ] 4.3 Owner sees own project's logs reverse-chronologically
- [ ] 4.4 Different organization's user gets `404` for the same project UUID
- [ ] 4.5 `limit`/`offset` paginate correctly across multiple seeded rows

### Phase 5: Fixtures & test coverage

#### Automated

- [ ] 5.1 Full Behat suite passes: `./vendor/bin/behat`
- [ ] 5.2 Full PHPUnit suite passes: `./vendor/bin/phpunit`
- [ ] 5.3 Static analysis passes: `./vendor/bin/phpstan analyse`

#### Manual

- [ ] 5.4 `bin/console hautelook:fixtures:load --no-interaction` succeeds against a fresh dev DB
- [ ] 5.5 Full US-01 hand walkthrough succeeds end-to-end
