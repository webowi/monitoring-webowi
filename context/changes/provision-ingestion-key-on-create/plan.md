# Generate First Ingestion Key Implementation Plan

## Overview

Add `POST /api/v1/projects/{uuid}/ingestion-key` — a dedicated endpoint that generates a project's *first* ingestion key (409 if an active key already exists). This gives the SPA's project wizard a semantically correct action for its step 2 ("generate key + install snippet"), distinct from `POST .../ingestion-key/rotate`, which is reserved for replacing a key that already exists.

## Current State Analysis

`CreateProjectHandler` (`src/Projects/Application/CreateProject/CreateProjectHandler.php`) only calls `Project::register()` + `projectRepository->save()` — no `IngestionKey` is created. `GET /api/v1/projects/{uuid}/ingestion-key` (`GetIngestionKeyHandler`) correctly reports `status: "none"`, `value: null` for such a project — this is truthful, not a bug.

`POST .../ingestion-key/rotate` (`RotateIngestionKeyHandler`) already tolerates a null existing key (`findActiveByProjectId` returning `null` just skips the revoke step), so it technically *could* double as "generate first key" — but its name and intent are about rotation, not first-time creation. No endpoint exists today whose semantics match "create the first key for this project."

## Desired End State

- A project with no active key returns `201 Created` from `POST /api/v1/projects/{uuid}/ingestion-key` with `{keyUuid, value, snippet}` — `value` is the new plaintext key, `snippet` is the Monolog install snippet
- Calling the same endpoint again on a project that now has an active key returns `409 Conflict` with a translated error message, and does not touch the existing key
- `GET /api/v1/projects/{uuid}/ingestion-key` reflects the newly generated key immediately after
- Unauthenticated calls return 401; wrong-organisation calls return 404 (consistent with `GET`/`rotate`)
- Unit and Behat coverage proves the full wizard flow: create project → `GET ingestion-key` (none) → `POST ingestion-key` (generated) → `GET ingestion-key` (populated) → `POST ingestion-key` again (409)

### Key Discoveries:

- `RotateIngestionKeyHandler` (`src/Projects/Application/RotateIngestionKey/RotateIngestionKeyHandler.php:28-61`) is the exact template to copy for the generate path — same generator/hasher/snippet-builder wiring, minus the revoke step, plus a conflict check
- `IngestionKey::new(Uuid $projectId, ?string $name, string $keyHash, ?string $keyValue)` (`src/Projects/Domain/IngestionKey.php:70-83`) defaults `name` to `'Default'` when `null` is passed — matches the pattern rotate uses when there's no prior key to inherit a name from
- Existing 409 exception pattern: `ProjectNameAlreadyExistsException` (`src/Projects/Application/Exception/ProjectNameAlreadyExistsException.php`) — extends `\Exception`, implements `TranslatableExceptionInterface`, constructs with `(message, Response::HTTP_CONFLICT)`
- Route auto-discovery: `config/routes.yaml` wires `src/Projects/Ui/` as an attribute-routed controller directory with prefix `/api/v1` — no manual route registration needed, just add the `#[Route]` attribute
- Fixture `tests/Behat/Fixtures/logMonitoring.yml` already has a project with **no** ingestion key (`project2`, name `'Another Owner Project'`) — but it has no fixed `uuid`, so it can't be addressed directly from a Behat scenario URL today; needs a fixed `uuid` added to be usable as the "keyless project" test fixture

## What We're NOT Doing

- Not changing `CreateProjectHandler` or `CreateProjectResult` — project creation stays exactly as-is; no key is created as a side effect
- Not changing `RotateIngestionKeyHandler`, `RotateIngestionKeyController`, or their tests — rotation semantics are untouched
- Not changing `GetIngestionKeyHandler`/`GetIngestionKeyController` — reading a not-yet-generated key still correctly returns `status: "none"`
- No frontend/SPA work — this repo ends at the API contract per the roadmap's repo-scope note
- No key expiry, IP allowlist, or encryption-at-rest — out of MVP scope per existing S-04 plan notes

## Implementation Approach

Two phases: (1) application-layer handler + exception + unit tests, mirroring `RotateIngestionKeyHandler` almost exactly but with a conflict guard instead of a revoke step; (2) the controller wiring plus Behat acceptance coverage for the full generate flow, including the 409 double-call case.

## Phase 1: Application Layer — Generate Handler and Conflict Exception

### Overview

Add the exception for "key already exists," the result value object, and the handler that generates the first key for a project.

### Changes Required:

#### 1. IngestionKeyAlreadyExistsException

**File**: `src/Projects/Application/Exception/IngestionKeyAlreadyExistsException.php`

**Intent**: Signal that a project already has an active ingestion key, so the generate endpoint should not silently create a second one.

**Contract**: `final class IngestionKeyAlreadyExistsException extends \Exception implements TranslatableExceptionInterface`, constructed with message `'Project already has an active ingestion key.'` and `Response::HTTP_CONFLICT` — identical shape to `ProjectNameAlreadyExistsException`.

#### 2. GenerateIngestionKeyResult

**File**: `src/Projects/Application/GenerateIngestionKey/GenerateIngestionKeyResult.php`

**Intent**: Value object carrying the newly generated key's UUID, plaintext value, and install snippet.

**Contract**: `final readonly class GenerateIngestionKeyResult { public function __construct(public Uuid $keyUuid, public string $value, public string $snippet) {} }` — identical shape to `RotateIngestionKeyResult`.

#### 3. GenerateIngestionKeyHandler

**File**: `src/Projects/Application/GenerateIngestionKey/GenerateIngestionKeyHandler.php`

**Intent**: Verify project ownership, reject if an active key already exists, otherwise generate and persist the first key.

**Contract**: `handle(Uuid $projectUuid): GenerateIngestionKeyResult`. Injects `ProjectRepositoryInterface`, `IngestionKeyRepositoryInterface`, `CurrentUserFetcher`, `IngestionKeyGenerator`, `IngestionKeyHasher`, `InstallSnippetBuilder` (same five collaborators as `RotateIngestionKeyHandler`, swapping the revoke branch for a conflict check). Steps: load project + ownership check (throws `ProjectNotFoundOrAccessDeniedException`); `findActiveByProjectId` — if non-null, throw `IngestionKeyAlreadyExistsException`; else generate plaintext via `IngestionKeyGenerator::generate()`, hash via `IngestionKeyHasher::hash()`, construct `IngestionKey::new($projectUuid, null, $hash, $plaintext)`, `save()`, return `GenerateIngestionKeyResult` with the new key's UUID, plaintext, and `InstallSnippetBuilder::build($plaintext)`.

#### 4. Unit tests

**File**: `tests/Unit/Projects/Application/GenerateIngestionKey/GenerateIngestionKeyHandlerTest.php`

**Intent**: Cover the handler's four paths, following the structure of `RotateIngestionKeyHandlerTest.php` and `GetIngestionKeyHandlerTest.php`.

**Contract**: Test cases — success (no existing key: generates, hashes, saves, returns result); conflict (existing active key found: throws `IngestionKeyAlreadyExistsException`, `save()` never called); project-not-found (throws `ProjectNotFoundOrAccessDeniedException`); wrong-org (throws `ProjectNotFoundOrAccessDeniedException`).

### Success Criteria:

#### Automated Verification:

- Static analysis passes: `./vendor/bin/phpstan analyse`
- Unit tests pass: `./vendor/bin/phpunit`

#### Manual Verification:

- None for this phase — no HTTP surface yet; verified in Phase 2

**Implementation Note**: After completing this phase and all automated verification passes, pause for manual confirmation before proceeding to Phase 2.

---

## Phase 2: Controller, Route, and Acceptance Tests

### Overview

Wire the HTTP endpoint and add the fixture + Behat coverage proving the full wizard-step-2 flow, including the conflict case.

### Changes Required:

#### 1. GenerateIngestionKeyController

**File**: `src/Projects/Ui/GenerateIngestionKey/GenerateIngestionKeyController.php`

**Intent**: Expose `POST /api/v1/projects/{uuid}/ingestion-key`.

**Contract**: `#[Route(path: '/projects/{projectUuid}/ingestion-key', name: 'projects_generate_ingestion_key', methods: ['POST'])]`. Response `201 Created`:
```json
{ "keyUuid": "…", "value": "mon_ing_…", "snippet": "monolog:\n  …" }
```
Same response-building shape as `RotateIngestionKeyController`, using `Response::HTTP_CREATED` instead of the default 200.

#### 2. Behat fixture — fixed uuid for the keyless project

**File**: `tests/Behat/Fixtures/logMonitoring.yml`

**Intent**: `project2` ("Another Owner Project") already has no ingestion key, making it the natural keyless-project fixture, but it currently has no fixed `uuid` so Behat scenarios can't address it directly.

**Contract**: Add `uuid: <uuid('9b1e5f3a-2c4d-4a6b-8e7f-1a2b3c4d5e6f')>` to `project2`'s `register` block, matching the pattern already used for `project1`.

#### 3. Behat acceptance tests

**File**: `tests/Behat/Features/Projects/projectApiKey.feature`

**Intent**: Extend the existing S-04 acceptance feature (rather than a new file) since these scenarios exercise the same `/ingestion-key` resource family.

**Contract** — new scenarios appended under a `# POST /api/v1/projects/{uuid}/ingestion-key` heading:
- Owner generates the first key for the keyless project (`9b1e5f3a-…`) → 201, `value` starts with `"mon_ing_"`, `snippet` contains the value
- After generating, `GET /ingestion-key` for that project reflects the new value
- Generating again on the same project (now has an active key) → 409, `error` equal to the translated conflict message
- Unauthenticated request → 401
- Wrong-org user → 404

### Success Criteria:

#### Automated Verification:

- Static analysis passes: `./vendor/bin/phpstan analyse`
- Unit tests pass: `./vendor/bin/phpunit`
- Behat suite passes: `./vendor/bin/behat`

#### Manual Verification:

- `POST /api/v1/projects/9b1e5f3a-2c4d-4a6b-8e7f-1a2b3c4d5e6f/ingestion-key` (authenticated, keyless project): returns 201 with `value` and `snippet`
- Repeating the same call returns 409
- `GET .../ingestion-key` after generation reflects the new `value`

**Implementation Note**: After completing this phase and all automated verification passes, pause for manual confirmation before proceeding to epilogue/closure.

---

## Testing Strategy

### Unit Tests:

- `GenerateIngestionKeyHandlerTest` — success, conflict (key already exists), not-found, wrong-org

### Integration Tests (Behat):

- Extended scenarios in `tests/Behat/Features/Projects/projectApiKey.feature` covering generate success, post-generate GET reflecting the new value, conflict on double-generate, 401, 404

### Manual Testing Steps:

1. Obtain a JWT: `POST /api/v1/auth/sign-in` with `owner@monitoring-webowi.test` / `demo1234`
2. `POST /api/v1/projects` to create a fresh project (no key yet)
3. `GET /api/v1/projects/{uuid}/ingestion-key` — confirm `status: "none"`, `value: null`
4. `POST /api/v1/projects/{uuid}/ingestion-key` — confirm 201 with `value` and `snippet`
5. `GET /api/v1/projects/{uuid}/ingestion-key` — confirm it now reflects the generated `value`
6. `POST /api/v1/projects/{uuid}/ingestion-key` again — confirm 409
7. Confirm wrong-org JWT returns 404 on the generate endpoint

## References

- Roadmap slice: `context/foundation/roadmap.md` § S-04 (this change closes the gap between project creation and a usable key)
- Sibling change: `context/changes/view-and-copy-project-api-key/plan.md` (S-04 — GET/rotate endpoints this change is a sibling of)
- Template handler: `src/Projects/Application/RotateIngestionKey/RotateIngestionKeyHandler.php`
- Template exception: `src/Projects/Application/Exception/ProjectNameAlreadyExistsException.php`
- Existing acceptance feature: `tests/Behat/Features/Projects/projectApiKey.feature`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles.

### Phase 1: Application Layer — Generate Handler and Conflict Exception

#### Automated

- [x] 1.1 Static analysis passes: `./vendor/bin/phpstan analyse`
- [x] 1.2 Unit tests pass: `./vendor/bin/phpunit`

### Phase 2: Controller, Route, and Acceptance Tests

#### Automated

- [ ] 2.1 Static analysis passes: `./vendor/bin/phpstan analyse`
- [ ] 2.2 Unit tests pass: `./vendor/bin/phpunit`
- [ ] 2.3 Behat suite passes: `./vendor/bin/behat`

#### Manual

- [ ] 2.4 `POST .../ingestion-key` on a keyless project returns 201 with `value` and `snippet`
- [ ] 2.5 Repeating the call returns 409
- [ ] 2.6 `GET .../ingestion-key` after generation reflects the new value
