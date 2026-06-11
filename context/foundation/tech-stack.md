---
starter_id: symfony
package_manager: composer
project_name: monitoring-webowi
hints:
  language_family: php
  team_size: solo
  deployment_target: self-host
  ci_provider: github-actions
  ci_default_flow: auto-deploy-on-merge
  bootstrapper_confidence: best-effort
  path_taken: custom
  quality_override: false
  self_check_answers:
    typed: true
    from_official_starter: false
    conventions: false
    docs_current: false
    can_judge_agent: true
  has_auth: true
  has_payments: false
  has_realtime: true
  has_background_jobs: true
  has_ai: false
---

## Why this stack

Solo PHP developer building a Symfony-native log-monitoring service. Custom path selected because the PRD is built entirely around Symfony-bundle ergonomics and Monolog integration — the product's core insight is the "composer require a bundle and it Just Works" install story, which presupposes Symfony as the runtime. Laravel (the registry's PHP default) was explicitly excluded by the user. Auth ships via Symfony Security (email + password) plus per-project API keys (FR-001, FR-002); background jobs via Symfony Messenger handle async log ingestion so the host app fails open per FR-006; MySQL + Doctrine ORM handle persistence with a 7-day retention window. Realtime flag is set because FR-009 requires a freshness indicator with seconds-level latency, implemented as short-poll rather than full WebSockets. Deployment targets a self-host VPS, matching the PRD persona of a solo developer on a small managed server. Three self-check items were unconfirmed (official starter base, conventions, docs currency) — AGENTS.md should anchor the agent to Symfony's canonical layout and the installed package versions explicitly.
