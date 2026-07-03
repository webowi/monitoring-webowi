# Remove and Rename Project Implementation Plan

## Overview

Add two API operations to the Projects bounded context, following the module's established DDD/hexagonal layering (`Domain` → `Application` → `Ui`, one subfolder per vertical slice): `DELETE /api/v1/projects/{uuid}` to remove a project, and `PATCH /api/v1/projects/{uuid}` to rename one. Both are new HTTP surface; the delete side reuses and repairs an existing but unwired, non-conforming handler.

## Current State Analysis

- `src/Projects/Application/DeleteProjectHandler.php` and `DeleteProjectCommand.php` already exist but are dead code — no `Ui` controller references them, and no route exists. They also predate the module's current convention: they take `organizationId`/`userId` explicitly in the command instead of resolving the user via `CurrentUserFetcher` inside the handler, and they throw `ProjectNotFoundException` / `ProjectAccessDeniedException` (plain `\Exception`, `src/Projects/Application/`) instead of the `TranslatableExceptionInterface` exceptions (`src/Projects/Application/Exception/`) that `GetProjectHandler` and `RotateIngestionKeyHandler` use today.
- No rename/update capability exists anywhere in the Projects module — no domain method, no application handler, no route.
- `IngestionKey.projectId` (`src/Projects/Domain/IngestionKey.php:35`) and `LogEntry.projectId` (`src/Logging/Domain/LogEntry.php:33`) are plain UUID columns with no DB foreign key to `project`. Deleting a project today would silently orphan both.
- `IngestionKey` lives in the same bounded context as `Project` (`Projects/Domain`), so cascading its deletion from `DeleteProjectHandler` is a same-context operation.
- `LogEntry` lives in the `Logging` context. The codebase has a documented, deliberate one-way dependency rule — `context/changes/view-and-copy-project-api-key/plan.md:141` states new Projects handlers should avoid importing from the Logging domain to prevent a backwards dependency — and `Logging/Application` already depends on `Projects/Domain` (`GetProjectFreshnessHandler` imports `ProjectRepositoryInterface`), confirming the direction is Logging → Projects only. No domain-event or cross-context messaging mechanism exists anywhere in this codebase (Messenger is used only for async log ingestion, entirely inside Logging).
- `ProjectRepositoryInterface::existsByName()` (`src/Projects/Domain/ProjectRepositoryInterface.php:15`) checks name uniqueness globally (not scoped by organization) and is already used by `CreateProjectHandler`. Reusing it as-is for rename means renaming a project to its own current name will also raise `ProjectNameAlreadyExistsException` — an accepted, deliberate tradeoff (see Open Risks).

### Key Discoveries:

- Handlers with a single UUID argument skip a Command DTO entirely — `GetProjectHandler::handle(Uuid $projectUuid)` (`src/Projects/Application/GetProject/GetProjectHandler.php:20`) and `RotateIngestionKeyHandler::handle(Uuid $projectUuid)` — so the rebuilt `DeleteProjectHandler` follows the same shape and the `DeleteProjectCommand` DTO is dropped.
- The ownership-check pattern is identical across `GetProjectHandler`, `RotateIngestionKeyHandler`, and `GetProjectFreshnessHandler`: `getById()` + `CurrentUserFetcher::fetchUser()` + `belongsToOrganization()` check, throwing `ProjectNotFoundOrAccessDeniedException` (`src/Projects/Application/Exception/ProjectNotFoundOrAccessDeniedException.php`) — reused as-is by both new/rebuilt handlers.
- `TranslatableExceptionListener` (`src/Kernel/TranslatableException/TranslatableExceptionListener.php`) auto-maps any `TranslatableExceptionInterface` exception to a JSON `{"error": "..."}` response at the HTTP code baked into the exception's constructor — translations live in `translations/messages.pl.yaml`.
- `Project.name`, `Project.status`, etc. are plain public (non-`readonly`) properties (`src/Projects/Domain/Project.php:37`), so a `rename()` method is a simple property mutation, consistent with how `IngestionKey::revoke()`-style domain methods already work in this module.
- Doctrine's `TimestampableSubscriber::preRemove` (`src/Kernel/EventSubscriber/TimestampableSubscriber.php`) stamps `deletedAt`/`deletedBy` on entity-by-entity removal but does not fire for DQL bulk deletes — irrelevant here since `IngestionKey` rows are being hard-deleted, not audited.
- Behat fixture `tests/Behat/Fixtures/logMonitoring.yml` already provisions one project (`project1`, fixed UUID `135a465d-cf7a-4ca8-872a-c76272cbb16f`) with one active and one revoked `IngestionKey`, plus an owner/other-org user pair — reusable as-is for the delete feature's happy-path and cross-org scenarios.

## Desired End State

- `DELETE /api/v1/projects/{uuid}` removes the project and its `IngestionKey` rows for the caller's own organization, returns `204 No Content`; returns `404` (translated `error` body) for a missing/foreign project, `401` unauthenticated.
- `PATCH /api/v1/projects/{uuid}` with any of `{"name": "...", "platform": "...", "status": "..."}` updates only the fields provided, within the caller's own organization, returns `200` with the updated project representation (`uuid`, `name`, `platform`, `status`); returns `409` on a genuine name collision (not on resubmitting the project's own current name), `422` on a blank/oversized name or an invalid platform/status, `404` for a missing/foreign project, `401` unauthenticated.
- The old, unwired `DeleteProjectHandler`/`DeleteProjectCommand` and their non-translatable exceptions are gone, replaced by handlers that match every other handler in the module.
- Verify via: `make phpunit`, the new Behat features, and `POST /projects` → `PATCH .../{uuid}` (all three fields) → `GET .../{uuid}` → `DELETE .../{uuid}` → `GET .../{uuid}` (404) manually.

## What We're NOT Doing

- Not touching `LogEntry` rows on project delete. Cascading them would require either violating the documented Logging→Projects-only dependency direction or introducing a net-new cross-context event mechanism — both out of proportion for this change. Log entries for a deleted project are left orphaned; a follow-up cleanup mechanism is explicitly deferred (see Open Risks).
- Not introducing a soft-delete / "removed" status. `ProjectStatusEnum` stays `ACTIVE`/`INACTIVE` only; delete remains a hard row removal, matching the existing (dead) `DeleteProjectHandler`'s behavior and the repository's existing `remove()` method.
- Not building a fully generic CRUD `UpdateProject` endpoint. `UpdateProjectSettings` is deliberately scoped to the three settings-page fields (`name`, `platform`, `status`) — not an arbitrary-field PATCH.
- Not adding organization-scoped name uniqueness. `existsByName()` stays global, matching `CreateProjectHandler`'s existing behavior; changing that scope is a separate concern.
- Not giving `status` any side effects beyond the flag itself. Nothing in the codebase today checks a project's status to gate ingestion or anything else — that's a pre-existing gap, not something this change addresses.

## Implementation Approach

Two independent vertical slices, each touching `Domain` (rename only) → `Application` → `Ui` → tests, mirroring the `CreateProject`/`GetProject` slice layout. Phase 1 also repairs the pre-existing, never-wired `DeleteProjectHandler` in place rather than leaving two competing implementations.

## Phase 1: Remove project

### Overview

Rebuild the dead `DeleteProjectHandler` to match the module's current handler convention, cascade-delete the project's `IngestionKey` rows, and expose it via a `DELETE` controller.

### Changes Required:

#### 1. Ingestion key bulk removal

**File**: `src/Projects/Domain/IngestionKeyRepositoryInterface.php`

**Intent**: Add a method to remove all ingestion keys belonging to a project, needed by the delete handler's cascade.

**Contract**: `removeAllByProjectId(Uuid $projectId): void`.

**File**: `src/Projects/Infrastructure/IngestionKeyRepository.php`

**Intent**: Implement the bulk removal as a single DQL `DELETE` (not entity-by-entity), since a project can accumulate several rotated/revoked keys.

**Contract**: Doctrine QueryBuilder `->delete()` filtered by `projectId`, executed via `getQuery()->execute()`.

#### 2. Exception cleanup

**File**: `src/Projects/Application/Exception/ProjectCannotRemoveException.php` (new)

**Intent**: Translatable replacement for the old plain exception, matching `CannotSaveProjectException`'s shape.

**Contract**: `extends \Exception implements TranslatableExceptionInterface`, message `'Cannot remove project.'`, code `Response::HTTP_INTERNAL_SERVER_ERROR`.

**File**: `translations/messages.pl.yaml`

**Intent**: Add the Polish translation for the new exception message.

**Contract**: Add entry `'Cannot remove project.': 'Nie można usunąć projektu.'`.

**Files removed**: `src/Projects/Application/ProjectCannotRemoveException.php`, `src/Projects/Application/ProjectNotFoundException.php`, `src/Projects/Application/ProjectAccessDeniedException.php` — dead once the handler below stops using them; nothing else in the codebase references them (verified via repo-wide search).

#### 3. Delete handler rebuild

**Files removed**: `src/Projects/Application/DeleteProjectCommand.php`, `src/Projects/Application/DeleteProjectHandler.php`.

**File**: `src/Projects/Application/DeleteProject/DeleteProjectHandler.php` (new location, matching the one-subfolder-per-slice convention used by `CreateProject/`, `GetProject/`)

**Intent**: Resolve the project and current user the same way `GetProjectHandler`/`RotateIngestionKeyHandler` do, cascade-remove the project's ingestion keys, then remove the project itself; wrap the removal in the existing try/log-critical/rethrow pattern from the old handler.

**Contract**: `handle(Uuid $projectUuid): void`. Constructor deps: `ProjectRepositoryInterface`, `IngestionKeyRepositoryInterface`, `CurrentUserFetcher`, `LoggerInterface`. Throws `ProjectNotFoundOrAccessDeniedException` (missing or foreign project) or `ProjectCannotRemoveException` (repository failure, logged at `critical`).

#### 4. Delete controller

**File**: `src/Projects/Ui/DeleteProject/DeleteProjectController.php` (new)

**Intent**: Thin controller invoking the handler, following `GetProjectController`'s shape (no request body, path param only).

**Contract**: `#[Route(path: '/projects/{projectUuid}', name: 'projects_delete_project', methods: ['DELETE'])]`. Returns `204 No Content` on success.

### Success Criteria:

#### Automated Verification:

- Unit tests pass: `make phpunit` (new `tests/Unit/Projects/Application/DeleteProject/DeleteProjectHandlerTest.php` covering: successful delete cascades ingestion-key removal before project removal; not-found project throws `ProjectNotFoundOrAccessDeniedException`; foreign-org project throws the same; repository failure logs `critical` and throws `ProjectCannotRemoveException`)
- Behat passes: `./vendor/bin/behat --tags=@projects` (new `tests/Behat/Features/Projects/deleteProject.feature`, reusing `logMonitoring` fixture: owner deletes own project → 204, then GET the same UUID → 404; wrong-org user deletes → 404; unauthenticated → 401)
- Static analysis passes: `./vendor/bin/phpstan analyse`
- No remaining references to the removed classes: `grep -rn "DeleteProjectCommand\|ProjectAccessDeniedException\b" src/ tests/` returns nothing outside this phase's new files

#### Manual Verification:

- `POST /api/v1/projects`, then `DELETE /api/v1/projects/{uuid}` for the created project, then `GET /api/v1/projects/{uuid}` returns 404
- Confirm via DB inspection that the deleted project's `ingestion_key` rows are gone

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Update project settings

### Overview

**Reworked mid-implementation** (originally planned as a dedicated `RenameProject` slice — see plan brief's Key Decisions for the pivot rationale). Add `rename()`/`changePlatform()`/`changeStatus()` domain methods and a single `PATCH` slice accepting optional `name`/`platform`/`status`, so a settings-page save is one atomic request instead of three.

### Changes Required:

#### 1. Domain mutators

**File**: `src/Projects/Domain/Project.php`

**Intent**: Allow the application layer to change a project's name, platform, and status without exposing the constructor. Kept as three small single-field mutators (not one combined setter) so each remains independently reusable and testable.

**Contract**: `rename(string $name): void`, `changePlatform(ProjectPlatformEnum $platform): void`, `changeStatus(ProjectStatusEnum $status): void` — each a plain property mutation, no internal validation (validation happens at the `Ui` input layer).

**File**: `src/Projects/Domain/ProjectStatusEnum.php`

**Intent**: Add a `values()` helper so the `Ui` input layer can validate `status` the same way `ProjectPlatformEnum::values()` already validates `platform`.

**Contract**: `public static function values(): array` — mirrors `ProjectPlatformEnum::values()`.

#### 2. Update-settings application slice

**File**: `src/Projects/Application/UpdateProjectSettings/UpdateProjectSettingsCommand.php` (new)

**Intent**: Carry the target project UUID plus optional new values for each settable field.

**Contract**: `final readonly class` with `public Uuid $projectUuid`, `public ?string $name = null`, `public ?ProjectPlatformEnum $platform = null`, `public ?ProjectStatusEnum $status = null`.

**File**: `src/Projects/Application/UpdateProjectSettings/UpdateProjectSettingsHandler.php` (new)

**Intent**: Resolve and authorize the project the same way every other handler in this module does, then apply only the fields that were actually provided, then persist everything in a single `save()` call so the whole settings update is atomic. Uniqueness is checked only when the submitted name differs from the project's current name — otherwise a settings form that resubmits the full (unchanged) form state would spuriously 409 on its own name.

**Contract**: `handle(UpdateProjectSettingsCommand $command): Project`. Constructor deps: `ProjectRepositoryInterface`, `CurrentUserFetcher`. Throws `ProjectNotFoundOrAccessDeniedException` (missing/foreign project) or `ProjectNameAlreadyExistsException` (reused as-is from `Application/Exception/`) before calling the relevant `Project` mutators + one `$projectRepository->save()`.

#### 3. Update-settings Ui slice

**File**: `src/Projects/Ui/UpdateProjectSettings/UpdateProjectSettingsInput.php` (new)

**Intent**: Validate each field only when it's actually present in the request body — omitted fields must pass validation untouched, but an explicitly blank/invalid value for a present field must still fail.

**Contract**: `final readonly class` with `public ?string $name = null` (`#[Assert\Type('string')]`, `#[Assert\NotBlank(allowNull: true)]`, `#[Assert\Length(max: 500)]`), `public ?string $platform = null` (`#[Assert\Choice(callback: [ProjectPlatformEnum::class, 'values'])]`), `public ?string $status = null` (`#[Assert\Choice(callback: [ProjectStatusEnum::class, 'values'])]`). `Assert\Choice` already treats `null` as valid without extra configuration.

**File**: `src/Projects/Ui/UpdateProjectSettings/UpdateProjectSettingsController.php` (new)

**Intent**: Validate input, convert present string fields to their enums, invoke the handler, return the updated project representation — same response shape as `GetProjectController`/`CreateProjectController`.

**Contract**: `#[Route(path: '/projects/{projectUuid}', name: 'projects_update_project_settings', methods: ['PATCH'])]`. `__invoke(string $projectUuid, #[MapRequestPayload] UpdateProjectSettingsInput $input): JsonResponse`, `200` with `{uuid, name, platform, status}`.

### Success Criteria:

#### Automated Verification:

- Unit tests pass: `make phpunit` (new `tests/Unit/Projects/Application/UpdateProjectSettings/UpdateProjectSettingsHandlerTest.php` covering: updating all three fields in one `save()` call; resubmitting the unchanged current name alongside another field change does NOT call `existsByName`; providing only one field leaves the others untouched; not-found/foreign project throws `ProjectNotFoundOrAccessDeniedException`; a genuine name collision throws `ProjectNameAlreadyExistsException`)
- Behat passes: `./vendor/bin/behat --tags=@projects` (new `tests/Behat/Features/Projects/updateProjectSettings.feature`: owner updates name+platform+status in one save → 200; owner updates only platform while resubmitting the unchanged name → 200, not 409; blank name → 422; invalid platform → 422; name collision with another project in the same org → 409; wrong-org user → 404; unauthenticated → 401)
- Static analysis passes: `./vendor/bin/phpstan analyse`

#### Manual Verification:

- `POST /api/v1/projects`, then `PATCH /api/v1/projects/{uuid}` with `{name, platform, status}` all at once, then `GET /api/v1/projects/{uuid}` reflects all three new values

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- Mirror `CreateProjectHandlerTest`'s structure: mock `ProjectRepositoryInterface`/`IngestionKeyRepositoryInterface`/`CurrentUserFetcher`/`LoggerInterface`, assert method call expectations and thrown exception types.
- Cover both the happy path and every thrown exception per handler.

### Integration Tests:

- Behat features under `tests/Behat/Features/Projects/`, tagged `@projects`, following `createProject.feature`/`projectApiKey.feature`'s Background + Scenario structure (sign in, JSON request, status code + JSON node assertions).

### Manual Testing Steps:

1. Create a project via `POST /api/v1/projects`.
2. Update its name, platform, and status in one `PATCH /api/v1/projects/{uuid}` call and confirm all three via `GET`.
3. Delete it via `DELETE /api/v1/projects/{uuid}` and confirm the subsequent `GET` returns 404.
4. Inspect the `ingestion_key` table to confirm no rows remain for the deleted project's UUID.

## Migration Notes

No schema migration needed — both operations work against the existing `project` and `ingestion_key` tables.

## References

- Similar implementation: `src/Projects/Application/CreateProject/CreateProjectHandler.php`, `src/Projects/Ui/CreateProject/CreateProjectController.php`
- Ownership-check pattern: `src/Projects/Application/RotateIngestionKey/RotateIngestionKeyHandler.php:28-35`
- Dependency-direction precedent: `context/changes/view-and-copy-project-api-key/plan.md:141`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles.

### Phase 1: Remove project

#### Automated

- [x] 1.1 Unit tests pass: `make phpunit` — cfb09ad
- [x] 1.2 Behat passes: `./vendor/bin/behat --tags=@projects` — cfb09ad
- [x] 1.3 Static analysis passes: `./vendor/bin/phpstan analyse` — cfb09ad
- [x] 1.4 No remaining references to removed classes — cfb09ad

#### Manual

- [x] 1.5 Create → delete → GET-404 flow verified via API — cfb09ad
- [x] 1.6 DB inspection confirms ingestion_key rows removed — cfb09ad

### Phase 2: Update project settings

#### Automated

- [x] 2.1 Unit tests pass: `make phpunit` — 0860866
- [x] 2.2 Behat passes: `./vendor/bin/behat --tags=@projects` — 0860866
- [x] 2.3 Static analysis passes: `./vendor/bin/phpstan analyse` — 0860866

#### Manual

- [x] 2.4 Create → update settings (name+platform+status) → GET-reflects-new-values flow verified via API — 0860866
