---
change_id: provision-ingestion-key-on-create
title: Add a dedicated endpoint to generate a project's first ingestion key
status: implemented
created: 2026-07-04
updated: 2026-07-04
archived_at: null
---

## Notes

Originally framed as "auto-provision a key when a project is created," discovered while verifying S-04 (`view-and-copy-project-api-key`, already implemented) against a project created through the real `POST /projects` flow: `GET /api/v1/projects/{uuid}/ingestion-key` returns `status: "none"`, `value: null` because `CreateProjectHandler` never creates an `IngestionKey`.

Reframed after discussing the intended UX: the user wants a two-step project wizard (1: create project + pick platform, 2: generate ingestion key + copyable install snippet). That means "no key until step 2 asks" is correct behavior, not a bug — `status: "none"` is the expected state between the two steps. `CreateProjectHandler` stays unchanged.

The actual gap is that there's no clean endpoint for "generate the first key" — only `POST .../ingestion-key/rotate`, which happens to handle a null existing key gracefully but is semantically about *replacing* a key, not creating the first one. Decision: add `POST /api/v1/projects/{uuid}/ingestion-key` as a dedicated first-time-generate endpoint (409 if a key already exists), keeping `rotate` reserved for actual rotation. See `plan.md` for the full design.
