# Filter Logs by Severity and HTTP Code — Plan Brief

> Full plan: `context/changes/filter-logs-by-severity-and-http-code/plan.md`

## What & Why

Add severity-level and HTTP-status-code filtering to the existing log list endpoint. This completes S-02 / FR-008 — the second piece of the PRD's Primary Success Criterion (US-01's "applies the HTTP-code filter for 500" step), unlocked now that S-01's ingest→store→list pipe is real.

## Starting Point

`GET /api/v1/projects/{uuid}/logs` already exists and works (S-01): JWT-scoped, ownership-checked, paginated, reverse-chronological. `ListProjectLogsInput` currently has only `limit`/`offset`. `LogEntry` already stores a typed `LogSeverityEnum` and a nullable `httpStatusCode` column — no schema changes needed, this is purely query-layer work.

## Desired End State

A developer calls the same endpoint with `?severity=error,critical`, `?httpStatusCode=500`, `?httpStatusCode=5xx`, or any combination, and gets back only the matching entries (AND semantics across both axes). Omitting both params behaves exactly as before. An unrecognized severity token or malformed status-code value returns `422`, same as today's `limit`/`offset` validation.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Severity matching | Multi-select exact match | Matches FR-008's literal wording; lets the SPA show several checked severity boxes in one request. |
| Severity query format | Comma-separated single param (`?severity=error,critical`) | Avoids introducing array-query-param handling, which has no precedent in this codebase. |
| HTTP-code matching | Exact code or class shorthand (`500` or `5xx`) | Covers both the PRD's literal "show only 500s" example and the common "show all server errors" dashboard need. |
| HTTP-code query format | One param, dual format | Keeps one filter axis = one query param, matching how the PRD frames it; unified into a single min/max range at the repository level. |
| Filter combination | AND | Standard, unsurprising filter-panel semantics for two independently-labeled controls. |
| Invalid filter values | Reject with 422 | Reuses the exact validation pattern `limit`/`offset` already use — zero new error-handling code. |

## Scope

**In scope:** Extending `ListProjectLogsInput`, `ListProjectLogsHandler`, `LogEntryRepositoryInterface`/Doctrine impl, and `ListProjectLogsController` with severity + HTTP-code filters; new translation entries; unit + Behat test coverage.

**Out of scope:** New routes, OR-combination, minimum-severity-threshold mode, full-text search, schema changes, ingestion-side changes.

## Architecture / Approach

Two new optional query params parsed and validated on `ListProjectLogsInput` (comma-split + enum-membership check for severity; regex + exact/class parsing for HTTP code), threaded unchanged through the handler, and applied as additional `andWhere` clauses (`IN` for severity, unified `BETWEEN min AND max` for HTTP code) in the Doctrine repository.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Filter input & validation | Parsing + validation on `ListProjectLogsInput`, translated error messages | New ground: multi-value + dual-format query params have no codebase precedent |
| 2. Repository + handler wiring | Filters actually narrow query results | Doctrine enum-array parameter binding in an `IN()` clause is untested in this codebase — needs verification |
| 3. Tests | Unit + Behat coverage for every filter path | None — mechanical coverage of already-built behavior |

**Prerequisites:** S-01 (`ingested-logs-queryable-list`) must be merged — it is, per repo history.
**Estimated effort:** ~2-3 sessions, one per phase.

## Open Risks & Assumptions

- Whether Doctrine's `enumType`-mapped column accepts an array of enum instances or backing-value strings inside a manual `IN (:x)` clause is unverified — flagged in the plan's Critical Implementation Details for the implementer to confirm against the dev DB in Phase 2.

## Success Criteria (Summary)

- Filtering by severity, by HTTP code (exact or class), and by both together (AND) all return the correct narrowed result set.
- Omitting both filters preserves today's behavior exactly (no regression).
- Invalid filter values return 422 with a translated message; full PHPUnit + Behat suites and `phpstan analyse` stay green.
