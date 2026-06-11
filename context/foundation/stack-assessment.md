---
project: monitoring-webowi
assessed_at: 2026-05-31T00:00:00Z
agent_readiness: ready-with-compensation
context_type: brownfield
stack_components:
  language: PHP 8.4
  framework: Symfony 7.4 (API mode)
  build_tool: Composer
  test_runner: PHPUnit 10 + Behat
  package_manager: Composer
  ci_provider: GitHub Actions
  deployment_target: Docker Compose (nginx + PHP-FPM + MySQL)
gates_passed: 11
gates_failed: 2
---

## Stack Components

**Language — PHP 8.4.** The project targets PHP 8.4, the current PHP release, using all modern type-system features: constructor property promotion, union types, intersection types, readonly properties, first-class callables, and named arguments. `declare(strict_types=1)` is enforced globally by the PHP CS Fixer rule `'declare_strict_types' => true` — no file can enter the codebase without it, because GrumPHP runs CS Fixer before every commit.

**Framework — Symfony 7.4 (LTS).** The project runs Symfony 7.4, the current long-term support release. It operates in API mode: all controller routes are prefixed `/api/v1` via `config/routes.yaml`, CORS is handled by `nelmio/cors-bundle`, and authentication uses JWT (`lexik/jwt-authentication-bundle`) with refresh tokens (`gesdinet/jwt-refresh-token-bundle`) and optional 2FA (`scheb/2fa-bundle`). There is no server-rendered HTML frontend in scope.

**Architecture — DDD/Hexagonal with bounded contexts.** Rather than Symfony's default flat `src/` layout, the project uses a consistent DDD structure. Each bounded context lives under `src/<Context>/` and contains four sub-layers: `Application/` (use-case handlers, commands, services), `Domain/` (entities, value objects, repository interfaces), `Infrastructure/` (Doctrine repositories, external adapters), and `Ui/` (Symfony controllers — the HTTP boundary). Current contexts: `Identity`, `Logging`, `Projects`, `Kernel`. This structure is the most important architectural fact for agents to internalize.

**Static analysis — PHPStan level 10 (max).** PHPStan is configured at level 10 in `phpstan.neon`, scanning `src/` and `tests/`. This is the strictest available level: all type errors, nullability issues, dead code, and unreachable branches are errors. GrumPHP enforces this on every commit.

**Code style — PHP CS Fixer with @Symfony + @PSR12 + strict rules.** The `.php-cs-fixer.php` config applies `@Symfony`, `@PSR12`, `@PSR12:risky`, enforces `declare_strict_types`, `fully_qualified_strict_types`, `use_arrow_functions`, `no_unused_imports`, and more. GrumPHP runs this before every commit.

**Quality gate — GrumPHP.** Every commit must pass: `composer` validation, `phplint`, `phpcsfixer`, `phpstan` (level max), and `phpunit`. Infection (mutation testing) is configured in `infection.json5` but currently disabled in `grumphp.yml`.

**Test runners — PHPUnit 10 + Behat.** Unit and integration tests live in `tests/` (PHPUnit 10, `phpunit.xml.dist`). BDD/E2E tests are handled by Behat (`behat.yml.dist`, `friends-of-behat/symfony-extension`). Fixtures use `hautelook/alice-bundle`. **All tests run inside Docker** — the CI pipeline (`ci.yml`) runs `docker compose exec -T monitoring-webowi-php ./vendor/bin/phpunit`.

**CI/CD — GitHub Actions.** `.github/workflows/ci.yml` runs on push/PR to `main`. Steps: build Docker image → `docker compose up -d` → `composer install` → PHPUnit → GrumPHP. No separate deployment step in the current workflow.

**Deployment — Docker Compose.** Local and CI environments use Docker Compose: `monitoring-webowi-nginx` (nginx:stable-alpine), `monitoring-webowi-php` (custom PHP-FPM Dockerfile), `monitoring-webowi-mysql` (MySQL 8.0). The PHP container is the execution context for all PHP tooling.

---

## Quality Gate Assessment

| Component          | Typed | Convention-based | Training data | Documented | Verdict          |
|--------------------|-------|-----------------|---------------|------------|------------------|
| Language (PHP 8.4) | ✓     | —               | —             | —          | pass             |
| Framework (Symfony 7.4) | — | ✓ / ~¹        | ✓             | ✓          | pass-with-note   |
| Build tool (Composer) | — | ✓             | ✓             | ✓          | pass             |
| Test runner (PHPUnit 10) | — | —            | ✓             | ✓          | pass             |
| Test runner (Behat) | —    | —               | ✓             | ✓          | pass             |

¹ Symfony itself is fully convention-based, but this project's DDD/Hexagonal layout is a project-specific convention that departs from Symfony's default flat structure. An agent unfamiliar with this project will default to the wrong layout.

**Legend:** ✓ = pass, ~ = partial, — = not applicable

### Gate Details

**Type safety (language)**
Evidence: `'declare_strict_types' => true` in `.php-cs-fixer.php` (enforced by GrumPHP on every commit); `level: 10` in `phpstan.neon` (maximum PHPStan strictness). No PHP file can be committed without explicit strict types. **Pass.**

**Convention-based (framework — Symfony conventions)**
Evidence: `config/routes.yaml` uses Symfony's attribute routing resource loader; `config/packages/doctrine.yaml` uses Doctrine ORM conventions; `config/services.yaml` uses autowiring and autoconfigure. Symfony's own conventions are fully followed. **Pass.**

**Convention-based (framework — project DDD layout)**
Evidence: `src/` contains `Identity/`, `Logging/`, `Projects/`, `Kernel/` — each with `Application/`, `Domain/`, `Infrastructure/`, `Ui/` sub-layers. This is consistent and well-structured but is **not** Symfony's default layout (`src/Controller/`, `src/Entity/`, `src/Repository/`). An agent trained on Symfony examples will default to the flat layout. **Partial — compensation required.**

**Popular in training data (Symfony)**
Evidence: Symfony is one of the two dominant PHP frameworks (alongside Laravel), with extensive representation in open-source codebases and PHP training data. Symfony 7.x docs and examples are current. **Pass.**

**Well-documented (Symfony 7.4)**
Evidence: symfony.com maintains versioned docs for 7.4, covering every bundle in use (Security, Serializer, Mailer, HttpClient, Messenger, Doctrine bridge). All third-party bundles (`lexik/jwt`, `scheb/2fa`, `nelmio/cors`, `vich/uploader`) have current official docs. **Pass.**

**Test runner (PHPUnit 10)**
Evidence: `phpunit.xml.dist` targets PHPUnit 10 schema. PHPUnit is the de-facto PHP test framework with complete official docs. **Pass.**

**Test execution context (Docker)**
Evidence: `ci.yml` runs all PHP tooling via `docker compose exec -T monitoring-webowi-php`. This is not a gate-scored criterion but is a critical operational fact: native `./vendor/bin/phpunit` will fail without the Docker MySQL container. **Compensation required.**

---

## Gaps & Compensation

### Gap 1 — DDD/Hexagonal layout not documented for agents

**What failed:** Symfony's training data teaches the flat layout (`src/Controller/`, `src/Entity/`, `src/Repository/`). This project uses a bounded-context DDD structure that an agent will not discover from Symfony conventions alone. Without explicit documentation, agents will create files in the wrong location or introduce architecture drift (e.g., `src/Controller/LogController.php` instead of `src/Logging/Ui/LogController.php`).

**Why it matters for agent workflows:** Every new feature — a new API endpoint, a new domain entity, a new repository — requires the agent to know which bounded context it belongs to and where within the four-layer structure to place it. Getting this wrong means the file lands in a flat location, silently bypassing the architecture.

**Compensation:** Add explicit folder-structure rules and bounded-context definitions to `CLAUDE.md`/`AGENTS.md`.

### Gap 2 — Test and tooling commands require Docker

**What failed:** All PHP tooling (PHPUnit, PHPStan, GrumPHP, PHP CS Fixer) runs inside the `monitoring-webowi-php` Docker container. Agents attempting to run tests or static analysis natively (e.g., `./vendor/bin/phpunit`) will either fail with a MySQL connection error or produce incorrect results if a local PHP installation is present.

**Why it matters for agent workflows:** Agents routinely run tests to verify changes. If they use the wrong command, they receive misleading feedback (test failures due to missing DB, not code errors) and may make spurious additional changes.

**Compensation:** Document the canonical Docker-based commands as the single source of truth.

---

### Recommended Instruction File Additions

Paste these blocks into your `CLAUDE.md` or `AGENTS.md`:

---

```markdown
## Architecture — Bounded-Context DDD Layout

This project uses a DDD/Hexagonal structure. Do NOT use Symfony's default flat `src/` layout.

Every feature belongs to a bounded context. Current contexts:
- `src/Identity/` — authentication, users, organizations, JWT, 2FA
- `src/Logging/` — log ingestion endpoint, log browsing, retention
- `src/Projects/` — project management, API key lifecycle
- `src/Kernel/` — cross-cutting infrastructure (translation, shared utilities)

Within each context, files go in one of four layers:
- `Application/` — use-case handlers, commands, services (no HTTP, no Doctrine)
- `Domain/` — entities, value objects, repository interfaces, domain exceptions (no framework deps)
- `Infrastructure/` — Doctrine repositories, external HTTP adapters, persistence (implements Domain interfaces)
- `Ui/` — Symfony controllers only (the HTTP boundary; delegates to Application layer)

**Examples:**
- New log ingestion endpoint → `src/Logging/Ui/IngestController.php`
- Log domain entity → `src/Logging/Domain/LogEntry.php`
- Doctrine repository → `src/Logging/Infrastructure/Db/LogEntryRepository.php`
- Use-case handler → `src/Logging/Application/IngestLogHandler.php`

Never create files directly under `src/` root or in `src/<Context>/` root.
Never create `src/Controller/`, `src/Entity/`, `src/Repository/` — these are the wrong layout.
```

---

```markdown
## Running Tests and Tooling

All PHP commands run inside Docker. The containers must be running first.

**Start containers:**
```bash
docker compose up -d
```

**Run PHPUnit:**
```bash
docker compose exec -T monitoring-webowi-php ./vendor/bin/phpunit --configuration phpunit.xml.dist
```

**Run PHPStan (static analysis):**
```bash
docker compose exec -T monitoring-webowi-php ./vendor/bin/phpstan analyse
```

**Run PHP CS Fixer (check only):**
```bash
docker compose exec -T monitoring-webowi-php ./vendor/bin/php-cs-fixer fix --dry-run --diff
```

**Run PHP CS Fixer (apply fixes):**
```bash
docker compose exec -T monitoring-webowi-php ./vendor/bin/php-cs-fixer fix
```

**Run all GrumPHP gates (what CI runs):**
```bash
docker compose exec -T monitoring-webowi-php ./vendor/bin/grumphp run
```

**Run Behat (E2E / API scenarios):**
```bash
docker compose exec -T monitoring-webowi-php ./vendor/bin/behat
```

Do not run any of the above without the `docker compose exec` prefix — there is no guaranteed local PHP/MySQL environment.
```

---

```markdown
## Code Quality Gates (must pass before every commit)

GrumPHP enforces these gates automatically on commit. When making changes, verify in order:

1. **PHPStan level 10 (max)** — zero type errors, no dead code, no nullability issues.
2. **PHP CS Fixer** — `declare(strict_types=1)` on every file, `@Symfony` + `@PSR12` rules.
3. **PHPUnit** — all unit and integration tests green.
4. **phplint** — no PHP syntax errors.

If any gate fails, fix the violation before treating the task as complete.
```

---

```markdown
## API Conventions

- All routes are prefixed `/api/v1` (defined in `config/routes.yaml`).
- Authentication: JWT Bearer token (`lexik/jwt-authentication-bundle`). Protected routes require `Authorization: Bearer <token>`.
- CORS: managed by `nelmio/cors-bundle` (`config/packages/nelmio_cors.yaml`).
- Serialization: use Symfony's Serializer component (`symfony/serializer`) — not `json_encode`, not API Platform.
- HTTP responses: return `JsonResponse` from controllers; delegate all business logic to the `Application/` layer.
```

---

## Summary

**Overall agent-readiness: ready-with-compensation.**

This is an exceptionally well-configured PHP stack. The type-safety story is as strong as PHP allows: PHPStan at maximum strictness enforced by a pre-commit gate, `declare_strict_types` globally enforced by the formatter, PHP 8.4. The framework (Symfony 7.4 LTS) is mainstream, well-documented, and highly convention-based. The test tooling (PHPUnit 10 + Behat + GrumPHP) is complete.

**Key strengths:**
- Maximum static analysis coverage means agents receive immediate, reliable feedback on type errors.
- GrumPHP pre-commit gate means the codebase cannot regress on formatting, linting, or analysis.
- Symfony 7.4 is among the best-represented PHP frameworks in AI training data.

**Key gaps requiring compensation (both addressed above):**
1. **DDD layout** — The project's bounded-context folder structure must be explicitly documented; agents will otherwise default to Symfony's flat layout and introduce architecture drift.
2. **Docker test commands** — All tooling runs in Docker; agents must use `docker compose exec` rather than native binaries.

**Recommended next step:** `/10x-health-check` — run a dependency audit, security scan, and CI/CD evaluation against the gaps identified here.
