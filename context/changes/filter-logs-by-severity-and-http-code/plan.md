# Filter Logs by Severity and HTTP Code Implementation Plan

## Overview

Add two additive query-parameter filters — Monolog severity level (multi-select) and HTTP status code (exact or class-shorthand) — to the existing `GET /api/v1/projects/{projectUuid}/logs` endpoint. This completes S-02 / FR-008: the developer can narrow the log list to specific severities, specific HTTP codes, or both at once.

## Current State Analysis

S-01 (`ingested-logs-queryable-list`) shipped the full read path this plan extends:

- `ListProjectLogsController` (`src/Logging/Ui/List/ListProjectLogsController.php:23-28`) maps query params via `#[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]` into `ListProjectLogsInput`, then calls `$this->handler->handle(Uuid::fromString($projectUuid), $input->limit, $input->offset)`.
- `ListProjectLogsInput` (`src/Logging/Ui/List/ListProjectLogsInput.php:11-21`) is a `#[Exclude]` readonly DTO with only `limit` (`Assert\Range(min: 1, max: 200)`, default 50) and `offset` (`Assert\GreaterThanOrEqual(0)`, default 0).
- `ListProjectLogsHandler::handle()` (`src/Logging/Application/List/ListProjectLogsHandler.php:25-35`) does the ownership check (`Project::belongsToOrganization()`, throws `ProjectNotFoundOrAccessDeniedException` → 404 on mismatch) then delegates straight to the repository.
- `LogEntryRepositoryInterface::getByProjectId()` (`src/Logging/Domain/LogEntryRepositoryInterface.php:16`) and its Doctrine implementation (`src/Logging/Infrastructure/LogEntryRepository.php:41-54`) build a QueryBuilder with one `andWhere('l.projectId = :projectId')`, `orderBy('l.occurredAt', 'DESC')`, limit/offset, `toIterable()`.
- `LogEntry` (`src/Logging/Domain/LogEntry.php:42-52`) has `public readonly LogSeverityEnum $severity` (`enumType: LogSeverityEnum::class`, `Types::STRING`) and `public readonly ?int $httpStatusCode` (`Types::SMALLINT`, nullable).
- `LogSeverityEnum` (`src/Logging/Domain/LogSeverityEnum.php:7-17`) is a string-backed enum: `DEBUG='debug'`, `INFO='info'`, `NOTICE='notice'`, `WARNING='warning'`, `ERROR='error'`, `CRITICAL='critical'`, `ALERT='alert'`, `EMERGENCY='emergency'`. The ingestion side (`IngestLogInput`) already accepts these lowercase backing values via `#[MapRequestPayload]`'s automatic enum deserialization — the filter param will match the same lowercase convention for consistency.
- Custom validation messages follow a `validation.<field>.<rule>` translation-key convention (`src/Identity/Domain/User/User.php:27` → `message: 'validation.email.alreadyExists'`, resolved via `translations/validators.pl.yaml`'s nested `validation:` root key).
- No precedent exists in this codebase for a multi-value or dual-format query parameter — both are new ground, scoped tightly below.
- Existing test coverage: `tests/Unit/Logging/Application/List/ListProjectLogsHandlerTest.php` (mock-based, asserts exact repository call args) and `tests/Behat/Features/Logging/ingest_and_list.feature` (Gherkin, ingest-then-list happy path).

## Desired End State

`GET /api/v1/projects/{uuid}/logs?severity=error,critical&httpStatusCode=500` returns only entries matching both an ERROR-or-CRITICAL severity AND an exact 500 status code. `?httpStatusCode=5xx` returns all entries with a status code in 500-599. Filters combine with AND. Omitting either param preserves today's unfiltered behavior. An unknown severity token or a malformed `httpStatusCode` value returns `422` with a translated error, via the same `#[MapQueryString]` validation path `limit`/`offset` already use — no new exception types or error-handling code.

### Key Discoveries:

- The HTTP-code dual format (exact `500` vs class `5xx`) can be unified into a single `BETWEEN :min AND :max` clause at the repository level: exact sets `min = max = 500`; class sets `min = 500, max = 599`. This avoids branching the QueryBuilder logic per format.
- `#[MapQueryString]` deserializes scalar properties straight from the query string before validation runs; multi-value severity and dual-format HTTP code both arrive as plain strings on the DTO and are parsed by methods on the DTO itself, not by the serializer.

## What We're NOT Doing

- No new route — same `GET /api/v1/projects/{projectUuid}/logs` endpoint, additive query params only.
- No OR combination between severity and HTTP-code filters (locked to AND per planning decision).
- No minimum-severity-threshold mode (locked to multi-select exact match).
- No full-text search, log grouping, or any other PRD non-goal.
- No changes to the ingestion endpoint, `LogEntry` schema, or `LogSeverityEnum` itself.

## Implementation Approach

Extend the existing DTO → handler → repository chain with two new optional, independently-parseable filter inputs. `ListProjectLogsInput` owns parsing (comma-split severity, exact/class HTTP-code range) and validation (via `#[Assert\Callback]`, since neither shape — a comma list validated against enum cases, nor a regex-constrained dual-format string — is expressible with built-in constraint attributes alone). The controller passes the DTO's parsed values (not raw strings) to the handler, which threads them unchanged to the repository, which adds two more `andWhere` clauses only when a filter is actually present.

## Critical Implementation Details

### Enum array parameter binding

When binding the severity filter as `andWhere('l.severity IN (:severities)')`, verify whether Doctrine's `enumType` column mapping requires the bound parameter to be an array of `LogSeverityEnum` instances or an array of their scalar backing values (`array_map(fn (LogSeverityEnum $s) => $s->value, $severities)`). This project's other enum usage (the `LogEntry` entity property itself) relies on Doctrine's automatic enum (de)hydration for column reads/writes, but parameter binding inside a manually-constructed `IN (:x)` clause is a different code path and isn't exercised anywhere else in this codebase yet — confirm with a quick local test against the dev DB before assuming either form works.

## Phase 1: Filter input & validation

### Overview

Extend `ListProjectLogsInput` with the two new filter properties, their parsing helpers, and validation — so by the end of this phase, invalid query values already 422 correctly even though the filters aren't wired to the query yet.

### Changes Required:

#### 1. Filter properties, parsing, and validation

**File**: `src/Logging/Ui/List/ListProjectLogsInput.php`

**Intent**: Add `public ?string $severity = null` (comma-separated lowercase severity tokens, e.g. `error,critical`) and `public ?string $httpStatusCode = null` (either a 3-digit exact code like `500` or a class shorthand like `5xx`). Add an `#[Assert\Callback]` method validating that every comma-separated `severity` token matches a `LogSeverityEnum` backing value (case-sensitive, matching the ingestion endpoint's existing convention) and that `httpStatusCode`, if present, matches `^[1-5][0-9]{2}$` or `^[1-5]xx$`. Add two read methods consumed by the controller: one returning `array<LogSeverityEnum>` (empty array when `severity` is null), and one returning either `null` or a `[min, max]` int pair (exact code → `[code, code]`; class shorthand → `[base*100, base*100+99]`).

**Contract**: Validation failure surfaces through the existing `#[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]` path on the controller — no controller change needed for this phase. Violation messages use the `validation.severity.invalid` / `validation.httpStatusCode.invalid` translation-key convention (`message` parameter on the built violation), matching `src/Identity/Domain/User/User.php:27`'s existing pattern.

#### 2. Translation entries

**File**: `translations/validators.pl.yaml`

**Intent**: Add Polish translations for the two new violation messages under the existing `validation:` root key, alongside `email`/`password`/`token`/`tin`.

**Contract**: New `validation.severity.invalid` and `validation.httpStatusCode.invalid` keys, each taking the invalid token as a `{{ value }}` placeholder (matches the existing placeholder convention, e.g. `password.minLength`'s `{{ limit }}`).

### Success Criteria:

#### Automated Verification:

- Static analysis passes: `./vendor/bin/phpstan analyse`

#### Manual Verification:

- `GET /api/v1/projects/{uuid}/logs?severity=bogus` returns `422` with a translated Polish error message.
- `GET /api/v1/projects/{uuid}/logs?httpStatusCode=999` and `?httpStatusCode=9xx` both return `422`.
- `GET /api/v1/projects/{uuid}/logs?severity=error,critical&httpStatusCode=5xx` returns `200` (filters not yet applied to results until Phase 2, but the request itself must validate cleanly).

---

## Phase 2: Repository + handler wiring

### Overview

Thread the parsed filter values from controller → handler → repository, and add the corresponding `andWhere` clauses so the filters actually narrow the result set.

### Changes Required:

#### 1. Repository interface + Doctrine implementation

**File**: `src/Logging/Domain/LogEntryRepositoryInterface.php`, `src/Logging/Infrastructure/LogEntryRepository.php`

**Intent**: Extend `getByProjectId()` with two new optional parameters: `array $severities = []` and a nullable HTTP-code range. When `$severities` is non-empty, add `andWhere('l.severity IN (:severities)')`. When the HTTP-code range is present, add `andWhere('l.httpStatusCode BETWEEN :httpStatusCodeMin AND :httpStatusCodeMax')` using the unified min/max approach from Critical Implementation Details — both exact-code and class-shorthand filters resolve to the same clause shape. Neither clause is added when its corresponding filter is absent, preserving today's unfiltered behavior exactly.

**Contract**: `getByProjectId(Uuid $projectId, int $limit, int $offset, array $severities = [], ?int $httpStatusCodeMin = null, ?int $httpStatusCodeMax = null): iterable` — both new trailing parameters default to "no filter" so existing callers (none outside this list path) are unaffected.

#### 2. Handler

**File**: `src/Logging/Application/List/ListProjectLogsHandler.php`

**Intent**: Accept the same new parameters and pass them straight through to `LogEntryRepositoryInterface::getByProjectId()`, after the existing ownership check. No new logic — pure pass-through.

**Contract**: `handle(Uuid $projectUuid, int $limit, int $offset, array $severities = [], ?int $httpStatusCodeMin = null, ?int $httpStatusCodeMax = null): iterable`.

#### 3. Controller wiring

**File**: `src/Logging/Ui/List/ListProjectLogsController.php`

**Intent**: Call the DTO's new read methods (added in Phase 1) to get `array<LogSeverityEnum>` and the `[min, max]` pair (or `null`), and pass them into `$this->handler->handle(...)` alongside the existing `limit`/`offset` arguments.

**Contract**: No route or response-shape change — same `200` JSON array response as today, now filtered.

### Success Criteria:

#### Automated Verification:

- Static analysis passes: `./vendor/bin/phpstan analyse`

#### Manual Verification:

- With seeded fixture log entries spanning multiple severities and HTTP codes, `?severity=error` returns only ERROR entries; `?severity=error,critical` returns ERROR and CRITICAL entries.
- `?httpStatusCode=500` returns only exact-500 entries; `?httpStatusCode=5xx` returns all 500-599 entries.
- `?severity=error&httpStatusCode=500` returns only entries matching both (AND, not OR).
- Omitting both params still returns the full unfiltered, paginated list exactly as before this change.

---

## Phase 3: Tests

### Overview

Add the unit and Behat coverage exercising every filter path introduced in Phases 1-2 — the prior two phases are correct by code review until this phase proves it.

### Changes Required:

#### 1. Unit tests for the Input DTO

**File**: `tests/Unit/Logging/Ui/List/ListProjectLogsInputTest.php` (new)

**Intent**: Cover the new parsing/validation surface added in Phase 1: valid single severity, valid multi-severity, an invalid severity token producing a constraint violation; a valid exact `httpStatusCode`, a valid class-shorthand `httpStatusCode`, an invalid format producing a violation; the two read methods returning the correct `LogSeverityEnum[]` / `[min, max]` shapes, including the `null`/empty-array case when a filter param is absent.

**Contract**: Follows the existing PHPUnit `#[Test]`-attribute style used by `ListProjectLogsHandlerTest`.

#### 2. Unit test updates for the handler

**File**: `tests/Unit/Logging/Application/List/ListProjectLogsHandlerTest.php`

**Intent**: Update the existing mock-based test(s) so the repository expectation matches the handler's new parameter list, and add cases asserting severities-only, HTTP-range-only, and both-combined filter arguments pass through unchanged to `LogEntryRepositoryInterface::getByProjectId()`.

**Contract**: Same `expects()->method('getByProjectId')->with(...)` mock-assertion style already in the file (see existing example asserting `->with($projectUuid, 50, 0)`).

#### 3. Behat scenarios

**File**: `tests/Behat/Features/Logging/ingest_and_list.feature`

**Intent**: Add scenarios covering: filter by a single severity, filter by multiple comma-separated severities, filter by an exact HTTP code, filter by an HTTP-code class shorthand, a combined severity+code request (proving AND semantics by including a fixture entry that matches only one axis and confirming it's excluded), an invalid severity value returning 422, and an invalid httpStatusCode value returning 422.

**Contract**: Follows the existing Gherkin step style in this file (`Given I set the header...`, `When I send a "GET" JSON request to...`, `Then the response status code should be...`, `And the JSON node "..." should be equal to...`).

### Success Criteria:

#### Automated Verification:

- Unit tests pass: `./vendor/bin/phpunit`
- Behat suite passes: `./vendor/bin/behat`
- Static analysis passes: `./vendor/bin/phpstan analyse`

#### Manual Verification:

- Test run output shows all new scenarios/cases passing, with no regressions in previously-passing `ingest_and_list.feature`, `ingestion_auth.feature`, or `project_isolation.feature` scenarios.

---

## Testing Strategy

### Unit Tests:

- `ListProjectLogsInput`: valid single severity, valid multi-severity, invalid severity token (422 violation present); valid exact `httpStatusCode`, valid class-shorthand `httpStatusCode`, invalid format (422 violation present); parsing methods return correct `LogSeverityEnum[]` / `[min, max]` shapes including the `null` case when the param is absent.
- `ListProjectLogsHandlerTest`: existing test updated to assert the new parameters pass through to the repository call unchanged; one new case per non-default filter combination (severities only, HTTP range only, both).

### Integration Tests:

- Behat scenarios added to `tests/Behat/Features/Logging/ingest_and_list.feature` (or a new sibling feature file if scenario count grows unwieldy): filter by single severity, filter by multiple severities, filter by exact HTTP code, filter by HTTP-code class shorthand, combined severity+code (AND semantics), invalid severity value (422), invalid HTTP-code value (422).

### Manual Testing Steps:

1. Ingest several log entries via the existing `POST /api/v1/logs/ingest` fixture flow, varying severity and `context.http_status_code`.
2. Sign in as the seeded user and call the list endpoint with each filter combination above; confirm result sets match expectations.
3. Confirm pagination (`limit`/`offset`) still works correctly when combined with an active filter.

## Performance Considerations

None beyond what S-01 already established — added `andWhere` clauses on already-indexed-by-`projectId` queries at MVP data volumes (7-day retention cap); no new indexes required for this scope.

## References

- Related plan: `context/changes/ingested-logs-queryable-list/plan.md`
- Roadmap entry: `context/foundation/roadmap.md` (S-02)
- PRD requirement: `context/foundation/prd.md` FR-008
- Existing list endpoint: `src/Logging/Ui/List/ListProjectLogsController.php:23-28`
- Existing repository query: `src/Logging/Infrastructure/LogEntryRepository.php:41-54`
- Existing translation-key convention: `src/Identity/Domain/User/User.php:27`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Filter input & validation

#### Automated

- [x] 1.1 Static analysis passes: `./vendor/bin/phpstan analyse`

#### Manual

- [ ] 1.2 Invalid severity value returns 422 with translated Polish error message
- [ ] 1.3 Invalid httpStatusCode value (exact and class format) returns 422
- [ ] 1.4 Valid combined filter request returns 200 (pre-filtering, validates cleanly)

### Phase 2: Repository + handler wiring

#### Automated

- [ ] 2.1 Static analysis passes: `./vendor/bin/phpstan analyse`

#### Manual

- [ ] 2.2 Single and multi-severity filters narrow results correctly
- [ ] 2.3 Exact and class-shorthand httpStatusCode filters narrow results correctly
- [ ] 2.4 Combined severity+httpStatusCode filter applies AND semantics
- [ ] 2.5 Omitting both filters preserves prior unfiltered behavior

### Phase 3: Tests

#### Automated

- [ ] 3.1 Unit tests pass: `./vendor/bin/phpunit`
- [ ] 3.2 Behat suite passes: `./vendor/bin/behat`
- [ ] 3.3 Static analysis passes: `./vendor/bin/phpstan analyse`

#### Manual

- [ ] 3.4 All new scenarios/cases pass with no regressions in existing Logging feature scenarios
