# Ingested Logs Queryable List (S-01) — Plan Brief

> Full plan: `context/changes/ingested-logs-queryable-list/plan.md`

## What & Why

Build the smallest end-to-end pipe that proves Monitoring Webowi's core hypothesis: a Symfony app's logs, sent via a per-project API key, are accepted without blocking the host app, stored asynchronously, and listed reverse-chronologically by their owner. This is the roadmap's north star (S-01) and the PRD's only user story (US-01).

## Starting Point

`src/Logging/{Domain,Application,Infrastructure,Ui}` are empty placeholders — nothing exists yet. The roadmap's prerequisite, F-01 (async transport), is also unbuilt despite showing "ready" status: no `symfony/messenger` dependency, no config, no transport table. Security is JWT-only with no API-key mechanism. Worse, `Project.php`/`IngestionKey.php` currently have **uncommitted broken references** (undeclared properties, a missing `setProject()`, a repository alias bug) left over from an unwired, dead project-creation wizard — this plan fixes that domain model as a prerequisite, since the ownership check and key lookup depend on it.

## Desired End State

A developer POSTs a log record with their project's API key and gets an immediate `202`; a background worker picks it up and stores it. Signed in with their JWT, they GET their project's logs and see them most-recent-first, with HTTP status code and exception class already broken out into their own fields. A different organization's user requesting the same project gets a `404`, never the data.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Ingest request flow | Auth-check + enqueue only, no sync DB write | Fastest possible response to the host app's blocking call — directly serves FR-006/host-app-safety. |
| API-key auth mechanism | Custom Symfony Security authenticator + new firewall | Idiomatic Symfony, integrates with Security component plumbing (user's explicit choice over a simpler inline check). |
| Dev worker | New `worker` service in docker-compose.yml now | User chose production-shaped dev loop over deferring to F-02, accepting some throwaway risk. |
| HTTP-code/exception normalization | Extracted into dedicated columns at ingest time | Matches PRD's Business Logic section's described v1 output shape; avoids a backfill migration when S-02 lands. |
| Pagination | Simple limit/offset (default 50, max 200) | Matches PRD's stated small/low-volume MVP scale; 7-day retention caps total rows anyway. |
| Error codes | Standard REST codes (401/422/202/429) | Debuggable, matches FR-002's explicit "rejects the payload" requirement. |
| Retention/pruning | Deferred — out of scope for S-01 | Not among S-01's PRD refs (FR-005/006/007); no slice currently owns this NFR; avoids a half-finished cron-less command. |
| Per-project volume cap | Added now via existing `symfony/rate-limiter` | User chose to reuse the already-installed bundle (one existing `gus_api` policy to follow) over deferring. |
| Fixture API key | Fixed, known dev-only plaintext value | Stable, copy-pasteable across manual testing and Behat scenarios. |
| Test coverage | Behat feature + targeted PHPUnit units | Matches the repo's existing black-box API testing convention (Behat), not a second parallel pattern. |
| Ownership enforcement | Inline repository-scoped query, not a Voter | User's explicit choice — simpler for this one endpoint, no new Security abstraction. |

## Scope

**In scope:** Messenger async transport (F-01), `Project`/`IngestionKey` domain-model fixes, `LogEntry` storage, API-key ingestion endpoint, JWT list endpoint, dev fixtures, Behat + unit test coverage.

**Out of scope:** F-02 (prod compose/`/health`), the actual host-app Monolog snippet (S-04), severity/HTTP-code filtering (S-02), freshness indicator (S-03), 7-day retention/pruning, fixing the dead `CreateProjectWithFirstKey` wizard.

## Architecture / Approach

`POST /api/v1/logs/ingest` (new API-key firewall, ahead of the catch-all JWT firewall) → validates payload → dispatches `IngestLogEntryMessage` to a Doctrine-backed `async` Messenger transport → a worker consumes it, normalizes context into `LogEntry` columns, persists. `GET /api/v1/projects/{uuid}/logs` (existing JWT firewall) → ownership check via scalar `organizationId` comparison → paginated read from `LogEntry`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Foundation | Messenger transport + fixed Project/IngestionKey domain model | Touches entities with pre-existing broken state; must not reintroduce the bug |
| 2. Log storage | `LogEntry` entity, repository, migration | Low — additive only |
| 3. Ingestion endpoint | API-key authenticator, rate limiter, async handler | Firewall/access_control ordering is a real gotcha (see plan's Critical Implementation Details) |
| 4. List endpoint | JWT-scoped, paginated GET | Ownership-check correctness is a PRD-flagged "fatal regression" if wrong |
| 5. Fixtures & tests | Dev seed data, Behat + PHPUnit coverage | Behat may need a new "set header" step for the ingestion key |

**Prerequisites:** None beyond what's in this repo today (no external service decisions needed).
**Estimated effort:** ~5 sessions, one per phase.

## Open Risks & Assumptions

- The exact `messenger_messages`/`log_entry` migration file names/timestamps are generated at implementation time via `doctrine:migrations:diff`, not pre-specified here.
- The ingestion JSON contract (`datetime`/`level`/`message`/`context.http_status_code`/`context.exception.class`) is defined fresh in this plan since no Monolog HTTP handler convention exists yet in this codebase — S-04's install instructions must match it.
- Assumes `tests/Behat/Context/JSON/JSONMainContext.php` either already supports setting arbitrary request headers or can be extended cheaply (not fully read during planning — flagged for the implementer to verify in Phase 5).

## Success Criteria (Summary)

- A log POSTed with a valid project key returns `202` and appears via the GET endpoint within a few seconds, with HTTP status/exception class populated when present.
- A different organization's user can never see another project's logs (`404`, not data).
- Full Behat + PHPUnit suites pass; `phpstan analyse` is clean.
