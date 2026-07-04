# Test Plan

> Phased test rollout for this project. Strategy is frozen at the top
> (§1–§5); cookbook patterns at the bottom (§6) fill in as phases ship.
> Read before writing any new test.
>
> Refresh: re-run `/10x-test-plan --refresh` when stale (see §8).
>
> Last updated: 2026-07-04

## 1. Strategy

Tests follow three non-negotiable principles for this project:

1. **Cost × signal.** The cheapest test that gives a real signal for the
   risk wins. Do not promote to e2e because e2e "feels safer." Do not put a
   vision model on top of a deterministic visual diff that already catches
   the regression.
2. **User concerns are first-class evidence.** Risks anchored in "the team
   is worried about X, and the failure would surface somewhere in area Y"
   carry the same weight as PRD lines or hot-spot data.
3. **Risks are scenarios, not code locations.** This plan documents *what
   could fail* and *why we believe it's likely* — drawn from documents,
   interview, and codebase *signal* (churn, structure, test base). It does
   NOT claim to know which line owns the failure. That knowledge is
   produced by `/10x-research` during each rollout phase. If the plan and
   research disagree about where the failure lives, research is the
   ground truth.

Hot-spot scope used for likelihood weighting: `src/` (main hand-written
codebase; excludes `vendor/`, `var/`, `public/`, `tests/`), 36 commits in
the last 30 days — sufficient signal.

## 2. Risk Map

The top failure scenarios this project must protect against, ordered by
risk = impact × likelihood. Risks are failure scenarios in user / business
terms, not test names. The Source column cites the *evidence that surfaced
this risk* — never a specific file as "where the failure lives" (that is
research's job, see §1 principle #3).

| # | Risk (failure scenario) | Impact | Likelihood | Source (evidence — not anchor) |
|---|---|---|---|---|
| 1 | An attacker exploits the unpatched Symfony Monolog-bridge deserialization CVE on the log-ingestion boundary — the product's core purpose is accepting external log payloads, putting this squarely on the hot path | High | High | health-check.md (CVE-2026-45077, unauthenticated PHP object deserialization in the MonologBridge server handler); PRD "Ingestion (Symfony app → service)" section |
| 2 | A change to sign-in / 2FA / session logic silently breaks access — locks out valid users, or lets an expired/invalid session through | High | High | interview Q2 (past incident: an auth-middleware refactor silently broke session validity); hot-spot dir `src/Identity/Ui/Authentication` (24 commits/30d), `src/Identity/Domain/User` (13 commits/30d) |
| 3 | After ingestion-key rotation, a request presented with the pre-rotation key is still accepted at the ingestion boundary | High | High | interview Q3 (lowest-confidence area) + Q4 (named as under-tested); hot-spot dir `src/Projects/Infrastructure/Security` (10 commits/30d); PRD Access Control section ("compromise of one project's key affects only that project's data") |
| 4 | A HEAD request bypasses `#[IsGranted]` / method-restricted route authorization, granting access to project data without a valid ownership check | High | High | health-check.md (CVE-2026-45075, HEAD request bypasses `methods: ['GET']` filter on `#[IsGranted]`/`#[IsSignatureValid]`); PRD Access Control / project-isolation guardrail |
| 5 | A log event fails between ingestion-acceptance and persistence (async dispatch failure) but the freshness indicator's last-arrival timestamp gives no signal that a specific delivery was lost | High | Medium | interview Q1 (top worry: silent ingestion swallowing behind a healthy-looking freshness indicator); PRD FR-006, FR-009; NFR "Freshness at the boundary" |
| 6 | A change to auth or key-handling code regresses cross-project log/data isolation, even though an isolation scenario already exists in the suite | High | Medium | PRD guardrail ("cross-project log visibility is a fatal regression even if Primary holds"); hot-spot dirs `src/Identity/Ui/Authentication`, `src/Projects/Infrastructure/Security` (same churn backing Risks #2, #3) |

**Impact × Likelihood rubric.**

| Rating | Impact | Likelihood |
|--------|--------|------------|
| High   | user loses access, data, or money; failure is publicly visible | area changes weekly, or we have already been burned here |
| Medium | feature degrades, a workaround exists, only some users affected | touched occasionally, has been a source of bugs |
| Low    | cosmetic, easily reverted, no data effect | stable code, rarely touched |

**Abuse / security lens applied.** Risk #1 covers untrusted input /
injection (unauthenticated deserialization on the ingestion boundary).
Risk #4 covers authorization/access (method-based bypass of an ownership
check). A candidate resource-abuse row (per-project event-volume flood)
and a candidate secret/PII-leakage row were considered and dropped — see
Challenger Findings below.

### Risk Response Guidance

| Risk | What would prove protection | Must challenge | Context `/10x-research` must ground | Likely cheapest layer | Anti-pattern to avoid |
|------|-----------------------------|----------------|--------------------------------------|-----------------------|-----------------------|
| #1 | A payload with a malicious serialized-object shape (or type-confused body) is rejected before reaching any deserialization sink; the ingestion path never deserializes attacker-controlled bytes into arbitrary PHP objects | "We validate JSON shape, so we're safe" — schema validation does not prove no unsafe deserialization occurs downstream in bridge/handler code | Whether this app's ingestion controller actually invokes the vulnerable monolog-bridge server-handler code path, or builds its own endpoint that never touches it | dependency-version gate (composer audit) + a targeted unit/integration test on the ingestion path | Bumping the dependency version and calling it done without a regression test that would catch reintroduction |
| #2 | After any change to sign-in/2FA/session-refresh logic, a valid session still authenticates, an expired/revoked session is rejected, and no user is spuriously logged out | "The happy-path sign-in test still passes, so auth is fine" — must also assert the negative cases (expired token, revoked refresh token, mid-2FA state) | Current session/2FA state machine: token TTLs, refresh-rotation behavior, 2FA-pending intermediate state | unit tests on the auth handler for expiry/invalid-session paths; Behat for the full sign-in → 2FA → session lifecycle | An assertion whose expected value is copied from the current implementation's output rather than the documented session/token contract |
| #3 | Immediately after `RotateIngestionKeyHandler` runs, an ingestion request using the pre-rotation key is rejected, while the new key is accepted | "The rotation handler's unit test passing means rotation works" — a test that the new key was persisted says nothing about whether the old key is actually rejected at the boundary | How/where the ingestion endpoint validates keys; whether any caching could serve a stale "valid" answer post-rotation | integration test hitting the real ingestion endpoint before and after rotation, with both keys | Testing the rotation handler in isolation without an end-to-end check that the old key is actually rejected |
| #4 | A HEAD request to any `#[IsGranted]`/method-restricted route is rejected (or behaves identically to the GET-path authorization check) | "We use `#[IsGranted]` everywhere, so we're covered" — the CVE shows the attribute itself can be bypassed by method choice | Installed Symfony version vs. patched version (7.4.12); which routes use `#[IsGranted]`/method-restricted patterns | dependency-version gate first; a small integration/contract test sending HEAD to representative guarded routes as a regression backstop | Relying solely on "we'll upgrade eventually" with no regression test for a future re-introduction |
| #5 | When a log event fails to persist, that failure is observable in the Messenger retry/failure-transport mechanism — not silently vanished | "The freshness indicator's own tests passing proves silent-failure detection works" — freshness only proves *some* event's timestamp updated, not that a *specific* app's events landed | Messenger dispatch failure modes: retry policy, dead-letter/failure-transport behavior for a message that fails all retries | unit/integration test on the Messenger failure/retry path | A test that only re-asserts the already-covered happy-path freshness timestamp update |
| #6 | After changes to auth or key-handling code, a project-scoped request (session- or key-based) still returns/accepts only that project's data | "The `projectIsolation.feature` file already exists and passes" — that proves isolation held when written, not that a new auth/key change hasn't broken it | Which authorization checks the existing Behat isolation scenarios exercise (session ownership check vs. key-to-project binding) | extend existing Behat `projectIsolation.feature`, or a faster integration equivalent if Behat proves too slow to run per relevant change | Treating "isolation feature file exists" as permanent proof instead of a check triggered by touching Identity/Auth or Projects/Security code |

**Challenger findings:** dropped a candidate "resource-abuse / per-project
event-volume flood" risk — the PRD and roadmap explicitly mark rate
limiting as an open, deferred question (Open Roadmap Question 4), not a
regression against an existing guarantee; it belongs in negative space.
Dropped a candidate "secret/PII leakage across projects" risk as redundant
with Risk #6 — no evidence of a leak surface distinct from cross-project
exposure.

## 3. Phased Rollout

Each row is a discrete rollout phase that will open its own change folder
via `/10x-new`. Status moves left-to-right through the values below; the
orchestrator updates Status as artifacts appear on disk.

| # | Phase name | Goal (one line) | Risks covered | Test types | Status | Change folder |
|---|---|---|---|---|---|---|
| 1 | Security-critical regression backstop | Close the two unpatched, evidenced CVEs on the ingestion and access-control boundaries | #1, #4 | dependency-version gate + integration/contract | change opened | context/changes/testing-security-critical-regression-backstop/ |
| 2 | Auth & key-lifecycle regression coverage | Prove session logic and key rotation actually enforce what they claim, not just that their handlers run | #2, #3 | unit + integration | not started | — |
| 3 | Ingestion failure-mode & isolation regression net | Make silent delivery failure observable; turn isolation from a one-time file into a triggered regression check | #5, #6 | integration + Behat | not started | — |
| 4 | Quality-gates wiring | Enforce the already-configured mutation-test gate and add `composer audit` to CI so Phases 1–3 don't silently regress | cross-cutting | gates | not started | — |

**Status vocabulary** (fixed — parser literals): `not started` →
`change opened` → `researched` → `planned` → `implementing` → `complete`.

No AI-native phase is proposed. This repository is an API-only backend
with no UI in scope (confirmed in `roadmap.md` — the web UI is a separate
SPA project); a vision-review layer would add no signal here.

## 4. Stack

| Layer | Tool | Version | Notes |
|---|---|---|---|
| unit | PHPUnit | 10.x | `tests/Unit/`, run via `./vendor/bin/phpunit` inside the `monitoring-webowi-php` container |
| integration / BDD | Behat + FriendsOfBehat\SymfonyExtension | per `behat.yml.dist` | `tests/Behat/Features/`, fixtures via `hautelook/alice-bundle` |
| static analysis | PHPStan | level max (10) | enforced pre-commit via GrumPHP |
| code style | PHP CS Fixer | `@Symfony` + `@PSR12` + strict | enforced pre-commit via GrumPHP |
| mutation testing | Infection | minMsi 80 / minCoveredMsi 90 (`infection.json5`) | configured but commented out of `grumphp.yml` and absent from CI — none yet, see Phase 4 |
| dependency audit | `composer audit` | Composer 2.4+ native | not wired into CI — none yet, see Phase 1 (initial fix) and Phase 4 (ongoing gate) |
| (optional) AI-native | none | n/a | not applicable — API-only backend, no UI surface in this repo |

**Stack grounding tools (current session):**
- Docs: none available in current session (no Context7/framework-docs MCP exposed); checked: 2026-07-04.
- Search: generic WebSearch tool available, not used for this rollout (local manifest/config evidence was sufficient); checked: 2026-07-04.
- Runtime/browser: none available in current session; checked: 2026-07-04.
- Provider/platform: none available in current session (no GitHub/Cloudflare/database MCP); checked: 2026-07-04.

## 5. Quality Gates

| Gate | Where | Required? | Catches |
|---|---|---|---|
| phpcsfixer + phplint + phpstan (max) | local (GrumPHP) + CI | required | style drift, type errors |
| unit (PHPUnit) | local + CI | required | logic regressions |
| Behat (BDD/integration) | local; not yet in CI | required after §3 Phase 3 | broken critical flows, isolation regressions |
| dependency audit (`composer audit`) | not yet wired | required after §3 Phase 1 | known-CVE regressions (Risks #1, #4) |
| mutation testing (Infection, minMsi 80/90) | configured, not enforced | required after §3 Phase 4 | tests that pass but don't actually assert |
| post-edit hook | not configured | out of scope this lesson | — (Module 3 Lesson 3) |
| multimodal visual review | not applicable | not applicable | no UI surface in this repo |

## 6. Cookbook Patterns

How to add new tests in this project. Each sub-section is filled in once
the relevant rollout phase ships; before that, the sub-section reads
"TBD — see §3 Phase <N>."

### 6.1 Adding a unit test

- TBD — see §3 Phase 2 (auth/key-lifecycle unit-test patterns for
  `RotateIngestionKeyHandler`-style handlers).

### 6.2 Adding an integration test

- TBD — see §3 Phase 1 (ingestion-boundary integration test pattern
  against the deserialization/authorization CVEs).

### 6.3 Adding a Behat/BDD scenario

- TBD — see §3 Phase 3 (triggered isolation-regression pattern extending
  `tests/Behat/Features/Logging/projectIsolation.feature`).

### 6.4 Adding a test for a new API endpoint

- **Test type**: integration (preferred) via Behat's JSON contexts, or a
  PHPUnit integration test hitting the handler directly.
- **Reference test**: `tests/Behat/Features/Logging/ingestAndList.feature`.
- **When to add e2e instead**: not applicable — this repo has no UI; the
  API contract boundary is the outermost layer under test here.

### 6.5 Wiring a quality gate

- TBD — see §3 Phase 4 (enabling Infection in `grumphp.yml`/CI and adding
  `composer audit`).

### 6.6 Per-rollout-phase notes

(Filled in after each phase lands.)

## 7. What We Deliberately Don't Test

Exclusions agreed during the rollout (Phase 2 interview, Q5). Future
contributors should respect these unless the underlying assumption
changes.

- **Legacy Twig/AssetMapper dashboard scaffolding** (`assets/dashboard-ui/`,
  `templates/dashboard/*`) — dead code slated for removal; the web UI is a
  separate SPA project. Re-evaluate if this scaffolding is ever revived.
  (Source: Phase 2 interview Q5; roadmap.md `## Parked`.)
- **Per-project event-volume cap / rate limiting** — no upper bound exists
  yet; behavior under a runaway/misconfigured app is explicitly undefined
  per the PRD. Re-evaluate once a cap is designed. (Source: roadmap.md Open
  Roadmap Question 4.)
- **Non-Symfony framework/language integration paths, charts/dashboards,
  full-text search, active alerting** — explicit PRD non-goals for MVP.
  (Source: prd.md Non-Goals.)

## 8. Freshness Ledger

- Strategy (§1–§5) last reviewed: 2026-07-04
- Stack versions last verified: 2026-07-04
- AI-native tool references last verified: not applicable — no AI-native
  layer in this rollout

Refresh (`/10x-test-plan --refresh`) when:

- a new top-3 risk surfaces from the roadmap or archive,
- a recommended tool's `checked:` date is older than three months,
- the project's tech stack changes (new framework, new test runner),
- §7 negative-space no longer matches what the team believes.
