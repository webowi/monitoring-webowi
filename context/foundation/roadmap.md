---
project: "Monitoring Webowi"
version: 1
status: draft
created: 2026-06-17
updated: 2026-06-17
prd_version: 1
main_goal: speed
top_blocker: decisions
---

# Roadmap: Monitoring Webowi

> Derived from `context/foundation/prd.md` (v1) + auto-researched codebase baseline.
> Edit-in-place; archive when superseded.
> Slices below are listed in dependency order. The "At a glance" table is the index.

## Vision recap

A solo Symfony developer running side-projects on a self-hosted VPS has no fast way to see prod errors — raw rotating `prod.log` files are slow to read and can rotate the answer away. Monitoring Webowi's insight is Symfony-bundle ergonomics: a near-zero-config install (today a paste-in Monolog snippet, a real `composer require`-able bundle later) that turns a Symfony app's logs into a readable, filterable stream, with a freshness signal so silent ingestion failure is visible without active alerting.

**Repo-scope note:** this repository is the API-only backend. The web UI described throughout the PRD (project page, log list, filters) will be built as a separate SPA project consuming this API — confirmed during roadmap shaping. Every slice below ends at the API contract, not a browser screen.

## North star

**S-01: A Symfony app's logs flow into a queryable list via the API** — the smallest end-to-end slice that proves the core hypothesis (ingest → store, fail-open → list) actually works; everything else (filtering, freshness, key UX) is additive once this pipe is real.

> A reader-facing gloss: the "north star" here means the smallest end-to-end slice whose successful delivery proves the core product hypothesis — sequenced first because nothing else in this roadmap matters if this one doesn't work.

## At a glance

| ID    | Change ID                              | Outcome (user can …)                                                          | Prerequisites          | PRD refs                              | Status   |
| ----- | --------------------------------------- | ------------------------------------------------------------------------------ | ----------------------- | --------------------------------------- | -------- |
| F-01  | async-ingestion-transport               | (foundation) fail-open async log dispatch transport is configured              | —                        | FR-006                                  | ready    |
| F-02  | production-deploy-skeleton              | (foundation) VPS+Coolify platform decided; minimal prod-safe compose + `/health` | —                       | Primary Success Criterion, NFR (Freshness at the boundary) | blocked  |
| S-01  | ingested-logs-queryable-list            | a Symfony app's logs are ingested and listable reverse-chronologically via API | F-01, seeded project+key | US-01, FR-001, FR-002, FR-003, FR-005, FR-006, FR-007 | proposed |
| S-02  | filter-logs-by-severity-and-http-code   | filter the visible log list by severity level and HTTP status code            | S-01                    | US-01, FR-008                           | proposed |
| S-03  | log-freshness-indicator                 | see when the most recent log was received, per project                       | S-01                    | FR-009, NFR (Freshness at the boundary) | proposed |
| S-04  | view-and-copy-project-api-key           | view/reveal the project's API key and install-snippet instructions            | seeded project+key      | FR-004                                  | ready    |

## Streams

Navigation aid — groups items that share a Prerequisites chain. Canonical ordering still lives in the dependency graph below; this table is the proposed reading order across parallel tracks.

| Stream | Theme                          | Chain                          | Note                                                                                   |
| ------ | ------------------------------- | ------------------------------- | ---------------------------------------------------------------------------------------- |
| A      | Core ingestion & visibility     | `F-01` → `S-01` → `S-02` / `S-03` | The must-have path under a speed-to-launch goal; the north star sits at the head.        |
| B      | Production readiness            | `F-02`                          | Runs parallel to Stream A; needed before S-01's "in production" acceptance criteria can be verified for real, but doesn't block writing S-01's code. |
| C      | Key visibility & install UX     | `S-04`                          | Standalone; no dependency on Stream A. Worth shipping early so a developer actually has a key to paste into the install snippet. |

## Baseline

What's already in place in the codebase as of `2026-06-17` (auto-researched + user-confirmed).
Foundations below assume these are present and do NOT re-scaffold them.

- **Frontend:** out of scope for this repository — confirmed during shaping that the web UI is a separate SPA project consuming this API. Legacy Twig/AssetMapper dashboard scaffolding (`assets/dashboard-ui/`, `templates/dashboard/*`) predates this decision and is dead weight (see `## Parked`).
- **Backend / API:** present — Symfony 7.4, `/api/v1` routes wired for Identity and Projects (`config/routes.yaml`).
- **Data:** present — Doctrine ORM + MySQL 8, migration `Version20260308163610` creates `user`, `organization`, `project`, `ingestion_key`, `password_token` tables.
- **Auth:** present — JWT (lexik) sign-in, refresh tokens, and 2FA TOTP are fully implemented and tested (`SignInController` + handler). This already satisfies FR-001 and FR-003's access-control rule. Confirmed during shaping: the broader Organization/sign-up build-out (GUS company lookup, a reserved sign-up route, an unwired project-creation wizard) goes beyond the PRD's flat/pre-seeded MVP model, but is treated as sunk cost — no new Foundation work is sequenced here; this roadmap pivots fully onto the Logging gap below.
- **Deploy / infra:** partial / conflicting — only a dev-shaped `docker-compose.yml` exists (bind mounts, custom `networks:` block, no healthchecks, no log-rotation limits). No `messenger.yaml`, no `/health` endpoint, no prod compose file. Two foundation docs disagree on the target platform: `infrastructure.md` recommends Hetzner VPS; an in-flight `context/changes/deployment/deployment-plan.md` targets OVHcloud instead (see `## Open Roadmap Questions`).
- **Observability:** absent beyond default — only Symfony's default `monolog.yaml` rotating-file logging; no external structured logging or metrics.
- **Logging (the product's core domain):** absent — `src/Logging/{Ui,Application,Infrastructure,Domain}` contain only `.gitkeep` placeholders. Zero ingestion, storage, browsing, or filtering exists today.

## Foundations

### F-01: Async ingestion transport scaffold

- **Outcome:** (foundation) a Symfony Messenger `async` transport (Doctrine, reusing the existing MySQL connection) is configured and migrated, so log records can be dispatched without blocking the host Symfony app's request cycle.
- **Change ID:** async-ingestion-transport
- **PRD refs:** FR-006
- **Unlocks:** S-01 — without an async dispatch path, the ingestion endpoint S-01 builds cannot satisfy FR-006's fail-open / non-blocking requirement.
- **Prerequisites:** —
- **Parallel with:** F-02, S-04
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Scoped to the transport/queue plumbing only (no Redis, no new service — reuses MySQL per the existing tech-stack choice) so it doesn't grow into "build the whole Logging layer." The actual ingestion endpoint and log storage are S-01's job, not this foundation's.
- **Status:** ready

### F-02: Production deploy skeleton

- **Outcome:** (foundation) the VPS+Coolify platform choice is resolved, a minimal prod-safe Docker Compose (healthchecks, log-rotation limits, no bind mounts, no custom `networks:` block) exists, and a `/health` endpoint reports DB + queue health — enough to run this MVP in the persona's actual self-hosted environment. Operational hardening beyond this (auto-deploy CI, nightly backups, uptime monitoring) is intentionally Parked, not part of this foundation.
- **Change ID:** production-deploy-skeleton
- **PRD refs:** Success Criteria (Primary — "logs emitted by a Symfony app **in production** reach Monitoring Webowi"), NFR (Freshness at the boundary)
- **Unlocks:** the production verification path for S-01's acceptance criteria, and resolves the Hetzner-vs-OVHcloud Open Roadmap Question below.
- **Prerequisites:** —
- **Parallel with:** F-01, S-01, S-02, S-03, S-04
- **Blockers:** —
- **Unknowns:**
  - Which VPS provider — Hetzner (per `infrastructure.md`) or OVHcloud (per the in-flight `deployment-plan.md`)? — Owner: user. Block: yes.
- **Risk:** Sequenced parallel to, not before, S-01's coding — the platform decision and prod-compose work don't block writing the ingestion/listing logic, only its real-world verification. Deferring this fully would risk discovering deploy-time surprises (the documented Traefik/`networks:` and worker-restart-policy pitfalls) only after S-01 is "done" in dev.
- **Status:** blocked

## Slices

### S-01: A Symfony app's logs flow into a queryable list via the API

- **Outcome:** a Symfony app, configured with the Monolog HTTP-handler snippet and a valid per-project API key, has its log records accepted, persisted, and retrievable via the API in reverse-chronological order — without ever blocking, slowing, or crashing the host app.
- **Change ID:** ingested-logs-queryable-list
- **PRD refs:** US-01, FR-001, FR-002, FR-003, FR-005, FR-006, FR-007
- **Prerequisites:** F-01, a seeded user + project + API key (pre-seeded per MVP scope; doesn't exist in `fixtures/` yet)
- **Parallel with:** F-02, S-04
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Intentionally bundles ingest + store + list as one slice rather than splitting by technical layer: PRD's only user story (US-01) and its Primary Success Criterion describe this as one atomic pipe, and none of FR-002/005/006/007 has independent user-visible value until the others work too. FR-001 (sign-in) and FR-003 (ownership scoping) are listed here because this is the first slice that exercises them against real data — both are already implemented (see `## Baseline`), so no new auth work is implied.
- **Status:** proposed

### S-02: Filter the visible log list by severity and HTTP status code

- **Outcome:** the developer can narrow the API's log list by Monolog severity level (DEBUG…EMERGENCY) and, when present, by HTTP status code (e.g., only 500s).
- **Change ID:** filter-logs-by-severity-and-http-code
- **PRD refs:** US-01, FR-008
- **Prerequisites:** S-01
- **Parallel with:** S-03
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Mechanical on top of S-01's data shape (query parameters, not new domain concepts) — low risk, sequenced right after the north star because it completes the PRD's literal Primary Success Criterion wording.
- **Status:** proposed

### S-03: Project freshness indicator

- **Outcome:** the developer can see, per project, when the most recent log entry was received (or that none have arrived recently) — a passive signal that ingestion is alive, without active alerting.
- **Change ID:** log-freshness-indicator
- **PRD refs:** FR-009, NFR (Freshness at the boundary)
- **Prerequisites:** S-01
- **Parallel with:** S-02
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Low — derived entirely from S-01's stored data (a max-timestamp query); the only real constraint is hitting the NFR's p95 ≤ 5s / p99 ≤ 15s freshness window, which is primarily a property of F-01's async transport, not new logic here.
- **Status:** proposed

### S-04: View and copy the project's API key + install instructions

- **Outcome:** the developer can retrieve their project's API key (masked by default, revealed and copyable on explicit request) along with the Monolog-snippet install instructions.
- **Change ID:** view-and-copy-project-api-key
- **PRD refs:** FR-004
- **Prerequisites:** a seeded project + API key (same seed data S-01 needs)
- **Parallel with:** F-01, F-02, S-01
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Low and independent of the ingestion pipe — the `Project`/`IngestionKey` domain already exists in baseline (entities, repositories, hashed-key storage); this slice is mostly the missing API endpoint around data that's already modeled. Worth shipping early since a developer needs the key before they can test the install snippet for S-01.
- **Status:** ready

## Backlog Handoff

| Roadmap ID | Change ID                            | Suggested issue title                                               | Ready for `/10x-plan` | Notes |
| ---------- | -------------------------------------- | ---------------------------------------------------------------------- | ----------------------- | ----- |
| F-01       | async-ingestion-transport              | Configure Messenger async transport for fail-open log ingestion        | yes                      | Unlocks the north star S-01 |
| F-02       | production-deploy-skeleton             | Resolve VPS platform + ship prod-safe Compose & /health endpoint       | no                       | Blocked on Hetzner-vs-OVHcloud decision |
| S-01       | ingested-logs-queryable-list           | Symfony app logs flow into a queryable list via the API                | no                       | Run `/10x-plan` once F-01 lands |
| S-02       | filter-logs-by-severity-and-http-code  | Filter log list by severity and HTTP status code                      | no                       | Run `/10x-plan` once S-01 lands |
| S-03       | log-freshness-indicator                | Per-project freshness indicator                                       | no                       | Run `/10x-plan` once S-01 lands |
| S-04       | view-and-copy-project-api-key          | View/reveal/copy project API key + install instructions               | yes                      | Independent of the ingestion pipe |

This table is the clean handoff to Jira/Linear or any MCP-backed backlog. Include one row for every `F-NN` and `S-NN`. It should be compact enough to copy into issues, but it must not duplicate the detailed roadmap body.

## Open Roadmap Questions

1. **Grouping in v2.** Deferred from FR-007 Socratic round. Grouping the log list by error pattern (exception class, message template, URL pattern) is useful during a 500-spike but is not in MVP. The v1 data shape should be chosen so v2 grouping is mechanical rather than a rewrite. Owner: user. Block: none (informs S-01's data shape, doesn't block it).
2. **API-key leak mitigation in v2.** Deferred from FR-002 Socratic round. Once public sign-up exists and many projects ship keys to public-ish repos, the per-project key needs IP allowlist, short-lived tokens, or rotation. Out of MVP. Owner: user. Block: none.
3. **Bundle vs. snippet in v2.** MVP ships a paste-in Monolog snippet; v2 should re-evaluate whether to publish a real Symfony bundle. Owner: user. Block: none.
4. **Per-project event volume cap.** No upper bound is set on events/sec or events/day per project; behavior under a misconfigured app's runaway logging is undefined. Owner: user. Block: S-01 (advisory — S-01 can ship without a cap, but the cap should be decided before real-world exposure).
5. **Product type hybrid.** This product has both a human web UI and a machine-facing ingestion endpoint — already resolved at the repo-scope level (this repo is the API/ingestion surface only; the human UI is a separate SPA project). Owner: user. Block: none — resolved for this roadmap's purposes.
6. **Hetzner vs. OVHcloud.** `infrastructure.md` recommends Hetzner VPS + Coolify; an in-flight `context/changes/deployment/deployment-plan.md` targets OVHcloud + Coolify instead. These two foundation docs disagree on the deploy target. Owner: user. Block: F-02.
7. **UI-facing FR/NFR scoping now that the frontend is a separate project.** FR-001 ("sign in to the web UI"), FR-004/007/008/009 ("on a project page" / "the visible log list"), and the NFRs for UI responsiveness, browser support, and accessibility are all written assuming a UI living in this repo. This roadmap treats them as satisfied at the API-contract level here, with actual UI/NFR fulfillment happening in the separate SPA project. Owner: user. Block: none directly (advisory) — worth a follow-up PRD revision so this split is explicit rather than implicit.

## Parked

- **Active alerting (email/SMS).** Why parked: explicit PRD non-goal; MVP ships only the passive freshness indicator (S-03).
- **Charts, graphs, dashboards, or trend visualizations.** Why parked: explicit PRD non-goal; MVP ships a filterable list, not analytics.
- **Non-Symfony framework/language support.** Why parked: explicit PRD non-goal; the Symfony-bundle ergonomics insight is framework-specific by design.
- **Full-text search across log message bodies.** Why parked: explicit PRD non-goal; MVP filters by structured axes only (severity, HTTP code).
- **Log grouping/clustering by error pattern.** Why parked: explicit PRD non-goal; deferred to v2 (see Open Roadmap Question 1).
- **Project sharing, team workspaces, per-project roles.** Why parked: explicit PRD non-goal; flat single-owner-per-project model is binding for MVP.
- **Public sign-up flow / project CRUD UI.** Why parked: explicit PRD non-goal for MVP — and per the shaping decision, the already-built sign-up route and project-creation wizard are sunk cost, not something this roadmap sequences further work on.
- **Packaged Symfony bundle (`composer require`-able).** Why parked: explicit PRD non-goal; v1 ships a paste-in Monolog snippet (see Open Roadmap Question 3).
- **Log retention beyond 7 days / cold-storage archive.** Why parked: explicit PRD non-functional non-goal.
- **Multi-region SLA / formal compliance certification.** Why parked: explicit PRD non-functional non-goal.
- **Defined ingestion throughput SLA under stress/spiking load.** Why parked: explicit PRD non-functional non-goal (see Open Roadmap Question 4).
- **Production operational hardening (auto-deploy CI webhook, nightly MySQL backups, external uptime monitor).** Why parked: deferred from F-02's scope to keep that foundation minimal; valuable but not required to prove the core hypothesis under a speed-to-launch goal.
- **Remove legacy Twig/AssetMapper dashboard scaffolding** (`assets/dashboard-ui/`, `templates/dashboard/*`). Why parked: confirmed out of scope now that the frontend is a separate SPA project; cleanup, not user-facing, and doesn't block any slice above.

## Done

