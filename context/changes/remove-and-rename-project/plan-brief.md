# Remove and Rename Project — Plan Brief

> Full plan: `context/changes/remove-and-rename-project/plan.md`

## What & Why

Add `DELETE` and `PATCH` operations for a project to the Projects API so a project owner can remove a project or update its settings (name, platform, status) — closing a gap where the domain already models a project's lifecycle but exposes no way to end one or edit it after creation.

## Starting Point

A `DeleteProjectHandler`/`DeleteProjectCommand` already exist in `src/Projects/Application/` but are dead code: no controller, no route, and they're written in an older style (explicit `organizationId`/`userId` args, non-translatable exceptions) that doesn't match every other handler in the module (`GetProjectHandler`, `RotateIngestionKeyHandler`), which resolve the user via `CurrentUserFetcher` internally. No settings-update capability exists in any form — no domain methods, no handler, no route.

## Desired End State

`DELETE /api/v1/projects/{uuid}` removes a project and its ingestion keys, returning 204. `PATCH /api/v1/projects/{uuid}` accepts any of `{name, platform, status}` and updates only what's provided in one atomic save, returning 200 with the updated project. Both enforce the same organization-ownership rule as every other project endpoint, and both are covered by unit + Behat tests matching the module's existing patterns.

**Mid-implementation pivot**: Phase 2 was originally planned and built as a dedicated `RenameProject` (name-only) slice. After Phase 1 shipped, the user described the real consumer — an SPA settings page editing name/platform/status together with one Save action — and asked to rework it into a combined endpoint for that reason. It was reworked before Phase 2's commit landed, so no throwaway code shipped.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Legacy `DeleteProjectHandler` | Refactor to current convention | Keeps one consistent handler pattern across the whole Projects module instead of shipping a second, inconsistent one. | Plan (user-confirmed) |
| Deleting a project's `IngestionKey` rows | Cascade-delete (same bounded context) | `IngestionKey` lives inside `Projects/Domain` — no architectural boundary crossed. | Plan (user-confirmed) |
| Deleting a project's `LogEntry` rows | Leave orphaned, explicitly deferred | Cascading would either violate the codebase's documented Logging→Projects-only dependency rule, or require inventing a net-new cross-context event mechanism for one use case — the user didn't respond to this specific fork, so the plan takes the option that adds no new architecture and breaks no documented rule. | Plan (default, unconfirmed — flagged below) |
| Rename name-uniqueness | Enforce globally via existing `existsByName()`, WITH self-exclusion | Reversed from the original dedicated-rename decision (which accepted no self-exclusion as a tradeoff): once the endpoint became a settings-form save that may resubmit the unchanged name, skipping the uniqueness check when the name didn't change is a correctness fix, not a preference. | Plan (superseded — see pivot note above) |
| Settings-update shape | One `UpdateProjectSettings` command with optional `name`/`platform`/`status`, one `save()` call | Matches the real SPA use case (one form, one Save button) and gives atomicity — three separate endpoint calls risk a partial-failure state where e.g. the name updates but a platform change 409s. Still scoped (not a generic CRUD `UpdateProject`) — DDD discipline here is "one coherent business concern per command," and "user saves their settings form" is one concern. | Plan (user-directed pivot) |
| Delete/update route shape | `DELETE /projects/{uuid}`, `PATCH /projects/{uuid}` | Matches the existing `GET /projects/{uuid}` path, differentiated purely by HTTP method — standard REST, no new path segments. | Plan |

## Scope

**In scope:**
- `DELETE /api/v1/projects/{uuid}` (rebuild of the dead handler + cascade delete of `IngestionKey` rows + new controller)
- `PATCH /api/v1/projects/{uuid}` accepting optional `name`/`platform`/`status` (new domain mutators, handler, controller)
- Cleanup of the old non-translatable Projects exceptions
- Unit + Behat coverage for both

**Out of scope:**
- Cascading/cleaning up orphaned `LogEntry` rows for a deleted project
- Soft-delete / a "removed" project status
- A fully generic CRUD `UpdateProject` endpoint (arbitrary fields)
- Any side effects tied to `status` changes (e.g. gating ingestion when `INACTIVE`) — nothing today reads project status for that purpose; that's a pre-existing gap
- Organization-scoped (vs. global) name uniqueness

## Architecture / Approach

Two independent vertical slices inside the existing `src/Projects/{Domain,Application,Ui}` layering, each following the `CreateProject`/`GetProject` slice pattern exactly: one subfolder per operation, ownership-check via `CurrentUserFetcher` + `belongsToOrganization()` inside the handler, `TranslatableExceptionInterface` exceptions for HTTP error mapping.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Remove project | `DELETE /projects/{uuid}`, cascades `IngestionKey` rows, rebuilt handler | `LogEntry` rows are left orphaned by design (see Open Risks) |
| 2. Update project settings | `PATCH /projects/{uuid}` with optional `name`/`platform`/`status`, one atomic save | `status` has no side effects yet (flag-only); reworked mid-implementation from a name-only rename (see pivot note) |

**Prerequisites:** None — builds entirely on existing `Project`/`IngestionKey` entities and repositories.
**Estimated effort:** ~1 session across 2 phases.

## Open Risks & Assumptions

- **Unresolved cross-context question**: whether/how to eventually clean up orphaned `LogEntry` rows for deleted projects was posed to the user and went unanswered; the plan defaults to "leave orphaned, no new architecture." Revisit if this becomes a real data-hygiene problem.
- Deleting a project with active ingestion traffic is not specially guarded — any owner can delete at any time, matching the original (dead) handler's behavior.
- `existsByName()` remains global across organizations (pre-existing behavior, not introduced by this change) — two different organizations still cannot use the same project name.

## Success Criteria (Summary)

- A project owner can delete their project via the API and it disappears (404 on subsequent GET), along with its ingestion keys.
- A project owner can update their project's name, platform, and status — together or individually — via one API call, and the new values are immediately reflected on GET.
- Both operations correctly reject unauthenticated requests (401) and cross-organization access (404).
