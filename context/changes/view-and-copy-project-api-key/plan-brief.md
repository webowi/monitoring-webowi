# View and Copy Project API Key — Plan Brief

> Full plan: `context/changes/view-and-copy-project-api-key/plan.md`

## What & Why

S-04 ships the three endpoints a developer needs to get their project's ingestion key and configure their Symfony app. FR-004 requires the key be viewable and copyable — but the current `IngestionKey` entity stores only an HMAC hash, never the plaintext, so a `key_value` column must be added before any reveal endpoint can work.

## Starting Point

`src/Projects/Ui/` is empty (`.gitkeep` only). The `IngestionKey` entity stores `key_hash` exclusively. `ProjectRepositoryInterface` and auth/ownership patterns are fully established — every new handler copies the same wiring as `GetProjectFreshnessHandler`.

## Desired End State

Three new endpoints are live under `/api/v1/projects/{uuid}/`:
- `GET /projects/{uuid}` — project name, platform, status
- `GET /projects/{uuid}/ingestion-key` — plaintext key value + Monolog YAML snippet with ingestion URL and key substituted in
- `POST /projects/{uuid}/ingestion-key/rotate` — revokes old key, generates a new `mon_ing_<32hex>` key, returns it (the only moment the new plaintext is exposed)

All three enforce JWT auth and organisation ownership (404 on mismatch). Behat scenarios confirm success, 401, 404, and old-key rejection after rotation.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
|---|---|---|---|
| Key storage model | Store plaintext in new `key_value` column | Simplest path for pre-seeded internal MVP; encryption deferred to pre-public-launch | Plan |
| Endpoint count | Two read + one write (3 total) | Project info and key are distinct resources; the SPA needs project metadata independently of the key | Plan |
| Key rotation scope | In S-04 | Pairs naturally with the storage change; provides self-service recovery without waiting for v2 | Plan |
| Snippet ownership | API returns the YAML string | API owns the correct ingestion URL; SPA just renders what it receives | Plan |
| Exception location | New `src/Projects/Application/Exception/` | Avoids Projects→Logging backwards domain dependency | Plan |

## Scope

**In scope:** `key_value` column + migration; `findActiveByProjectId` + `save` on repository; `GetProjectHandler`, `GetIngestionKeyHandler`, `RotateIngestionKeyHandler`; `InstallSnippetBuilder`; `IngestionKeyGenerator`; three controllers; unit tests for all handlers + generator + builder; Behat feature.

**Out of scope:** Key encryption at rest; project CRUD; key expiry / IP allowlist; frontend masking; `APP_URL` tooling beyond `.env` addition.

## Architecture / Approach

Standard Symfony DDD-lite stack. New application handlers in `src/Projects/Application/{GetProject,GetIngestionKey,RotateIngestionKey}/` follow the `handle(Uuid): Result` pattern established by `GetProjectFreshnessHandler`. Controllers in `src/Projects/Ui/` follow `ProjectFreshnessController`. `InstallSnippetBuilder` is a plain service wired with `%env(APP_URL)%`. `IngestionKeyGenerator` lives in Infrastructure (`Security/`) alongside `IngestionKeyHasher`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
|---|---|---|
| 1. Domain & schema | `key_value` column, migration, repository additions, fixture update, `APP_URL` env | Migration must be nullable (existing rows have no plaintext) |
| 2. GET endpoints | Project info + ingestion-key read endpoints with unit tests | None significant — pure read path |
| 3. POST rotate + Behat | Rotation write path + full acceptance test suite | Behat rotation scenario depends on `findActiveByProjectId` from Phase 1 working correctly |

**Prerequisites:** Phase 1 must be committed before Phase 2 (fixture + migration needed). Phase 3 can begin immediately after Phase 2 (no new infrastructure needed).

**Estimated effort:** ~2 sessions across 3 phases.

## Open Risks & Assumptions

- `key_value` stores plaintext — acceptable for pre-seeded internal MVP, must be addressed before public launch (v2 task).
- `InstallSnippetBuilder` snippet format (exact Monolog handler type) is left to the implementer; the constraint is that MonologBundle 3.x must support the chosen type.
- Rotation creates a new `IngestionKey` row (old row is revoked, not deleted) — `ingestion_key` table will accumulate revoked rows over time; no cleanup is planned for MVP.

## Success Criteria (Summary)

- `GET /ingestion-key` returns `value: "mon_ing_demo0000000000000000000000000000"` against the seeded fixture
- `POST /ingestion-key/rotate` returns a new key; old key gets 401 on the ingest endpoint (Behat-verified)
- All three endpoints return 404 with `{"error":"Project not found."}` for wrong-org JWT
