---
project: monitoring-webowi
checked_at: 2026-05-31T00:00:00Z
health_status: needs-attention
audit_critical: 1
audit_high: 5
audit_medium: 10
audit_low: 7
test_runner: PHPUnit 10 + Behat (detected)
ci_provider: GitHub Actions
category_a_fixes: 3
category_b_items: 2
---

## Dependency Audit

**Lockfile:** `composer.lock` present. Builds are reproducible. ✓

**Audit command:** `composer audit` (Composer 2.4+ native)

**Result: 1 CRITICAL, 5 HIGH, 10 MEDIUM, 7 LOW, 13 advisories with unclassified severity.**

Current installed versions of affected packages:
- `symfony/*` (most packages): **7.4.6 / 7.4.7** → patches available at 7.4.12+
- `twig/twig`: **3.24.0** → patches available at 3.26+

### CRITICAL

| Package | CVE | Title | Fixed in |
|---------|-----|-------|----------|
| `twig/twig` | CVE-2026-46633 | PHP code injection via `{% use %}` template name | 3.26.0 |

**API relevance:** Twig is loaded even in API mode (error pages, dev profiler, any template referenced by bundles). If any code path passes user-controlled input to a template name, this is exploitable. Patch immediately.

### HIGH

| Package | CVE | Title | Fixed in |
|---------|-----|-------|----------|
| `symfony/monolog-bridge` | CVE-2026-45077 | Unauthenticated PHP Object Deserialization in MonologBridge server handler | 7.4.12 |
| `symfony/mime` | CVE-2026-45067 | Email Header / SMTP Command Injection via CRLF in `Header\ParameterizedHeader` | 7.4.12 |
| `symfony/security-http` | CVE-2026-45063 | Identity Spoofing via Unanchored DN Regex in X509Authenticator | 7.4.12 |
| `twig/twig` | CVE-2026-46640 | Arbitrary PHP code execution via `_self.(<string>)` macro-reference compilation | 3.26.0 |
| `twig/twig` | CVE-2026-46639 | Sandbox property and method bypass via object-destructuring assignment | 3.26.0 |

**API relevance, ranked by impact for this project:**

1. **`symfony/monolog-bridge` CVE-2026-45077** — this project uses `symfony/monolog-bundle` as its core logging infrastructure (the product *is* a log ingestion service). PHP object deserialization in the MonologBridge server handler is directly on the hot path.
2. **`symfony/mime` CVE-2026-45067** — the project depends on `symfony/mailer`; SMTP command injection via CRLF is exploitable on any outbound mail operation.
3. **`twig/twig` CVE-2026-46640** — arbitrary PHP code execution; patch even if templates are not user-facing.

### Notable MEDIUM advisories (API-relevant only)

| Package | CVE | Title |
|---------|-----|-------|
| `symfony/http-kernel` | CVE-2026-45075 | HEAD request bypasses `methods: ['GET']` filter in `#[IsGranted]` / `#[IsSignatureValid]` / `#[IsCsrfTokenValid]` |
| `symfony/security-http` | CVE-2026-45074 | `Cas2Handler` derives CAS service URL from client Host header → CSRF/open-redirect |
| `symfony/routing` | CVE-2026-45065 | `UrlGenerator` route-requirement bypass via unanchored regex alternation |

The `#[IsGranted]` HEAD-method bypass (`symfony/http-kernel`) is directly relevant to this API's access control layer — any `#[IsGranted]` or `#[Route(methods: ['GET'])]` annotation may be bypassable via HEAD requests.

### Unclassified severity (notable)

| Package | CVE | Title |
|---------|-----|-------|
| `symfony/security-http` | CVE-2026-48489 | Security Firewall Bypass via `failure_forward` subrequest: unauthenticated access |
| `symfony/runtime` | CVE-2026-46626 | SymfonyRuntime CVE-2024-50340 patch bypass: web requests can still set env vars |

CVE-2026-48489 (firewall bypass) is high-impact for an authentication-gated API. Treat as HIGH until Symfony publishes a CVSS score.

---

## Test Infrastructure

**PHPUnit 10** — configured in `phpunit.xml.dist`. Test directory structure:

```
tests/
  Unit/
    Kernel/      # framework-layer unit tests
    Stub/        # test stubs
  Behat/
    Features/
      Api/       # API endpoint scenarios
      Dashboard/ # (legacy — predates API-only pivot)
    Context/
      AuthenticationContext
      JSON/JSONMainContext, JSONRequestContext, JSONResponseContext
      FixturesContext, DbContext, CommandContext
  Mock/
  bootstrap.php
```

**PHPUnit status:** Configured and registered in GrumPHP — runs on every commit. ✓

**Behat status:** Configured with full API context suite (`behat.yml.dist`, Friends of Behat Symfony extension). Fixtures use `hautelook/alice-bundle`. **NOT wired into CI** — the `.github/workflows/ci.yml` runs PHPUnit and GrumPHP but does not execute `./vendor/bin/behat`. The API E2E test suite is invisible to continuous integration.

**Test execution context:** All test commands require Docker. Containers must be running first (`docker compose up -d`). See `context/foundation/stack-assessment.md` → "Running Tests and Tooling" for the canonical commands.

---

## CI/CD Evaluation

**Provider:** GitHub Actions (`.github/workflows/ci.yml`), runs on push/PR to `main`.

| Stage | Status | Notes |
|-------|--------|-------|
| Build | ✓ | Docker image built before test run |
| Lint | ✓ | PHP CS Fixer via GrumPHP |
| Type check | ✓ | PHPStan level 10 via GrumPHP |
| Test (unit) | ✓ | PHPUnit via dedicated step + GrumPHP |
| Test (E2E) | ✗ | Behat not invoked in CI |
| Security audit | ✗ | `composer audit` not run in CI |
| Dependency lock check | — | Not run, but lockfile is committed |

**Two gaps in CI coverage:**

1. **No `composer audit` step** — security advisories accumulate silently between developer-run audits. The current batch (1 CRITICAL, 5 HIGH) would not have been caught automatically.
2. **Behat not in CI** — the API E2E scenarios (`tests/Behat/Features/Api/`) do not run on push. A breaking API change can merge without triggering the behavioral test suite.

---

## Configuration Files

| File | Status | Notes |
|------|--------|-------|
| `composer.lock` | ✓ present | Reproducible builds |
| `.gitignore` | ✓ present | — |
| `.env.dist` | ✓ present | Environment variable documentation |
| `phpstan.neon` | ✓ present | Level 10 (max) |
| `.php-cs-fixer.php` | ✓ present | @Symfony + @PSR12 + strict rules |
| `grumphp.yml` | ✓ present | Pre-commit quality gate |
| `phpunit.xml.dist` | ✓ present | — |
| `behat.yml.dist` | ✓ present | — |
| `.editorconfig` | ✗ absent | Low priority — PHP CS Fixer covers formatting |
| `CLAUDE.md` (project rules) | ✗ absent | Has scaffold/lesson content only; no project-specific agent rules |
| `AGENTS.md` | ✗ absent | No agent instruction file |

The two missing instruction files (`CLAUDE.md` project rules, `AGENTS.md`) are **Category B** — see below.

---

## Cross-Reference: Stack Assessment Gaps

From `context/foundation/stack-assessment.md` (assessed 2026-05-31):

**Gap 1 — DDD/Hexagonal layout not documented for agents**
Health check confirms: `CLAUDE.md` contains scaffold/lesson boilerplate only — the four ready-to-paste instruction blocks from the stack assessment (architecture layout, Docker commands, quality gates, API conventions) have not been added yet. Agent onboarding will resolve this.

**Gap 2 — Test and tooling commands require Docker**
Health check confirms: no native test command documentation exists. The canonical `docker compose exec` commands from the stack assessment are not yet in any instruction file. Agent onboarding will resolve this.

---

## Prioritized Fixes

### Category A — Fix before agent work

---

**A-1: Update Symfony and Twig to patch CRITICAL + HIGH vulnerabilities**

- **Finding:** `symfony/monolog-bridge` 7.4.6 (deserialization), `symfony/security-http` 7.4.6 (identity spoofing + firewall bypass), `symfony/http-kernel` 7.4.6 (HEAD method bypass on `#[IsGranted]`), `twig/twig` 3.24.0 (PHP code injection).
- **Why it matters for agent work:** An agent touching authentication, route access control, or log ingestion may inadvertently interact with these vulnerable code paths. Running with known HIGH vulnerabilities in a security-focused (JWT auth, API key ingestion) project is a risk that agents cannot mitigate themselves.
- **Fix:**
  ```bash
  docker compose exec -T monitoring-webowi-php composer update
  ```
  This updates all packages to their latest allowed versions within `composer.json` constraints (all Symfony packages are pinned to `7.4.*`, which will get 7.4.12+; Twig is `^3.23.0` which will get 3.27+).
  After update, re-run the audit to confirm all CRITICAL/HIGH findings are resolved:
  ```bash
  composer audit
  ```
- **Effort:** Quick (< 5 min). The update is within pinned minor ranges — no breaking changes expected.

---

**A-2: Add `composer audit` to the CI pipeline**

- **Finding:** `.github/workflows/ci.yml` runs GrumPHP and PHPUnit but never runs `composer audit`. The current CRITICAL/HIGH advisory batch went undetected by CI.
- **Why it matters for agent work:** When an agent updates or adds a dependency, there is no automated check that the new dependency is clean. Security regressions can merge silently.
- **Fix:** Add this step to `.github/workflows/ci.yml` after the `composer install` step:
  ```yaml
  - name: Security audit
    run: docker compose exec -T monitoring-webowi-php composer audit
  ```
- **Effort:** Quick (< 5 min).

---

**A-3: Wire Behat into the CI pipeline**

- **Finding:** `tests/Behat/Features/Api/` contains API E2E scenarios but CI never runs Behat. A breaking change to an API endpoint can merge without triggering the behavioral suite.
- **Why it matters for agent work:** Agents implementing or modifying API endpoints need CI to run the full test suite — including the API scenarios — to catch regressions. Without Behat in CI, only unit tests are the safety net.
- **Fix:** Add this step to `.github/workflows/ci.yml` after the PHPUnit step:
  ```yaml
  - name: Run Behat (API scenarios)
    run: docker compose exec -T monitoring-webowi-php ./vendor/bin/behat --no-interaction
  ```
  Note: ensure the test database is migrated before Behat runs. If a migrations step is missing from CI, add it first:
  ```yaml
  - name: Run database migrations (test env)
    run: docker compose exec -T monitoring-webowi-php php bin/console doctrine:migrations:migrate --no-interaction --env=test
  ```
- **Effort:** Moderate (15–30 min — depends on whether the test DB migration setup in CI needs adjustment).

---

### Category B — Upcoming lessons

---

**B-1: Add project-specific agent instruction rules to CLAUDE.md**

The stack assessment produced four ready-to-paste blocks (DDD architecture layout, Docker test commands, quality gate rules, API conventions). These are not yet in any instruction file.

This is covered in **Agent Onboarding: Agents.md, AI Rules i feedback loops (M1L4)** — that lesson walks you through building `CLAUDE.md` and `AGENTS.md` with the right content for your project. Generating stubs now would be premature. The stack assessment at `context/foundation/stack-assessment.md` → "Recommended Instruction File Additions" has the exact blocks ready to paste when you get there.

**B-2: Add `AGENTS.md`**

No agent instruction file exists yet (`AGENTS.md` is absent, `CLAUDE.md` has scaffold boilerplate only). Same lesson as B-1: **Agent Onboarding (M1L4)** covers this.

---

## Summary

**Overall health: needs-attention.**

The project's local development configuration is excellent: maximum static analysis (PHPStan level 10), strict types globally enforced, PHPUnit and Behat both configured, GrumPHP pre-commit gate. The test infrastructure is mature and the architecture is clean.

The needs-attention verdict is driven entirely by one issue: **the dependency tree is 5–6 patch versions behind and carries 1 CRITICAL + 5 HIGH security advisories**, all patched in `7.4.12` / `twig 3.26+`. A single `composer update` inside Docker resolves the CRITICAL and all 5 HIGH findings. That, plus two CI additions (audit step + Behat step), completes all Category A work.

**Fixes by effort:**

| Fix | Category | Effort |
|-----|----------|--------|
| A-1: `composer update` to patch CRITICAL/HIGH CVEs | A | Quick |
| A-2: Add `composer audit` step to CI | A | Quick |
| A-3: Wire Behat into CI pipeline | A | Moderate |
| B-1: Add project agent rules to CLAUDE.md | B | Upcoming lesson |
| B-2: Create AGENTS.md | B | Upcoming lesson |
