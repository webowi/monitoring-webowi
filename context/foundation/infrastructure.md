---
project: monitoring-webowi
researched_at: 2026-06-05
recommended_platform: Hetzner VPS + Coolify
runner_up: Railway
context_type: mvp
tech_stack:
  language: PHP 8.2+
  framework: Symfony 7.x
  runtime: PHP-FPM / FrankenPHP (containerised)
  database: MySQL 8.x (Doctrine ORM)
  workers: Symfony Messenger (persistent background process)
---

## Recommendation

**Deploy on Hetzner Cloud VPS (CX33) managed by Coolify.**

The project's own `tech-stack.md` declares `deployment_target: self-host`, the developer already uses Docker in the project, and the PRD's persona ("solo developer on a small managed server") maps directly to a self-hosted VPS. Hetzner CX33 (4 vCPU, 8 GB RAM, 80 GB SSD, EU data centre) at €6.49/month is the cheapest EU option with headroom for the full stack (PHP app + MySQL + Messenger worker + Coolify overhead). Coolify provides a managed deployment layer over Docker Compose — GitHub auto-deploy on push, automatic TLS via Traefik, co-located MySQL, and first-class multi-service Docker Compose support for running Messenger workers as a persistent separate container. The preference for co-location (interview Q5) is fully met: app, MySQL, and worker all run on the same server under Coolify's internal Docker network.

---

## Platform Comparison

Full scoring matrix (Pass = 2, Partial = 1, Fail = 0; weights per criteria doc).

| Platform | CLI-first (×3) | Managed (×3) | Agent docs (×2) | Stable deploy (×3) | MCP (×1) | **Weighted** |
|---|---|---|---|---|---|---|
| Hetzner+Coolify | Partial (3) | Partial (3) | Pass (4) | Partial (3) | Fail (0) | **13/24** |
| Railway | Pass (6) | Pass (6) | Partial (2) | Pass (6) | Partial (1) | **21/24** |
| Render | Pass (6) | Pass (6) | Pass (4) | Pass (6) | Pass (2) | **24/24** |
| Fly.io | Pass (6) | Pass (6) | Partial (2) | Pass (6) | Partial (1) | **21/24** |
| cyberfolks.pl (shared) | — DROPPED — | | | | | |

**Hard filter applied:** cyberfolks.pl *shared* hosting dropped before scoring — cannot run persistent `messenger:consume` worker processes, which the PRD's p95 ≤ 5s freshness NFR requires. Note: cyberfolks.pl *VPS* plans (vroot series) would not hit this blocker, but cost more (~€11/mo) than Hetzner for equivalent resources.

**Why Hetzner+Coolify despite a lower criteria score:** The scoring criteria measure agent-friendliness of the deployment platform itself. The user explicitly rejected managed-PaaS options (wants one server, uses Docker) — a platform-fitness dimension the five criteria don't directly capture. The lower agent-friendliness score reflects the absence of a CLI and MCP server; the mitigations (webhook API + git-push auto-deploy) are sufficient for a solo-developer workflow. Railway and Render remain valid if the user later moves to a managed PaaS.

---

### Shortlisted Platforms

#### 1. Hetzner VPS + Coolify (Recommended)

Single-server VPS at €6.49/month (CX33) with Coolify providing Docker Compose multi-service deployments, automatic TLS via Traefik, co-located MySQL, and GitHub auto-deploy on git push. PHP supported via Nixpacks (auto-detects from `composer.json`) or Dockerfile. No CLI — deployments are triggered by git push or a webhook `curl`. Agent-readable docs via `coolify.io/docs/llms.txt` and `llms-full.txt`. No official MCP server. Best fit for the declared `deployment_target: self-host` and the developer's Docker familiarity.

#### 2. Railway

Managed PaaS with PHP auto-detection via Railpack (no Dockerfile needed), MySQL as a template service co-located in the same project, and dedicated worker service support for `messenger:consume`. Full CLI (`railway up`, `railway logs`), official MCP server (self-described "work in progress"). Cost ~$5–8/month on Hobby tier. Dropped to runner-up because the user prefers a single VPS over PaaS; would be the top choice if the PaaS model were acceptable.

#### 3. Render

Highest agent-friendly criteria score: `llms.txt` + `llms-full.txt`, GA hosted MCP server (read/observe only), persistent Background Workers, and the cleanest rollback support. No managed MySQL (self-hosted private service + persistent disk adds ~$12/mo); total MVP cost ~$26/month. Dropped to third because of cost and MySQL setup complexity relative to Railway.

---

## Anti-Bias Cross-Check: Hetzner VPS + Coolify

### Devil's Advocate — Weaknesses

1. **No platform CLI — deploys via webhook only.** There is no `coolify deploy` command. An agent deploys by POSTing to a webhook URL, then must poll the Coolify REST API or parse logs to confirm success. Functional but less ergonomic than typed CLI exit codes (`railway up`, `fly deploy`).

2. **You own the VPS — OS uptime is your responsibility.** Coolify abstracts app deployments but the underlying Hetzner VPS needs OS updates, SSH key management, and disk management. A kernel update requiring a reboot, or a full disk from unrotated Docker logs, brings down all services simultaneously. No platform safety net.

3. **Coolify itself consumes ~400–700 MB RAM.** Coolify runs its own Docker containers (Laravel, queue, scheduler, Traefik, Soketi). On a CX23 (4 GB), that's tight alongside PHP + MySQL + Messenger worker. CX33 (8 GB, €6.49/mo) is the realistic minimum — budget for CX33, not CX23.

4. **Custom `networks:` block in Docker Compose causes intermittent Traefik 504s.** Coolify manages its own Docker network. Declaring a custom `networks:` block in your `docker-compose.yml` makes Traefik non-deterministically route between two IPs. Not documented prominently; avoid all custom network declarations.

5. **No MCP server.** Coolify has no official MCP server as of 2026-06-05. Agents must parse the Coolify REST API manually — no structured tool calls for deployment status, health checks, or log queries.

### Pre-Mortem — How This Could Fail

The team chose Hetzner+Coolify for full control and the lowest cost. The GitHub integration auto-deployed on push; Messenger workers ran via Docker Compose multi-service. Everything worked for three months.

Then an unattended Hetzner VPS reboot (Hetzner notified by email; not surfaced in Coolify) triggered a startup race: MySQL hadn't fully initialized before the Messenger worker tried to connect, so the worker crashed on startup. The `depends_on` restart policy was set to `on-failure` with 3 attempts — after 3 crashes it stopped retrying silently. MySQL was healthy; the ingestion worker was dead. Nobody noticed for two weeks because the monitoring service monitors *other* apps, not itself.

Diagnosing required SSHing into the VPS, manually reading `docker logs`, comparing restart policies, and restarting the container by hand — none of this was automatable via Coolify's webhook API. Three hours to diagnose, 20 minutes to fix.

Six months later: the 80 GB Hetzner volume hit 95% capacity from Docker log files that had never been configured to rotate. MySQL could not write new data; ingestion silently failed again. VPS operations had been assumed, not planned for.

### Unknown Unknowns

- **Docker log rotation is not configured by default.** Docker writes container logs to unbounded JSON files on disk. A verbose PHP app + MySQL + worker can fill the 80 GB VPS disk within weeks. Add a `logging.options.max-size` / `max-file` config to every container in Docker Compose, or configure Docker's daemon-level log rotation in `/etc/docker/daemon.json`.
- **Coolify auto-update can briefly interrupt running containers.** Coolify's self-update mechanism is automatic and runs in the background. Updates that modify Traefik config or Docker network settings can briefly interrupt running services. Monitor the Coolify changelog before allowing unattended auto-updates in a production environment.
- **Symfony cache warming must happen at build time, not at runtime.** If the DI container and Twig template cache are not compiled during the Nixpacks/Docker build step, the first request after every deploy hits a cold container. Confirm that `php bin/console cache:warmup --env=prod` runs as part of the Dockerfile `RUN` layer or Nixpacks post-install hook, not as a runtime entrypoint command.
- **Worker MySQL startup race on every VPS reboot.** PHP Messenger workers attempt a MySQL connection at startup. If MySQL is still initializing, the worker crashes. Fix: add a Docker Compose `healthcheck` to the MySQL service and set the worker service to `depends_on: db: condition: service_healthy`.

---

## Operational Story

- **Preview deploys**: Not natively supported by Coolify in the same way as PaaS platforms. The practical pattern is a separate Coolify application resource pointing to a `staging` branch — git push to `staging` triggers auto-deploy to a staging URL. No branch-preview URLs generated automatically per PR.
- **Secrets**: Environment variables stored in Coolify's application settings UI (per-application, per-environment). They are injected into containers at runtime and are not visible in `docker-compose.yml` or git. Rotation: update in Coolify settings → redeploy. Tokens must not be placed in `.env` files committed to the repo.
- **Rollback**: Navigate to Coolify UI → Application → Deployments → select past deployment → Redeploy. Typical time: ~2 minutes (pulls the previously built Docker image). DB migrations do not roll back automatically — maintain backward-compatible migrations or run a down-migration manually before reverting the app.
- **Approval**: Destructive actions (drop database, delete application resource, delete VPS) are human-only via Coolify UI or Hetzner Cloud console. Deployments and restarts can be triggered by an agent via webhook + Bearer token. Do not give agents the Hetzner Cloud API token with delete permissions.
- **Logs**: `ssh user@vps-ip docker logs <container-name> --tail 100 --follow` for direct container logs. Coolify UI → Application → Logs for web-based view. No structured log query API in Coolify as of 2026-06-05.

---

## Risk Register

| Risk | Source | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| Messenger worker silently stops after 10 crash restarts | Devil's advocate | M | H | Set `restart: unless-stopped` in Docker Compose (not `on-failure`). Add an external uptime monitor (e.g., UptimeRobot free tier) pinging an `/health` endpoint that checks Messenger queue depth. |
| MySQL startup race after VPS reboot | Unknown unknowns | M | H | Add `healthcheck` to the MySQL Compose service; set worker to `depends_on: db: condition: service_healthy`. |
| Disk full from unrotated Docker logs | Unknown unknowns | H | H | Add `logging.options: max-size: "50m" max-file: "3"` to every service in `docker-compose.yml` on day one. |
| No auto-backup for MySQL — full data loss on volume corruption | Devil's advocate | L | H | Schedule a nightly `mysqldump` cron inside the MySQL container piped to Hetzner Volume Snapshots or Backblaze B2. |
| Coolify auto-update disrupts running containers | Unknown unknowns | M | M | Disable automatic Coolify self-updates in production; apply manually during a low-traffic window after reading the changelog. |
| Custom Docker Compose `networks:` causes Traefik 504s | Devil's advocate | M | H | Never declare `networks:` in the project `docker-compose.yml`. Use Coolify's auto-managed network for inter-service communication. |
| Symfony DI cache not warmed before first request post-deploy | Unknown unknowns | M | M | Run `php bin/console cache:warmup --env=prod` in the Dockerfile `RUN` step, not as a runtime command. |
| No CLI rollback for agents | Devil's advocate | M | M | Document the Coolify API rollback endpoint (`POST /api/v1/deploy`) as the agent's rollback path; test it in staging before production. |

---

## Getting Started

These steps assume a fresh Hetzner CX33 server with Ubuntu 24.04.

1. **Provision the VPS.**
   - Create a Hetzner Cloud account at cloud.hetzner.com, create a project, add your SSH key, and launch a CX33 in the `nbg1` or `fsn1` data centre (Germany, closest to Poland).
   - Firewall: allow ports 22 (SSH), 80 (HTTP redirect), 443 (HTTPS).

2. **Install Coolify.**
   ```bash
   curl -fsSL https://cdn.coollabs.io/coolify/install.sh | bash
   ```
   Access the Coolify UI at `http://<vps-ip>:8000` to create the admin account.

3. **Connect your GitHub repository.**
   In Coolify: Settings → Source → Add GitHub App → follow the OAuth flow. This enables auto-deploy on git push without storing a deploy key manually.

4. **Create the application resource.**
   New Resource → Application → select your repo → select the `main` branch → set Build Pack to Nixpacks → set `NIXPACKS_PHP_ROOT_DIR=/public` and `APP_ENV=prod` as environment variables.

5. **Add a MySQL database service.**
   New Resource → Database → MySQL 8.0 → set the database name, user, and password → Coolify injects `DATABASE_URL` automatically. Set `depends_on` in your `docker-compose.yml` with a `healthcheck` (see Risk Register).

6. **Add the Messenger worker as a second service.**
   In your `docker-compose.yml`:
   ```yaml
   worker:
     build: .
     command: php bin/console messenger:consume async --time-limit=3600 --memory-limit=128M
     restart: unless-stopped
     depends_on:
       db:
         condition: service_healthy
   ```
   Add `logging.options: max-size: "50m" max-file: "3"` to every service.

---

## Out of Scope

The following were not evaluated in this research:
- Docker image configuration and Dockerfile authoring
- CI/CD pipeline setup (GitHub Actions test + lint gates)
- Production-scale architecture (multi-region, HA, DR)
- Email/SMS alerting infrastructure
- Symfony bundle packaging (v2 scope per PRD)
