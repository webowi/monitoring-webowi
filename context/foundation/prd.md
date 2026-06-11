---
project: "Monitoring Webowi"
version: 1
status: draft
created: 2026-05-26
context_type: greenfield
product_type: web-app
target_scale:
  users: small
  qps: low
  data_volume: small
timeline_budget:
  mvp_weeks: 3
  hard_deadline: null
  after_hours_only: true
---

# Monitoring Webowi — Product Requirements Document

## Vision & Problem Statement

A solo developer (or a small team) running a Symfony side-project app in production discovers their app is throwing 500-class errors — typically over a weekend, or right after deploying a new feature — and has no fast way to see what happened. Their logs sit on the server as raw, rotating `prod.log` files; tailing and grepping is slow and unreadable, and rotation can quietly take the answer away before they get to it. The same person, after shipping a feature, wants a quick confidence signal that the new behavior is healthy in prod — they don't have one today, so they live with a low-grade "is this actually working?" decision paralysis.

The insight is Symfony-bundle ergonomics. Generic free log tools (Sentry free tier, hosted ELK trials, Logtail, etc.) exist but their Symfony integration path is just "use our generic SDK". Symfony developers expect `composer require <vendor>/<bundle>` plus a handful of YAML lines to make a thing Just Work — the way Doctrine, Twig, and Messenger already do. No log/monitoring tool currently targets that exact shape: a free, opinionated Symfony-bundle install that turns into readable, filterable logs in a browser with effectively zero configuration.

## User & Persona

**Primary persona — Solo Symfony Developer**

- **Role**: a single developer (or a tiny team operating in solo-mode per project) building and shipping Symfony apps as side-projects or small client work.
- **Context**: self-deploys to a VPS or small managed host; owns the server; no dedicated ops/SRE; cost-sensitive (paid Sentry/Datadog tiers are not in the budget for a side-project); already fluent with the Symfony bundle install ritual.
- **Moment of reach**: opens this product (a) on a Saturday when something is paging or users are reporting 500s, to diagnose fast; or (b) the minute after a deploy, to confirm the feature is healthy in prod.

## Success Criteria

### Primary

- Logs emitted by a Symfony app in production reach Monitoring Webowi and become visible in the web UI, and the developer can filter the visible list by HTTP error code (e.g. show only 500s).

### Secondary

- Post-deploy confidence signal: logs produced by a fresh deploy appear in the UI within seconds of being emitted, so the developer can tell whether the new feature is healthy in prod within their normal post-deploy window.

### Guardrails

- Project isolation: a developer can only see logs belonging to projects they own; the ingestion API rejects any payload whose key does not match the asserted project. Cross-project log visibility is a fatal regression even if Primary holds.
- Host-app safety: the Symfony app being monitored must not crash, hang, or visibly slow down when the monitoring service is unreachable, slow, or returning errors. Making a user's app *less* reliable than it was before installing the monitoring snippet is a fatal regression.

## User Stories

### US-01: Developer confirms a Symfony app's prod errors via Monitoring Webowi

- **Given** a pre-seeded developer account with one project and one API key
- **And** the developer has pasted the Monolog HTTP-handler snippet into their Symfony app's `monolog.yaml` using that API key and deployed
- **And** the Symfony app is throwing 500-class errors in production
- **When** the developer signs in to Monitoring Webowi and opens the project page
- **Then** they see the project's recent log entries in the UI, most-recent first
- **And when** they apply the HTTP-code filter for `500`
- **Then** the list reduces to only the 500-class entries from their app

#### Acceptance Criteria
- A log emitted by the Symfony app appears in the UI within a few seconds.
- Logs from other projects (real or test) never appear in this developer's list.
- An empty result is shown as an explanatory empty-state, not a 0-row table.

## Functional Requirements

### Authentication & access

- FR-001: Developer can sign in to the web UI with email and password. Priority: must-have
  > Socratic: Counter considered: replace email/password with magic-link to skip password storage + forgotten-password flow, OR drop sign-in entirely since MVP pre-seeds a single user. Resolution: kept. The seed explicitly names a "simple user account system"; password-based auth survives v2's public-sign-up addition without rework; and the sign-in boundary is load-bearing on day one — anyone with the URL would otherwise see project logs.

- FR-002: The ingestion API accepts a log payload only when the caller presents a valid API key bound to the asserted project; otherwise it rejects the payload. Priority: must-have
  > Socratic: Counter considered: API keys embedded in `monolog.yaml` are a known leak vector (committed configs, public repos), and a single-project MVP could use a hardcoded shared secret. Resolution: kept. Per-project keys are the industry-standard pattern (Sentry DSN, Bugsnag), would have to be built for v2 anyway, and avoid a throwaway-then-rebuild path. Leak-mitigation hardening (IP allowlist, key rotation) is acknowledged as v2 work.

- FR-003: An authenticated developer can only see and act on projects they own; an unauthenticated visitor cannot see any project data. Priority: must-have
  > Socratic: Counter considered: the original wording prescribed a UX mechanism ("redirect to sign-in") inside an FR. Resolution: revised. The FR now states the access rule only; whether the unauthenticated case is handled with a redirect, a 401, or a landing page is a downstream UX decision, not part of the rule.

### Project & key (pre-seeded in MVP; UI is read-only)

- FR-004: Developer can view their project's API key and the Monolog-snippet install instructions on a project page; the API key is masked by default and revealed only on explicit click, with a copy-to-clipboard control so the developer need not read the key on screen. Priority: must-have
  > Socratic: Counter considered: displaying an API key on a routinely-visited page risks shoulder-surf and screenshot leakage. Resolution: revised. The key is masked by default, revealed on demand, and copy-on-click — the FR now binds these behaviors rather than just "view".

### Ingestion (Symfony app → service)

- FR-005: A Symfony app, configured with the Monolog HTTP-handler snippet, sends its log records to the ingestion endpoint. Priority: must-have
  > Socratic: Counter considered: pinning to Monolog excludes Symfony apps that wire logging differently, and Monolog's HTTP handler is synchronous by default. Resolution: kept. Monolog is the de-facto Symfony logger; targeting it directly is the most pragmatic v1 integration. The performance concern is addressed by FR-006 (the snippet must be designed to fail open and not block the host request) rather than by a separate FR.

- FR-006: The Symfony app does not crash, hang, or visibly slow when the ingestion endpoint is unreachable, slow, or returning errors — log delivery fails open from the host app's perspective. Priority: must-have
  > Socratic: Counter considered: silent failure is dangerous — a developer may assume logs are flowing when they are not. Resolution: kept, and paired with FR-009 below (a project-page freshness / health indicator) so silent ingestion failure is visible to the developer without breaking the host app.

### Log browsing & filtering

- FR-007: Developer can view a list of recent log entries for their project, ordered most-recent first. Priority: must-have
  > Socratic: Counter considered: during a 500-spike the dev wants to group by error pattern (exception class, message template, URL), not see a flat stream. Resolution: kept as a flat reverse-chronological list for MVP; grouping is deferred to v2 and captured in Open Questions. The v2 grouping work informs the data-shape choice in v1 but does not block MVP delivery.

- FR-008: Developer can filter the visible log list by Monolog severity level (DEBUG / INFO / NOTICE / WARNING / ERROR / CRITICAL / ALERT / EMERGENCY) and, when present in the log's context, by HTTP status code (e.g. show only entries whose status code is 500). Priority: must-have
  > Socratic: Counter considered: HTTP status code alone is web-only and misses CLI / Messenger / console-command / cron errors that are common in Symfony apps. Resolution: revised. The FR now ships both filter axes; severity-level covers non-HTTP execution paths, and HTTP code remains available for the seed's literal "filter po kodach błędu" use case.

- FR-009: Developer can see, on each project page, when the most recent log entry was received (e.g., "Last log received: 12 seconds ago", or "No logs received in the last 24 hours") so that silent ingestion failure is detectable without active alerting. Priority: must-have
  > Socratic: Counter considered: should the project page also actively alert (email / push) when a healthy project goes silent for N minutes? Resolution: passive indicator only in MVP. Active alerting (email/SMS) is explicitly out of MVP per the seed (`Komunikaty mailowe, sms w przypadku błędów krytycznych` listed under "Co NIE wchodzi w zakres MVP"). Note: an earlier draft of this number described a freshness latency target; that requirement has been reclassified as a Non-Functional Requirement.

## Non-Functional Requirements

- **Freshness at the boundary.** A log event accepted by ingestion becomes visible on the project page within p95 ≤ 5 seconds and p99 ≤ 15 seconds, under nominal load.
- **Project isolation.** No log event, project metadata, or API key belonging to one account is ever visible to a request authenticated as a different account; the ingestion endpoint rejects any payload whose presented key does not match the asserted project. Cross-tenant exposure is a binary failure.
- **Host-app safety.** A monitoring-service outage, slowdown, or error response does not measurably degrade the host Symfony app's request-handling latency, and does not produce uncaught exceptions in the host application's request cycle.
- **Retention window.** Log events are kept for a 7-day rolling window from ingestion. Beyond that window, events are no longer queryable.
- **Sign-in resistance.** A user who mistypes their password a handful of times in a row is not locked out; credential-stuffing at scale is rejected before reaching credential verification.
- **UI responsiveness.** When the developer changes filter selection on the project page, the visible list updates within 200 ms (at MVP volumes — single project, 7 days of events).
- **Browser support.** The web UI remains usable on the latest two major versions of the four mainstream desktop browsers (Chrome, Firefox, Safari, Edge).
- **Accessibility.** The log list view (the product's primary surface) meets WCAG 2.1 AA for keyboard navigation, contrast, and focus visibility.

## Business Logic

Monitoring Webowi takes the heterogeneous stream of log events a Symfony app emits and projects each event onto a single uniform, filterable, freshness-tracked surface, so the developer can answer questions about recent app behavior faster than by reading the raw log file.

**Inputs.** What the product consumes is one stream per project: log events as the developer's Symfony app produces them. Each event carries a timestamp, a severity level (DEBUG through EMERGENCY), a message, and a bag of free-form context — which may or may not include an HTTP status code, the URL being handled, an exception class and message, a stack trace, or arbitrary keys the developer attached. The shape of context varies record to record because Symfony apps generate logs from many places (controllers, Messenger workers, console commands, cron jobs, framework internals); the product treats this variability as a property of the input, not an error.

**Output.** What the product produces is a normalized view: for each ingested event the developer sees a row with predictable columns — *when* (timestamp), *how serious* (severity), *what* (message), *where in the app* (HTTP code, source kind, exception class — each derived from context when present, absent otherwise) — and the same row exposes the original context for drill-in. Across rows, the product also exposes one summary property: how recently *anything* arrived for this project, so that "no logs" is distinguishable from "the link is broken".

**How the user encounters it.** The developer opens a project page. They see the latest events in reverse-chronological order, each event rendered in the normalized shape. They narrow the list with the filter axes the product surfaces (severity level, and HTTP status code when present), and they read the freshness indicator to confirm the stream is alive. The product never adds or invents events; it only projects what was sent, in a shape the developer can scan.

## Access Control

The product has two distinct access boundaries.

**Symfony app → monitoring service (machine boundary).** Each project (one project = one Symfony app being monitored) has its own API key, generated in the web UI and pasted into the Symfony bundle's configuration. The monitoring service rejects any incoming log payload whose key is unknown, revoked, or not bound to the asserted project. Compromise of one project's key affects only that project's data ingestion.

**Developer → web UI (human boundary).** A user signs up and signs in with email and a password. An authenticated user may only see and act on projects they own; an unauthenticated visitor cannot see any project metadata. (The exact handling of an unauthenticated request — redirect to sign-in, 401 response, marketing landing — is a downstream UX decision, not part of the access rule.)

**User & project model — flat.** A user account owns one or more projects. There are no admin / member / viewer roles in the MVP. Sharing a project with a second human is explicitly out of scope for MVP (a flat, single-owner-per-project model is the smallest model that still supports the "monitor multiple side-projects" case).

## Non-Goals

### Functional non-goals (capabilities the MVP will NOT provide)

- **No active alerting.** Email, SMS, or push notifications when errors occur are out of MVP scope (explicit in the seed under `Co NIE wchodzi w zakres MVP`). MVP ships only the passive freshness indicator from FR-009.
- **No charts, graphs, dashboards, or visualizations of error frequency / trend / distribution.** Explicit in the seed; MVP ships a filterable list, not analytics.
- **No support for non-Symfony frameworks or languages.** No Laravel, Express, Django, generic HTTP-SDK, or other integration paths in MVP. The Symfony-bundle ergonomics insight is framework-specific by design; broadening it is what v3+ is for, not v1.
- **No full-text search across log message bodies.** MVP filters by structured axes only (severity level, HTTP code). Finding "the request that said X" by free-text is deferred to v2.
- **No log grouping / clustering by error pattern.** Already deferred via FR-007 Socratic round (see Open Questions); explicitly locked out of MVP here so it does not creep back in.
- **No project sharing with a second user, team workspaces, or per-project role matrices.** The flat single-owner-per-project access model is binding for MVP.
- **No public sign-up flow / no project CRUD UI in MVP.** The single user account, single project, and single API key are pre-seeded; the sign-up flow and the create-project / regenerate-key UI move to v2 (this scope cut keeps MVP inside the 3-week timeline).
- **No packaged Symfony bundle in MVP.** v1 ships a paste-in Monolog HTTP-handler snippet rather than a `composer require`-able bundle; publishing the bundle moves to v2.

### Non-functional non-goals (quality dimensions the MVP will NOT aim for)

- **No log retention beyond 7 days; no cold-storage archive.** The 7-day rolling retention NFR is a ceiling, not a floor — MVP makes no commitment for long-term archive, export, or cold storage.
- **No multi-region SLA, no formal compliance certification (SOC 2 / ISO 27001).** MVP commits to baseline privacy hygiene (project isolation, no cross-tenant exposure) but explicitly does not claim enterprise compliance posture.
- **No defined ingestion throughput SLA for stressed/spiking load.** Freshness NFR is for *nominal* load only. MVP behavior under abnormal ingestion rates (misconfigured app, runaway loop) is undefined; the cap and back-pressure design are deferred (see Open Question on per-project event-volume cap).

## Open Questions

1. **Grouping in v2.** Deferred from FR-007 Socratic round. Grouping the log list by error pattern (exception class, message template, URL pattern) is useful during a 500-spike but is not in MVP. The v1 data shape should be chosen so v2 grouping is mechanical rather than a rewrite. Owner: user. Resolution timing: during v2 planning.
2. **API-key leak mitigation in v2.** Deferred from FR-002 Socratic round. Once public sign-up exists and many projects ship keys to public-ish repos, the per-project key needs either IP allowlist, short-lived tokens, or rotation. Out of MVP. Owner: user.
3. **Bundle vs. snippet in v2.** Deferred from MVP scope-down. MVP ships a paste-in Monolog snippet; v2 should re-evaluate whether to publish a real Symfony bundle. Owner: user.
4. **Per-project event volume cap.** No upper bound is currently set on events/sec or events/day per project. If a misconfigured Symfony app suddenly ships logs at very high rates, ingestion behavior under load is undefined. Owner: user. Resolution timing: before tech-stack selection hardens storage choice.
5. **Product type hybrid.** Selected `product_type: web-app`, but the same deployed product also exposes a machine-facing ingestion HTTP endpoint that the Symfony snippet/bundle calls. Treated at PRD level as one product with two surfaces (human web UI + machine ingestion route), not two separate products. Owner: user. Resolution timing: confirm during tech-stack selection when picking the request-handling surface.
