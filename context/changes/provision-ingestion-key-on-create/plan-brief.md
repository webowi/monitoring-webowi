# Generate First Ingestion Key — Plan Brief

> Full plan: `context/changes/provision-ingestion-key-on-create/plan.md`

## What & Why

The SPA is being built as a two-step project wizard: step 1 creates the project + picks a platform, step 2 generates the ingestion key and shows the copy-paste install snippet. Today there's no endpoint whose semantics match "generate the first key" — only `rotate`, which happens to tolerate a missing key but is meant for replacing one. This plan adds a dedicated `POST /api/v1/projects/{uuid}/ingestion-key` endpoint for that step 2 action.

## Starting Point

`CreateProjectHandler` creates a `Project` only — no `IngestionKey`. `GET /ingestion-key` correctly reports `status: "none"` for such a project (this is truthful behavior, not a bug). `RotateIngestionKeyHandler` already has all the pieces (`IngestionKeyGenerator`, `IngestionKeyHasher`, `InstallSnippetBuilder`) this plan reuses.

## Desired End State

A project with no key can call `POST .../ingestion-key` once to get `{keyUuid, value, snippet}` (201). Calling it again on the same project returns 409 — `rotate` remains the only way to replace an existing key. `GET .../ingestion-key` reflects the generated key immediately after.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
|---|---|---|
| When is a key created | Only on explicit step-2 request, never at project creation | Matches the wizard's two-step UX — `status: "none"` between steps is correct, not a gap to hide |
| Endpoint | New `POST .../ingestion-key`, separate from `rotate` | Keeps "generate first key" and "replace existing key" semantically distinct in the API and logs |
| Conflict handling | 409 if an active key already exists | Generate never silently no-ops or replaces — matches the `ProjectNameAlreadyExistsException` 409 pattern already used elsewhere in this bounded context |
| Test coverage | Unit tests + extended Behat feature | Proves the full wizard flow (create → none → generate → populated → 409 on repeat) over real HTTP, not just the unit contract |

## Scope

**In scope:** `IngestionKeyAlreadyExistsException`, `GenerateIngestionKeyHandler` + `GenerateIngestionKeyResult`, `GenerateIngestionKeyController`, unit tests, a fixed `uuid` on the existing keyless fixture project, extended Behat scenarios in `projectApiKey.feature`.

**Out of scope:** Any change to `CreateProjectHandler`/`CreateProjectResult`; any change to `rotate` or `GET ingestion-key`; SPA/wizard UI (separate repo per roadmap's repo-scope note); key expiry, IP allowlist, encryption at rest.

## Architecture / Approach

Mirrors `RotateIngestionKeyHandler` almost exactly — same five collaborators, same generate → hash → construct → save sequence — but swaps the "revoke existing key" branch for a "reject if one exists" guard. Route auto-discovered via the existing attribute-routing config for `src/Projects/Ui/`; no manual route registration needed.

## Phases at a Glance

| Phase | What it delivers | Key risk |
|---|---|---|
| 1. Application layer | Handler, exception, result, unit tests | None significant — closely mirrors an existing, tested handler |
| 2. Controller + Behat | HTTP endpoint, fixed fixture uuid, extended acceptance scenarios | Fixture change (`project2` gets a fixed uuid) must not break other scenarios that already reference `project2` by name-based assertions |

**Prerequisites:** None — builds entirely on infrastructure already shipped in `view-and-copy-project-api-key` (S-04).
**Estimated effort:** ~1 session across 2 phases.

## Open Risks & Assumptions

- Assumes no real (non-fixture) projects exist yet that were created via `POST /projects` before this change and now need a one-time `rotate` call to get their first key — confirm this before shipping if real usage has already started.
- The wizard UI itself lives in the separate SPA repo; this plan only delivers the API surface it needs.

## Success Criteria (Summary)

- `POST .../ingestion-key` on a keyless project returns 201 with a usable `value` + `snippet`
- Repeating the call returns 409 without altering the existing key
- `GET .../ingestion-key` reflects the generated key immediately after
