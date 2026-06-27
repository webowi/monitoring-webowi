# Log Freshness Indicator — Plan Brief

> Full plan: `context/changes/log-freshness-indicator/plan.md`

## What & Why

Add a passive ingestion-health signal per FR-009: the SPA needs to show "last log received N seconds ago" (or "no logs received") on each project page so a developer can detect silent ingestion failure without active alerting. This is a dedicated `GET /api/v1/projects/{uuid}/freshness` endpoint returning the `MAX(received_at)` timestamp across the project's log entries.

## Starting Point

S-01 and S-02 shipped the ingest → store → list path. `LogEntry` already carries `receivedAt` (server-arrival timestamp, set unconditionally at `LogEntry::create()`) but no API surface exposes it. `src/Projects/Ui/` is empty; no project-level endpoint exists yet.

## Desired End State

`GET /api/v1/projects/{uuid}/freshness` returns `{ "lastLogReceivedAt": "<ISO-8601>" }` when logs exist, or `{ "lastLogReceivedAt": null }` when none have arrived. The query is backed by a new `(project_id, received_at)` DB index for fast O(log n) lookups. Ownership is enforced via the same `belongsToOrganization` check used throughout the logging layer.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
|---|---|---|---|
| Endpoint placement | Dedicated `/projects/{uuid}/freshness` | Zero coupling with S-04's project-info endpoint; both slices ship independently | Plan |
| Freshness timestamp | `receivedAt` (server arrival) | Directly answers "is ingestion alive?" — `occurredAt` could appear stale even when the queue is healthy | Plan |
| Query strategy | `MAX(received_at)` aggregation per request | Sufficient at MVP volumes with the new index; denormalizing on Project adds transaction complexity for no gain | Plan |
| DB index | New `(project_id, received_at)` | Parallels existing `(project_id, occurred_at)`; makes the MAX scan O(log n) | Plan |
| Test scope | PHPUnit handler + Behat scenario | Matches the coverage pattern from S-01/S-02 | Plan |

## Scope

**In scope:**
- New `GET /api/v1/projects/{uuid}/freshness` endpoint
- `getLastReceivedAtByProjectId()` method on `LogEntryRepositoryInterface` + `LogEntryRepository`
- `GetProjectFreshnessHandler` with ownership check
- New Doctrine index on `log_entry(project_id, received_at)` + migration
- PHPUnit handler test + `projectFreshness.feature` Behat scenarios

**Out of scope:**
- No changes to the log-list endpoint
- No new `GET /api/v1/projects/{uuid}` project-info endpoint (S-04's domain)
- No denormalized field on `Project` entity
- No "time ago" string formatting (SPA's responsibility)
- No active alerting

## Architecture / Approach

Plain DQL `MAX()` aggregation through the existing repository → handler → controller chain. The one non-obvious detail: Doctrine's `getSingleScalarResult()` on an aggregate bypasses column-type hydration and returns a raw database string, so the repository must parse it manually (`new \DateTimeImmutable($raw)`).

## Phases at a Glance

| Phase | What it delivers | Key risk |
|---|---|---|
| 1. Freshness query, DB index & API endpoint | Working endpoint with ownership check and DB index | DQL MAX raw-string parsing quirk (documented in Critical Implementation Details) |
| 2. Tests | PHPUnit handler test + Behat freshness scenarios | Behat requires test DB reachable (same pre-existing infra caveat as S-02) |

**Prerequisites:** S-01 and S-02 merged (done); dev DB running for manual verification.
**Estimated effort:** ~1 session across 2 phases.

## Open Risks & Assumptions

- Behat test DB infra may still be unreachable (pre-existing issue from S-02); Phase 2's Behat step carries the same caveat.
- `new \DateTimeImmutable($raw)` parsing of MySQL's `DATETIME` string format (`Y-m-d H:i:s`) works correctly in PHP — confirmed by standard PHP behaviour, but worth a quick local test before committing.

## Success Criteria (Summary)

- `GET /api/v1/projects/{uuid}/freshness` returns a valid ISO-8601 timestamp after ingestion and `null` before any logs arrive.
- Wrong-owner project returns `404`; unauthenticated request returns `401`.
- PHPUnit and static analysis pass clean; Behat passes if infra is available.
