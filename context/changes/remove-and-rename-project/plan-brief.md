# Remove and Rename Project — Plan Brief

> Full plan: `context/changes/remove-and-rename-project/plan.md`

## What & Why

Add `DELETE` and `PATCH` operations for a project to the Projects API so a project owner can remove a project or change its name — closing a gap where the domain already models a project's lifecycle but exposes no way to end or rename one.

## Starting Point

A `DeleteProjectHandler`/`DeleteProjectCommand` already exist in `src/Projects/Application/` but are dead code: no controller, no route, and they're written in an older style (explicit `organizationId`/`userId` args, non-translatable exceptions) that doesn't match every other handler in the module (`GetProjectHandler`, `RotateIngestionKeyHandler`), which resolve the user via `CurrentUserFetcher` internally. Rename doesn't exist in any form — no domain method, no handler, no route.

## Desired End State

`DELETE /api/v1/projects/{uuid}` removes a project and its ingestion keys, returning 204. `PATCH /api/v1/projects/{uuid}` with `{"name": "..."}` renames a project, returning 200 with the updated project. Both enforce the same organization-ownership rule as every other project endpoint, and both are covered by unit + Behat tests matching the module's existing patterns.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Legacy `DeleteProjectHandler` | Refactor to current convention | Keeps one consistent handler pattern across the whole Projects module instead of shipping a second, inconsistent one. | Plan (user-confirmed) |
| Deleting a project's `IngestionKey` rows | Cascade-delete (same bounded context) | `IngestionKey` lives inside `Projects/Domain` — no architectural boundary crossed. | Plan (user-confirmed) |
| Deleting a project's `LogEntry` rows | Leave orphaned, explicitly deferred | Cascading would either violate the codebase's documented Logging→Projects-only dependency rule, or require inventing a net-new cross-context event mechanism for one use case — the user didn't respond to this specific fork, so the plan takes the option that adds no new architecture and breaks no documented rule. | Plan (default, unconfirmed — flagged below) |
| Rename name-uniqueness | Enforce globally via existing `existsByName()`, no self-exclusion | Reuses `CreateProjectHandler`'s exact rule with zero new logic; renaming to the current name will 409 as an accepted tradeoff. | Plan (user-confirmed) |
| Delete/rename route shape | `DELETE /projects/{uuid}`, `PATCH /projects/{uuid}` | Matches the existing `GET /projects/{uuid}` path, differentiated purely by HTTP method — standard REST, no new path segments. | Plan |

## Scope

**In scope:**
- `DELETE /api/v1/projects/{uuid}` (rebuild of the dead handler + cascade delete of `IngestionKey` rows + new controller)
- `PATCH /api/v1/projects/{uuid}` with `{"name": "..."}` (new domain method, handler, controller)
- Cleanup of the old non-translatable Projects exceptions
- Unit + Behat coverage for both

**Out of scope:**
- Cascading/cleaning up orphaned `LogEntry` rows for a deleted project
- Soft-delete / a "removed" project status
- A general "update project" endpoint (platform/status changes)
- Organization-scoped (vs. global) name uniqueness

## Architecture / Approach

Two independent vertical slices inside the existing `src/Projects/{Domain,Application,Ui}` layering, each following the `CreateProject`/`GetProject` slice pattern exactly: one subfolder per operation, ownership-check via `CurrentUserFetcher` + `belongsToOrganization()` inside the handler, `TranslatableExceptionInterface` exceptions for HTTP error mapping.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Remove project | `DELETE /projects/{uuid}`, cascades `IngestionKey` rows, rebuilt handler | `LogEntry` rows are left orphaned by design (see Open Risks) |
| 2. Rename project | `PATCH /projects/{uuid}`, `Project::rename()` | Renaming to a project's own current name returns 409, not a no-op (accepted tradeoff) |

**Prerequisites:** None — builds entirely on existing `Project`/`IngestionKey` entities and repositories.
**Estimated effort:** ~1 session across 2 phases.

## Open Risks & Assumptions

- **Unresolved cross-context question**: whether/how to eventually clean up orphaned `LogEntry` rows for deleted projects was posed to the user and went unanswered; the plan defaults to "leave orphaned, no new architecture." Revisit if this becomes a real data-hygiene problem.
- Deleting a project with active ingestion traffic is not specially guarded — any owner can delete at any time, matching the original (dead) handler's behavior.
- `existsByName()` remains global across organizations (pre-existing behavior, not introduced by this change) — two different organizations still cannot use the same project name.

## Success Criteria (Summary)

- A project owner can delete their project via the API and it disappears (404 on subsequent GET), along with its ingestion keys.
- A project owner can rename their project via the API and the new name is immediately reflected on GET.
- Both operations correctly reject unauthenticated requests (401) and cross-organization access (404).
