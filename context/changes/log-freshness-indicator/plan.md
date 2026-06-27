# Log Freshness Indicator Implementation Plan

## Overview

Add a dedicated `GET /api/v1/projects/{uuid}/freshness` endpoint that returns the `receivedAt` timestamp of the most recent log entry for a project (or `null` if none have arrived). This gives the SPA a passive ingestion-health signal per FR-009 without coupling to the S-04 project-info endpoint.

## Current State Analysis

S-01 (`ingested-logs-queryable-list`) and S-02 (`filter-logs-by-severity-and-http-code`) have shipped the full ingest → store → list path. What exists relevant to this slice:

- `LogEntry` (`src/Logging/Domain/LogEntry.php:29-48`) has two timestamps: `occurredAt` (event time supplied by the Symfony app) and `receivedAt` (server-side arrival time, set unconditionally as `new \DateTimeImmutable('now')` at `LogEntry::create()` line 66).
- The `log_entry` table has one composite index: `idx_log_entry_project_occurred_at (project_id, occurred_at)` — no index on `received_at` yet.
- `LogEntryRepositoryInterface` (`src/Logging/Domain/LogEntryRepositoryInterface.php`) exposes `add()` and `getByProjectId()` only — no aggregate method exists.
- `src/Projects/Ui/` is empty (`.gitkeep` only) — no project-level API endpoint exists yet.
- All JSON responses are plain manual arrays; no Symfony serializer is used.
- Ownership checks follow a consistent pattern: `projectRepository->getById($uuid)` → `belongsToOrganization($user->organizationId)` → throw `ProjectNotFoundOrAccessDeniedException` (mapped to 404).

## Desired End State

`GET /api/v1/projects/{uuid}/freshness` returns `200 { "lastLogReceivedAt": "<ISO-8601 timestamp>" }` when at least one log has been ingested for the project, or `200 { "lastLogReceivedAt": null }` when no logs have arrived. The query uses `MAX(received_at)` scoped to the project, backed by a new `(project_id, received_at)` composite index so it stays a fast index-range scan as log volume grows. Unauthenticated requests receive `401`; requests for another owner's project receive `404`.

### Key Discoveries

- `LogEntry.receivedAt` (`src/Logging/Domain/LogEntry.php:36-37`, column `received_at`, type `DATETIME_IMMUTABLE`) is the correct timestamp — it reflects server arrival, directly answering "is ingestion alive?"
- The existing index `idx_log_entry_project_occurred_at` does not cover `received_at`; a second `#[ORM\Index]` on `LogEntry` plus a migration is required.
- `src/Logging/Infrastructure/LogEntryRepository.php` uses Doctrine QueryBuilder throughout — the new method follows the same pattern.
- The handler ownership-check pattern lives in `src/Logging/Application/List/ListProjectLogsHandler.php:35-39` and must be replicated exactly in the freshness handler.

## What We're NOT Doing

- No changes to the log-list endpoint — freshness is a separate read, not merged into `GET /api/v1/projects/{uuid}/logs`.
- No new project-info endpoint (`GET /api/v1/projects/{uuid}`) — that's S-04's domain.
- No denormalized `lastLogReceivedAt` field on the `Project` entity — a `MAX()` aggregation per request is sufficient at MVP data volumes with the new index.
- No "time ago" formatting — the API returns a raw ISO-8601 timestamp; the SPA formats it.
- No active alerting or push notification when ingestion goes silent — passive indicator only (PRD non-goal).

## Implementation Approach

Extend the `LogEntryRepositoryInterface` with a single aggregate method, implement it via DQL `MAX()`, wire it through a new handler (ownership check identical to the list handler), expose it via a new controller at `GET /api/v1/projects/{uuid}/freshness`. Add the DB index to the `LogEntry` entity annotation and generate the migration.

## Critical Implementation Details

### DQL MAX() bypasses Doctrine type hydration

When using `createQueryBuilder('l')->select('MAX(l.receivedAt)')->...->getSingleScalarResult()`, Doctrine returns a raw database string (e.g. `"2026-06-27 10:42:00"`) rather than a `DateTimeImmutable` — aggregate functions skip column-type mapping. The implementer must explicitly parse: `$raw !== null ? new \DateTimeImmutable($raw) : null`. This is the only non-obvious implementation detail in this slice.

---

## Phase 1: Freshness query, DB index, and API endpoint

### Overview

Add the DB index, repository method, handler, and controller. By the end of this phase the endpoint is live and ownership-checked; test coverage follows in Phase 2.

### Changes Required:

#### 1. DB index on (project_id, received_at)

**File**: `src/Logging/Domain/LogEntry.php`

**Intent**: Add a second composite index covering `received_at` alongside the project isolation key, so the `MAX(received_at) WHERE project_id = ?` query in Phase 1.2 is a fast index-range scan rather than a full project scan.

**Contract**: Add `#[ORM\Index(name: 'idx_log_entry_project_received_at', columns: ['project_id', 'received_at'])]` as a second class-level attribute, directly below the existing `idx_log_entry_project_occurred_at` attribute at line 16.

#### 2. Doctrine migration

**File**: `migrations/` (new file, generated)

**Intent**: Persist the new index to the database. Generate the migration with `./bin/console doctrine:migrations:diff`, review the generated `CREATE INDEX` statement for correctness, and commit the file.

**Contract**: The generated migration must contain exactly one `CREATE INDEX idx_log_entry_project_received_at ON log_entry (project_id, received_at)` statement and its inverse `DROP INDEX` in the down method.

#### 3. Repository interface — getLastReceivedAtByProjectId

**File**: `src/Logging/Domain/LogEntryRepositoryInterface.php`

**Intent**: Add a single aggregate method that returns the most recent `receivedAt` timestamp for a project, or `null` if no entries exist.

**Contract**: `getLastReceivedAtByProjectId(Uuid $projectId): ?\DateTimeImmutable`

#### 4. Repository implementation

**File**: `src/Logging/Infrastructure/LogEntryRepository.php`

**Intent**: Implement the new interface method using a DQL MAX aggregate. Follow the existing QueryBuilder style in `getByProjectId()`. Apply the raw-string parsing note from Critical Implementation Details.

**Contract**: `getLastReceivedAtByProjectId(Uuid $projectId): ?\DateTimeImmutable` — uses `createQueryBuilder('l')->select('MAX(l.receivedAt)')->andWhere('l.projectId = :projectId')->setParameter('projectId', $projectId, 'uuid')->getQuery()->getSingleScalarResult()`, then wraps the result as `$raw !== null ? new \DateTimeImmutable($raw) : null`.

#### 5. Application handler

**File**: `src/Logging/Application/Freshness/GetProjectFreshnessHandler.php` (new)

**Intent**: Perform the ownership check then delegate to the repository. Mirrors `ListProjectLogsHandler` exactly, minus the filter/pagination parameters.

**Contract**: `handle(Uuid $projectUuid): ?\DateTimeImmutable` — fetches project, checks `belongsToOrganization`, throws `ProjectNotFoundOrAccessDeniedException` on mismatch (same exception already used by the list handler), then returns `$this->logEntryRepository->getLastReceivedAtByProjectId($projectUuid)`.

#### 6. Controller

**File**: `src/Logging/Ui/Freshness/ProjectFreshnessController.php` (new)

**Intent**: Expose the freshness query as `GET /api/v1/projects/{projectUuid}/freshness`. Return a single-key JSON object; format the `DateTimeImmutable` as ATOM string when non-null, or emit `null`.

**Contract**: Route `#[Route(path: '/projects/{projectUuid}/freshness', name: 'logging_project_freshness', methods: ['GET'])]`. Response shape: `JsonResponse(['lastLogReceivedAt' => $ts?->format(\DateTimeInterface::ATOM)])` — the null-safe `?->` naturally emits `null` when no logs exist.

### Success Criteria:

#### Automated Verification:

- Static analysis passes: `./vendor/bin/phpstan analyse`
- Migration applies cleanly: `./bin/console doctrine:migrations:migrate --no-interaction`

#### Manual Verification:

- `GET /api/v1/projects/{uuid}/freshness` on a project with ingested logs returns `200` with a valid ISO-8601 `lastLogReceivedAt` string.
- Same endpoint on a project with no logs returns `200` with `"lastLogReceivedAt": null`.
- Request for another user's project returns `404`.
- Unauthenticated request returns `401`.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase. Phase blocks use plain bullets — the corresponding `- [ ]` checkboxes for these items live in the `## Progress` section at the bottom of the plan.

---

## Phase 2: Tests

### Overview

Add the unit and Behat coverage exercising every path introduced in Phase 1.

### Changes Required:

#### 1. Handler unit test

**File**: `tests/Unit/Logging/Application/Freshness/GetProjectFreshnessHandlerTest.php` (new)

**Intent**: Cover the three handler paths: project not found (404), project found but wrong org (404), and happy path (returns whatever the repository returns). Follows the `#[Test]`-attribute mock-based style used by `ListProjectLogsHandlerTest`.

**Contract**: Three test methods asserting: (a) `ProjectNotFoundOrAccessDeniedException` thrown when `projectRepository->getById()` returns null; (b) same exception when project exists but `belongsToOrganization()` returns false; (c) happy path — `logEntryRepository->getLastReceivedAtByProjectId()` is called with the correct `Uuid` and its return value flows through unchanged.

#### 2. Behat scenarios

**File**: `tests/Behat/Features/Logging/projectFreshness.feature` (new)

**Intent**: Integration-level coverage of the endpoint: ingest a log entry, then assert the freshness endpoint returns a non-null timestamp; assert null when the project has no logs; assert 404 for an unknown/wrong-owner project; assert 401 for an unauthenticated request.

**Contract**: Follows the camelCase file-naming convention and existing Gherkin step vocabulary (`Given the following fixtures are loaded…`, `When I send a "GET" JSON request to…`, `Then the response status code should be…`, `And the JSON node "lastLogReceivedAt" should not be null`).

### Success Criteria:

#### Automated Verification:

- Unit tests pass: `./vendor/bin/phpunit`
- Behat suite passes: `./vendor/bin/behat`
- Static analysis passes: `./vendor/bin/phpstan analyse`

#### Manual Verification:

- All new unit and Behat cases appear in test output with no regressions in `ingestAndList.feature`, `listFiltering.feature`, `ingestionAuth.feature`, or `projectIsolation.feature`.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase. Phase blocks use plain bullets — the corresponding `- [ ]` checkboxes for these items live in the `## Progress` section at the bottom of the plan.

---

## Testing Strategy

### Unit Tests:

- `GetProjectFreshnessHandlerTest`: project-not-found (null repo return → 404), wrong-org (belongsToOrganization false → 404), happy path (repository return value passes through unchanged).

### Integration Tests:

- `projectFreshness.feature`: ingest-then-freshness (non-null timestamp), no-logs project (null), wrong-owner project (404), unauthenticated (401).

### Manual Testing Steps:

1. Sign in and call `POST /api/v1/logs/ingest` to seed one log entry for the demo project.
2. Call `GET /api/v1/projects/{uuid}/freshness` and verify the returned `lastLogReceivedAt` matches the ingestion time (within a few seconds).
3. Repeat with a project UUID that has no logs; confirm `null` is returned.
4. Confirm `?severity=error` on the existing list endpoint still works — no regression.

## Performance Considerations

`MAX(received_at) WHERE project_id = ?` on the new `(project_id, received_at)` index is an O(log n) index-range scan. At MVP data volumes (single project, 7-day retention cap) this is well within the NFR's p95 ≤ 5 s / p99 ≤ 15 s freshness budget. No further optimisation is needed for this scope.

## References

- Roadmap entry: `context/foundation/roadmap.md` (S-03)
- PRD requirement: `context/foundation/prd.md` FR-009, NFR (Freshness at the boundary)
- Related plan: `context/changes/ingested-logs-queryable-list/plan.md`
- Existing list handler pattern: `src/Logging/Application/List/ListProjectLogsHandler.php:35-39`
- Existing controller pattern: `src/Logging/Ui/List/ListProjectLogsController.php`
- Existing index annotation: `src/Logging/Domain/LogEntry.php:16`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Freshness query, DB index, and API endpoint

#### Automated

- [x] 1.1 Static analysis passes: `./vendor/bin/phpstan analyse` — 7cdf7c5
- [x] 1.2 Migration applies cleanly: `./bin/console doctrine:migrations:migrate --no-interaction` — 7cdf7c5

#### Manual

- [x] 1.3 GET freshness with logs returns 200 + valid ISO-8601 lastLogReceivedAt — 7cdf7c5
- [x] 1.4 GET freshness with no logs returns 200 + null lastLogReceivedAt — 7cdf7c5
- [x] 1.5 Wrong-owner project returns 404 — 7cdf7c5
- [x] 1.6 Unauthenticated request returns 401 — 7cdf7c5

### Phase 2: Tests

#### Automated

- [x] 2.1 Unit tests pass: `./vendor/bin/phpunit`
- [ ] 2.2 Behat suite passes: `./vendor/bin/behat`
- [x] 2.3 Static analysis passes: `./vendor/bin/phpstan analyse`

#### Manual

- [x] 2.4 All new test cases pass with no regressions in existing Logging feature scenarios
