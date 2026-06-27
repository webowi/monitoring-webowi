# View and Copy Project API Key — Implementation Plan

## Overview

S-04 ships three new endpoints under `/api/v1/projects/{uuid}/` so the SPA can display project details, reveal the ingestion key with a Monolog install snippet, and rotate the key. A nullable `key_value` column is added to `ingestion_key` so the plaintext key can be returned by the API. Key rotation revokes the current active key, generates a new `mon_ing_<32hex>` key, and persists both — returning the new plaintext value and snippet in the response (the only moment the new key is exposed in full).

## Current State Analysis

`src/Projects/Ui/` contains only a `.gitkeep` — no project-facing API endpoints exist yet. The Projects domain has `Project` and `IngestionKey` entities, but `IngestionKey` stores only `key_hash` (HMAC-SHA256 via `IngestionKeyHasher`), never the plaintext. There is no way to return the actual key value without a schema change.

Three controller patterns are established in the Logging bounded context (`ListProjectLogsController`, `ProjectFreshnessController`) and can be followed exactly. The JWT + ownership check flow is established: `CurrentUserFetcher → user.organizationId → Project.belongsToOrganization()`.

## Desired End State

After this plan:
- `GET /api/v1/projects/{uuid}` returns `{uuid, name, platform, status}` (JWT-protected, ownership-scoped)
- `GET /api/v1/projects/{uuid}/ingestion-key` returns `{keyUuid, status, value, snippet}` — `value` is the plaintext key; `snippet` is the Monolog YAML with key and URL substituted in
- `POST /api/v1/projects/{uuid}/ingestion-key/rotate` revokes the current active key, generates a new one, and returns `{keyUuid, value, snippet}`
- All three endpoints return 401 for unauthenticated requests and 404 for wrong-organisation access
- Behat scenarios cover success, 401, and 404 paths for every endpoint
- The old ingestion key is rejected with 401 after rotation (Behat-verified)

### Key Discoveries:

- `IngestionKey.keyHash` is stored as `hash_hmac('sha256', $plaintext, $appSecret)` — irreversible; plaintext must be stored separately (`src/Projects/Domain/IngestionKey.php:46`)
- `IngestionKeyRepository` extends `ServiceEntityRepository` — `save()` is implemented via `getEntityManager()->persist() + flush()` (`src/Projects/Infrastructure/IngestionKeyRepository.php`)
- `ProjectNotFoundOrAccessDeniedException` lives in `src/Logging/Application/List/` — a duplicate belongs in the Projects application layer to avoid a backwards domain dependency
- Fixture `tests/Behat/Fixtures/logMonitoring.yml:41` seeds `ingestionKeyActive` with `keyHash` only; `keyValue` must be added for the GET endpoint to return a non-null value in tests
- No `APP_URL` env var exists yet — needed by `InstallSnippetBuilder` to compose the snippet's ingestion URL
- `IngestionKey.$uuid` has no auto-generation: `RotateIngestionKeyHandler` must call `setUuid(Uuid::v4())` explicitly when constructing the new key entity

## What We're NOT Doing

- No project CRUD (create / rename / delete project) — pre-seeded MVP only
- No key revocation without replacement — rotation is the only write operation
- No encrypted-at-rest `key_value` — plaintext is acceptable for this pre-seeded internal MVP; encryption is a pre-public-launch task
- No per-key expiry or IP allowlist — v2 concerns per PRD Open Questions
- No frontend masking logic — "masked by default, revealed on click" is a SPA concern; the API always returns the full value

## Implementation Approach

Three phases: (1) schema + domain foundation; (2) two GET read endpoints; (3) POST rotate endpoint with Behat. Unit tests land alongside the handlers in phases 2 and 3. All new application handlers follow the same function-call pattern as `GetProjectFreshnessHandler` (no message bus, direct dependency injection).

## Critical Implementation Details

- **`IngestionKey.uuid` must be set explicitly** when creating a new key in `RotateIngestionKeyHandler`. The column is not auto-generated (`@ORM\Column(type: 'uuid', unique: true)` with no `@ORM\GeneratedValue`). Call `setUuid(Uuid::v4())` before persist.
- **`key_value` column nullable** — existing rows in production (and the current fixture rows that only carry `keyHash`) will have `NULL`. `GetIngestionKeyController` must handle `value: null` gracefully in the response.

---

## Phase 1: Domain and Schema Foundation

### Overview

Add the `key_value` column to `IngestionKey`, create the migration, extend the repository interface and implementation with two new methods, seed `keyValue` in the Behat fixture, and introduce the `APP_URL` env var.

### Changes Required:

#### 1. IngestionKey entity — add `keyValue` property

**File**: `src/Projects/Domain/IngestionKey.php`

**Intent**: Store the plaintext key value alongside the hash so the API can return it.

**Contract**: New nullable `string|null $keyValue` mapped to `#[ORM\Column(name: 'key_value', type: Types::STRING, length: 255, nullable: true)]`. Getter `getKeyValue(): ?string` and setter `setKeyValue(?string $keyValue): self`.

#### 2. Doctrine migration — add `key_value` column

**File**: `migrations/Version202606XXXXXXXXXX.php` (generated by `php bin/console doctrine:migrations:generate`)

**Intent**: Alter the `ingestion_key` table to add the nullable column; rollback removes it.

**Contract**:
- `up`: `ALTER TABLE ingestion_key ADD key_value VARCHAR(255) DEFAULT NULL`
- `down`: `ALTER TABLE ingestion_key DROP COLUMN key_value`

#### 3. IngestionKeyRepositoryInterface — add `findActiveByProjectId` and `save`

**File**: `src/Projects/Domain/IngestionKeyRepositoryInterface.php`

**Intent**: Expose the two lookups S-04's handlers need.

**Contract**:
- `findActiveByProjectId(Uuid $projectId): ?IngestionKey` — returns the active (non-revoked, non-expired) key for the given project, or `null`
- `save(IngestionKey $key): void` — persist and flush a new or modified key

#### 4. IngestionKeyRepository — implement both methods

**File**: `src/Projects/Infrastructure/IngestionKeyRepository.php`

**Intent**: `findActiveByProjectId` queries by `projectId` then delegates to `isActive()` (consistent with `findOneActiveByKeyHash`). `save` uses `getEntityManager()->persist($key); getEntityManager()->flush()`.

**Contract**: Both methods are annotated `@codeCoverageIgnore @infection-ignore-all` (matching existing repository methods).

#### 5. Behat fixture — add `keyValue` to active key

**File**: `tests/Behat/Fixtures/logMonitoring.yml`

**Intent**: The GET ingestion-key endpoint must return a non-null `value` in acceptance tests.

**Contract**: Add `keyValue: 'mon_ing_demo0000000000000000000000000000'` to the `ingestionKeyActive` fixture entry. The revoked key fixture does not need a value.

#### 6. App URL env var

**Files**: `.env`, `.env.test`

**Intent**: Provide the base URL for the ingestion endpoint so `InstallSnippetBuilder` can compose the snippet without hardcoding.

**Contract**: `.env` → `APP_URL=http://localhost:8000`; `.env.test` → `APP_URL=http://localhost`.

### Success Criteria:

#### Automated Verification:

- Migration applies cleanly: `php bin/console doctrine:migrations:migrate --no-interaction`
- Schema diff is empty after migration: `php bin/console doctrine:schema:validate`
- Static analysis passes: `./vendor/bin/phpstan analyse`
- Behat fixture loads without error: `./vendor/bin/behat --dry-run`

#### Manual Verification:

- `DESCRIBE ingestion_key` shows `key_value VARCHAR(255) NULL` column
- Existing fixture rows (loaded via Behat) show `key_value = 'mon_ing_demo0000000000000000000000000000'` for `ingestionKeyActive`

**Implementation Note**: After completing this phase and all automated verification passes, pause for manual confirmation before proceeding to Phase 2.

---

## Phase 2: GET Endpoints — Project Info and Ingestion Key

### Overview

Introduce the Projects application layer (`src/Projects/Application/`) with handlers for both GET endpoints, the `InstallSnippetBuilder` service, and the corresponding controllers. Unit tests for all handlers and the snippet builder land in this phase.

### Changes Required:

#### 1. Projects-domain access exception

**File**: `src/Projects/Application/Exception/ProjectNotFoundOrAccessDeniedException.php`

**Intent**: Give the Projects bounded context its own 404 exception so new handlers don't import from the Logging domain (which would create a backwards dependency).

**Contract**: Identical shape to `App\Logging\Application\List\ProjectNotFoundOrAccessDeniedException`: extends `\Exception`, implements `TranslatableExceptionInterface`, HTTP 404, message `"Project not found."`.

#### 2. GetProjectHandler

**File**: `src/Projects/Application/GetProject/GetProjectHandler.php`

**Intent**: Load a project by UUID, assert ownership, and return the `Project` entity for the controller to serialize.

**Contract**: `handle(Uuid $projectUuid): Project`. Injects `ProjectRepositoryInterface` and `CurrentUserFetcher`. Throws `ProjectNotFoundOrAccessDeniedException` when project is null or `belongsToOrganization()` fails.

#### 3. GetProjectController

**File**: `src/Projects/Ui/GetProject/GetProjectController.php`

**Intent**: Expose `GET /api/v1/projects/{uuid}` returning project metadata.

**Contract**: `#[Route('/projects/{projectUuid}', name: 'projects_get_project', methods: ['GET'])]`. Response 200:
```json
{ "uuid": "…", "name": "…", "platform": "symfony", "status": "active" }
```
`platform` is `Project::getPlatform()->value`; `status` is `Project::getStatus()->value` (lowercase enum values).

#### 4. InstallSnippetBuilder

**File**: `src/Projects/Application/GetIngestionKey/InstallSnippetBuilder.php`

**Intent**: Compose the Monolog YAML install snippet with the ingestion URL and key value substituted in, so the API owns the canonical copy-paste instructions.

**Contract**: Service bound with `string $appUrl` (from `%env(APP_URL)%` via services.yaml binding). Method `build(string $keyValue): string` returns a YAML string pointing `POST` at `{$appUrl}/api/v1/logs/ingest` with `X-Ingestion-Key: {$keyValue}`. The exact Monolog handler type (`http`, `webhook`, or `service`) is the implementer's decision based on MonologBundle 3.x compatibility.

#### 5. GetIngestionKeyResult

**File**: `src/Projects/Application/GetIngestionKey/GetIngestionKeyResult.php`

**Intent**: Typed value object carrying the key's UUID, status, plaintext value, and snippet.

**Contract**: `readonly class GetIngestionKeyResult { public function __construct(public readonly Uuid $keyUuid, public readonly string $status, public readonly ?string $value, public readonly string $snippet) {} }`

#### 6. GetIngestionKeyHandler

**File**: `src/Projects/Application/GetIngestionKey/GetIngestionKeyHandler.php`

**Intent**: Verify project ownership, load the active ingestion key, and compose a `GetIngestionKeyResult`.

**Contract**: `handle(Uuid $projectUuid): GetIngestionKeyResult`. Injects `ProjectRepositoryInterface`, `IngestionKeyRepositoryInterface`, `CurrentUserFetcher`, `InstallSnippetBuilder`. Throws `ProjectNotFoundOrAccessDeniedException` on ownership failure. `value` in the result is `$key?->getKeyValue()` (null-safe — `findActiveByProjectId` returns null when no active key exists). Snippet is always built with an empty-string placeholder when key value is null: `$this->snippetBuilder->build($key?->getKeyValue() ?? '')`.

#### 7. GetIngestionKeyController

**File**: `src/Projects/Ui/GetIngestionKey/GetIngestionKeyController.php`

**Intent**: Expose `GET /api/v1/projects/{uuid}/ingestion-key`.

**Contract**: `#[Route('/projects/{projectUuid}/ingestion-key', name: 'projects_get_ingestion_key', methods: ['GET'])]`. Response 200:
```json
{ "keyUuid": "…", "status": "active", "value": "mon_ing_…", "snippet": "monolog:\n  …" }
```
`keyUuid` is a UUID string; `value` may be null.

#### 8. Unit tests — Phase 2

**Files**:
- `tests/Unit/Projects/Application/GetProject/GetProjectHandlerTest.php`
- `tests/Unit/Projects/Application/GetIngestionKey/GetIngestionKeyHandlerTest.php`
- `tests/Unit/Projects/Application/GetIngestionKey/InstallSnippetBuilderTest.php`

**Intent**: Cover the three new application-layer classes. Tests follow the pattern in `GetProjectFreshnessHandlerTest.php` (PHPUnit, mocked repositories, `CurrentUserFetcher` stub).

**Contract** per handler test: success path (returns expected result), project-not-found path (exception), wrong-org path (exception). `InstallSnippetBuilder` test: `build('test-key')` returns a string containing `'test-key'` and the `APP_URL`.

### Success Criteria:

#### Automated Verification:

- Static analysis passes: `./vendor/bin/phpstan analyse`
- Unit tests pass: `./vendor/bin/phpunit`

#### Manual Verification:

- Authenticated `GET /api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f` returns project name `"Owner Monitored Project"` and status `"active"`
- Authenticated `GET /api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f/ingestion-key` returns `value: "mon_ing_demo0000000000000000000000000000"` and `snippet` containing the ingestion URL and the key value

**Implementation Note**: After completing this phase and all automated verification passes, pause for manual confirmation before proceeding to Phase 3.

---

## Phase 3: POST Rotate Endpoint and Behat Acceptance Tests

### Overview

Add the key rotation write path: `IngestionKeyGenerator` service, `RotateIngestionKeyHandler` (revokes current key, creates fresh one), `RotateIngestionKeyController`, and a Behat feature covering all three S-04 endpoints.

### Changes Required:

#### 1. IngestionKeyGenerator

**File**: `src/Projects/Infrastructure/Security/IngestionKeyGenerator.php`

**Intent**: Generate a cryptographically random ingestion key in the established `mon_ing_<32hex>` format.

**Contract**: `generate(): string` returns `'mon_ing_' . bin2hex(random_bytes(16))` (40 characters total, matching the fixture key `mon_ing_demo0000000000000000000000000000`).

#### 2. RotateIngestionKeyResult

**File**: `src/Projects/Application/RotateIngestionKey/RotateIngestionKeyResult.php`

**Intent**: Value object for the rotation response.

**Contract**: `readonly class RotateIngestionKeyResult { public function __construct(public readonly Uuid $keyUuid, public readonly string $value, public readonly string $snippet) {} }`

#### 3. RotateIngestionKeyHandler

**File**: `src/Projects/Application/RotateIngestionKey/RotateIngestionKeyHandler.php`

**Intent**: Atomically revoke the current active key and generate a replacement, returning the new plaintext key and snippet.

**Contract**: `handle(Uuid $projectUuid): RotateIngestionKeyResult`. Steps in order:
1. Load project + ownership check (throws `ProjectNotFoundOrAccessDeniedException` on failure)
2. `findActiveByProjectId` — if key exists, call `revoke()` on it, then `save()` it
3. `IngestionKeyGenerator::generate()` → new plaintext
4. `IngestionKeyHasher::hash($plaintext)` → new hash
5. Construct new `IngestionKey`: `setUuid(Uuid::v4())`, `setProjectId(...)`, `setName($oldKey?->getName() ?? 'Default')`, `setKeyHash(...)`, `setKeyValue(...)`
6. `save()` the new key
7. Return `RotateIngestionKeyResult` with new key's UUID, plaintext, and `InstallSnippetBuilder::build($plaintext)`

Injects: `ProjectRepositoryInterface`, `IngestionKeyRepositoryInterface`, `CurrentUserFetcher`, `IngestionKeyGenerator`, `IngestionKeyHasher`, `InstallSnippetBuilder`.

#### 4. RotateIngestionKeyController

**File**: `src/Projects/Ui/RotateIngestionKey/RotateIngestionKeyController.php`

**Intent**: Expose `POST /api/v1/projects/{uuid}/ingestion-key/rotate`.

**Contract**: `#[Route('/projects/{projectUuid}/ingestion-key/rotate', name: 'projects_rotate_ingestion_key', methods: ['POST'])]`. Response 200:
```json
{ "keyUuid": "…", "value": "mon_ing_…", "snippet": "monolog:\n  …" }
```

#### 5. Unit tests — Phase 3

**Files**:
- `tests/Unit/Projects/Application/RotateIngestionKey/RotateIngestionKeyHandlerTest.php`
- `tests/Unit/Projects/Infrastructure/Security/IngestionKeyGeneratorTest.php`

**Intent**: `IngestionKeyGeneratorTest` asserts the output starts with `'mon_ing_'` and is 40 chars long. `RotateIngestionKeyHandlerTest` covers: success path (old key revoked + new key persisted), no-existing-key path (new key created without revoking), ownership-fail path (exception).

#### 6. Behat acceptance tests

**File**: `tests/Behat/Features/Projects/projectApiKey.feature`

**Intent**: End-to-end acceptance of all three S-04 endpoints against the seeded fixture, including ownership isolation and post-rotation key rejection.

**Contract** — scenarios to cover:

`GET /projects/{uuid}`:
- Authenticated owner → 200, `name` = `"Owner Monitored Project"`
- Unauthenticated → 401
- Wrong-org user → 404, `error` = `"Project not found."`

`GET /projects/{uuid}/ingestion-key`:
- Authenticated owner → 200, `value` = `"mon_ing_demo0000000000000000000000000000"`, `snippet` contains the key
- Unauthenticated → 401
- Wrong-org user → 404

`POST /projects/{uuid}/ingestion-key/rotate`:
- Authenticated owner → 200, `value` starts with `"mon_ing_"`, response contains `snippet`
- After rotation, old key `mon_ing_demo0000000000000000000000000000` is rejected on ingestion → 401
- Unauthenticated → 401
- Wrong-org user → 404

### Success Criteria:

#### Automated Verification:

- Static analysis passes: `./vendor/bin/phpstan analyse`
- Unit tests pass: `./vendor/bin/phpunit`
- Behat suite passes: `./vendor/bin/behat`

#### Manual Verification:

- `POST /api/v1/projects/{uuid}/ingestion-key/rotate` (authenticated): returns new `value` and `snippet`; subsequent `GET /ingestion-key` returns the new value
- Old key (`mon_ing_demo0000000000000000000000000000`) returns 401 on ingest after rotation
- All three endpoints return 404 with `{"error":"Project not found."}` when called with another org's JWT

**Implementation Note**: After completing this phase and all automated verification passes, pause for manual confirmation before proceeding to epilogue/closure.

---

## Testing Strategy

### Unit Tests:

- `GetProjectHandlerTest` — success, not-found, wrong-org
- `GetIngestionKeyHandlerTest` — success (value present), success (no active key → value null), not-found, wrong-org
- `InstallSnippetBuilderTest` — output contains key value and app URL
- `RotateIngestionKeyHandlerTest` — success (revokes old + creates new), success (no prior key), not-found, wrong-org
- `IngestionKeyGeneratorTest` — output is 40 chars, starts with `mon_ing_`

### Integration Tests (Behat):

- All scenarios in `tests/Behat/Features/Projects/projectApiKey.feature`
- Rotation scenario validates old key rejection via ingest endpoint

### Manual Testing Steps:

1. Obtain a JWT: `POST /api/v1/auth/sign-in` with `owner@monitoring-webowi.test` / `demo1234`
2. `GET /api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f` — verify project name + status
3. `GET /api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f/ingestion-key` — verify `value` and `snippet`
4. Copy `snippet` content and verify it contains the `APP_URL`-based ingestion URL and the key
5. `POST /api/v1/projects/135a465d-cf7a-4ca8-872a-c76272cbb16f/ingestion-key/rotate` — record new key value
6. Confirm `GET /ingestion-key` now returns the new key
7. Confirm old key `mon_ing_demo0000000000000000000000000000` returns 401 on `POST /api/v1/logs/ingest`
8. Confirm wrong-org JWT returns 404 on all three endpoints

## References

- Roadmap slice: `context/foundation/roadmap.md` § S-04
- PRD requirement: `context/foundation/prd.md` § FR-004
- Similar handler: `src/Logging/Application/Freshness/GetProjectFreshnessHandler.php`
- Similar controller: `src/Logging/Ui/Freshness/ProjectFreshnessController.php`
- Similar exception: `src/Logging/Application/List/ProjectNotFoundOrAccessDeniedException.php`
- Key hash algorithm: `src/Projects/Infrastructure/Security/IngestionKeyHasher.php`
- Fixture file: `tests/Behat/Fixtures/logMonitoring.yml`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles.

### Phase 1: Domain and Schema Foundation

#### Automated

- [x] 1.1 Migration applies cleanly: `php bin/console doctrine:migrations:migrate --no-interaction`
- [x] 1.2 Schema diff is empty: `php bin/console doctrine:schema:validate`
- [x] 1.3 Static analysis passes: `./vendor/bin/phpstan analyse`
- [x] 1.4 Behat fixture loads without error: `./vendor/bin/behat --dry-run`

#### Manual

- [x] 1.5 `DESCRIBE ingestion_key` shows `key_value VARCHAR(255) NULL`
- [x] 1.6 Fixture row `ingestionKeyActive` has `key_value = 'mon_ing_demo0000000000000000000000000000'`

### Phase 2: GET Endpoints

#### Automated

- [ ] 2.1 Static analysis passes: `./vendor/bin/phpstan analyse`
- [ ] 2.2 Unit tests pass: `./vendor/bin/phpunit`

#### Manual

- [ ] 2.3 `GET /api/v1/projects/{uuid}` returns correct project name and status
- [ ] 2.4 `GET /api/v1/projects/{uuid}/ingestion-key` returns `value` = `"mon_ing_demo0000000000000000000000000000"` and snippet with ingestion URL

### Phase 3: POST Rotate and Behat

#### Automated

- [ ] 3.1 Static analysis passes: `./vendor/bin/phpstan analyse`
- [ ] 3.2 Unit tests pass: `./vendor/bin/phpunit`
- [ ] 3.3 Behat suite passes: `./vendor/bin/behat`

#### Manual

- [ ] 3.4 Rotation returns new key value and snippet; GET reflects the new key
- [ ] 3.5 Old key returns 401 on ingest after rotation
- [ ] 3.6 Wrong-org JWT returns 404 on all three endpoints
