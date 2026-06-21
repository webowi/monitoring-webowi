# Deploy Plan — monitoring-webowi → OVHcloud VPS + Coolify

## Context

`context/foundation/infrastructure.md` already picked the platform: an OVHcloud VPS managed by Coolify, chosen because the project's `tech-stack.md` declares `deployment_target: self-host`, the dev already works in Docker, and the PRD persona is "solo developer on a small managed server". This plan turns that recommendation into an executable, checkbox-tracked sequence: what the agent can do unattended, what requires a human at a keyboard (account creation, secret entry, DNS), the exact commands/webhooks for deploy, and how to verify the result. This file is the audit trail milestone-planning skills consume later (per this project's `context/changes/` convention).

**Why this isn't a trivial "git push and done":** the current repo is dev-shaped, not prod-shaped. Three gaps from `infrastructure.md`'s own risk register are *not yet closed* in code:
- No `messenger.yaml` exists at all — Messenger is in `composer.json` but unconfigured (confirmed via `grep -rn messenger config/`). The PRD's async-ingestion requirement (FR-006, fail-open) and the freshness NFR depend on a worker that doesn't exist yet.
- No `/health` endpoint exists (`config/routes.yaml` only loads Identity/Projects/Translator controllers).
- `docker-compose.yml` is dev-only: bind-mounts (`./:/var/www/html`), a custom `networks:` block (flagged as a Traefik-504 cause in `infrastructure.md:67`), no healthchecks, no log-rotation limits, debug ports, xdebug — none of which belong in the Coolify-managed prod container.

**In-flight refactor caveat:** `git status` shows ~95 staged + 47 unstaged files mid-rewrite (Identity → Organization/User restructuring, Dashboard module deletion, JWT/Lexik auth swap replacing the old session-based login, new Project/IngestionKey entities). None of this changes the *infrastructure* plan, but Phase 1 code changes should land **after** this refactor is committed and green — editing `config/routes.yaml`, `security.yaml`, or Doctrine mappings mid-rewrite would create merge friction. Phase 0 below gates on this.

---

## Phase 0 — Pre-flight gate (confirm ready to build on)
- [ ] Confirm the in-flight Identity/Organization/JWT refactor is committed to `main` and CI (`.github/workflows/ci.yml`) is green (PHPUnit + GrumPHP incl. phpstan level 10 + infection 100% MSI).
- [ ] Confirm `config/routes.yaml` reflects the final route prefixes (`/api/v1/...`) — the new `/health` route in Phase 1 needs to slot into the stable layout.
- [ ] Decide the production domain name (needed for Coolify app config, CORS, Traefik TLS, JWT/cookie settings). *Ask the user if not already chosen.*

## Phase 1 — Close the production-readiness gaps (agent-owned code changes)

These are the concrete blockers `infrastructure.md`'s risk register and unknown-unknowns sections call out by name — closing them now means the first deploy doesn't immediately hit the pre-mortem's "worker crashes silently, nobody notices for two weeks" scenario.

- [ ] **Add `config/packages/messenger.yaml`** with a `doctrine://default` transport for the `async` bus. Reuse the existing MySQL connection (no Redis service needed — keeps the co-located, single-VPS story `infrastructure.md` recommends, and avoids adding a moving part Coolify has to babysit). Run `bin/console messenger:setup-transports` once to create the `messenger_messages` table via migration.
- [ ] **Add a `/health` controller** (e.g. `src/Kernel/Ui/HealthController.php`, routed at `/health` outside the `/api/v1` JWT-protected prefix). It should check: (a) DB connectivity (`Connection::executeQuery('SELECT 1')`), (b) Messenger queue depth via the `doctrine` transport's message count, returning 503 if the queue is growing unbounded (the "worker silently died" signal from the risk register). This is what an external uptime monitor pings — the pre-mortem scenario explicitly says "the monitoring service monitors *other* apps, not itself."
- [ ] **Create `docker-compose.prod.yml`** (separate from the dev-focused `docker-compose.yml` — do not edit that one, CI depends on it). Differences from dev:
  - No bind mounts — build the image from `docker/etc/php/main.Dockerfile` so the container is immutable and reproducible.
  - **No custom `networks:` block** (per `infrastructure.md:67` — Coolify's auto-managed network must be the only one, or Traefik 504s intermittently).
  - `healthcheck` on the `mysql` service (`mysqladmin ping`) — Phase 1's race-condition fix.
  - `worker` service: `command: php bin/console messenger:consume async --time-limit=3600 --memory-limit=128M`, `restart: unless-stopped` (NOT `on-failure` — the pre-mortem's silent-death mode comes from `on-failure` giving up after 3 attempts), `depends_on: db: condition: service_healthy`.
  - `logging: options: max-size: "50m", max-file: "3"` on every service (php, nginx, mysql, worker) — closes the "80GB disk fills with unrotated logs" risk.
  - Named volumes for `public/uploads` (Vich Uploader writes here — `config/packages/vich_uploader.yaml` maps `avatars`/`logos` to `public/uploads/images/...`; without a volume, every redeploy wipes user-uploaded files) and for `config/jwt` (see Phase 1 JWT item below).
- [ ] **Add cache warmup to the Dockerfile build layer**: `RUN php bin/console cache:warmup --env=prod` before the final `COPY`/entrypoint, so the first prod request isn't a cold-container hit (per `infrastructure.md:85`/`110`).
- [ ] **Wire JWT keypair generation into first-boot**: `lexik/jwt-authentication-bundle` needs `config/jwt/{private,public}.pem` (currently gitignored, generated locally via `lexik:jwt:generate-keypair`). Add a startup check (in an entrypoint script or the worker/php container's `command`) that runs `bin/console lexik:jwt:generate-keypair --skip-if-exists` against the `config/jwt` named volume — so keys persist across redeploys and are never committed.
- [ ] **Trim `docker/etc/nginx/conf.d/default.conf`** for prod: it currently has dev comments about `fastcgi_param APP_ENV prod` etc. as commented-out hints — leave `fastcgi_pass` pointing at the Coolify-managed service name (Coolify rewrites this via its network, but verify the `php` service name matches what's in `docker-compose.prod.yml`).

## Phase 2 — Manual infrastructure setup (human-only gates)

Per `infrastructure.md`'s "Approval" operational story: account creation, billing, and anything that could destroy data is a human-click action, not an agent action.

- [ ] **OVHcloud**: create account at OVHcloud, provision a VPS (sized comparably to a Hetzner CX33 — 4 vCPU / 8GB RAM tier, e.g. "VPS Comfort" or higher) in a EU datacenter (`GRA` Gravelines or `SBG` Strasbourg, closest to users), add an SSH key during provisioning. Configure firewall (OVHcloud Network Security Group or `ufw` on the VPS): allow 22 (SSH), 80, 443 only.
- [ ] **DNS**: point the chosen production domain's A record at the new VPS IP (needed before Traefik can issue a Let's Encrypt cert).
- [ ] **Coolify install**: SSH in, run `curl -fsSL https://cdn.coollabs.io/coolify/install.sh | bash`, create the admin account at `http://<vps-ip>:8000`.
- [ ] **⚠️ Disable Coolify auto-updates** immediately after setup (Settings → Updates) — `infrastructure.md:84`/`108` flags self-updates as a mid-deploy disruption risk; apply updates manually after reading the changelog.
- [ ] **Connect GitHub**: Coolify → Settings → Source → add GitHub App via OAuth (enables auto-deploy without a manually-managed deploy key).
- [ ] **Rotate every secret before first deploy** — none of the current `.env`/`.env.dist` values are production-safe:
  - `APP_SECRET` (currently a throwaway dev value)
  - `JWT_PASSPHRASE` and the keypair itself (Phase 1 generates fresh keys on the VPS — do not copy the local gitignored `config/jwt/*.pem`)
  - `DB_USER`/`DB_PASS`/`MYSQL_ROOT_PASSWORD` (currently `user`/`password`)
  - `GUS_API_KEY` (see external-integration notes below — confirm prod vs sandbox key)
  - `EMAIL_LAB_SMTP` / `EMAIL_LAB_APP_KEY` / `EMAIL_LAB_APP_SECRET` (Brevo/Sendinblue — see notes below)
  - `CORS_ALLOW_ORIGIN` — must change from the `localhost` regex to the real frontend origin(s)

## Phase 3 — Coolify resource configuration

- [ ] **New Resource → Database → MySQL 8.0**: set DB name/user/password (the rotated values from Phase 2). Coolify injects `DATABASE_URL` — confirm its format matches what `config/packages/doctrine.yaml` expects (`mysql://...?serverVersion=8.0`); adapt `DATABASE_URL` assembly in `.env`/Coolify env vars if Coolify's generated URL differs.
- [ ] **New Resource → Application**: point at the GitHub repo, `main` branch, **Docker Compose** build pack pointing at `docker-compose.prod.yml` (not Nixpacks — the project already has a working custom Dockerfile with SOAP/GD/intl extensions GUS API and image uploads need; Nixpacks would have to rediscover all of that).
- [ ] **Set environment variables** in Coolify's per-application secrets UI (never in `.env` committed to git): `APP_ENV=prod`, `APP_SECRET`, `DATABASE_URL` (or `DB_HOST`/`DB_NAME`/`DB_USER`/`DB_PASS` per the existing `.env.dist` assembly pattern), `MAILER_DSN`, `CORS_ALLOW_ORIGIN`, `GUS_API_KEY`, `JWT_PASSPHRASE`, `CT_NAME`, `CT_EMAIL`, `CACHE_TTL`, `LOCK_DSN`.
- [ ] **Verify `docker-compose.prod.yml` services land correctly**: `php`, `nginx`, `worker`, `db` all show as separate Coolify-tracked containers; confirm the worker shows `restart: unless-stopped` in the running config (Coolify can silently coerce restart policies — check the rendered compose in the UI).
- [ ] **Enable Traefik TLS**: confirm the domain from Phase 2 is attached to the application resource and Let's Encrypt issues a cert (Coolify automates this, but DNS must already resolve).

## Phase 4 — CI/CD: wire auto-deploy

- [ ] In Coolify, generate a deploy webhook URL + Bearer token for the application resource.
- [ ] Add a `deploy` job to `.github/workflows/ci.yml` (after the existing `build` job passes, gated on `github.ref == 'refs/heads/main'`): `curl -X POST -H "Authorization: Bearer ${{ secrets.COOLIFY_WEBHOOK_TOKEN }}" <coolify-webhook-url>`. Store the token as a GitHub Actions secret — never inline.
- [ ] This closes the gap noted in exploration: CI currently only validates (build/test/lint), it never deploys.

## Phase 5 — First deploy + verification

- [ ] Trigger the first deploy (push to `main`, or manually via Coolify UI "Deploy").
- [ ] Watch the build log in Coolify UI for: image build success, `cache:warmup` running at build time (not first-request time), `lexik:jwt:generate-keypair` creating keys into the persistent volume on first boot only.
- [ ] **Run migrations** (via Coolify's "Execute command in container" or SSH + `docker exec`): `bin/console doctrine:migrations:migrate --no-interaction`, then `bin/console messenger:setup-transports`.
- [ ] **Smoke-test the API**: `curl https://<domain>/health` → expect 200; hit an authenticated `/api/v1/...` route to confirm JWT issuance/validation works with the freshly generated keypair.
- [ ] **Confirm the worker is alive**: `ssh user@vps-ip docker logs <worker-container> --tail 50` shows `messenger:consume` running, not crash-looping. Push a test message through the `async` bus and confirm it's consumed (queue depth in `/health` returns to 0).
- [ ] **Confirm log rotation is active**: `docker inspect <container> | grep -A2 LogConfig` shows `max-size`/`max-file` on every service.

## Phase 6 — Operational hardening (post-first-deploy)

- [ ] **Nightly MySQL backup**: cron `mysqldump` inside the DB container piped to OVHcloud VPS Snapshots or Backblaze B2 (closes the "no auto-backup, full data loss on corruption" risk register row).
- [ ] **External uptime monitor**: point UptimeRobot (free tier) at `https://<domain>/health` — this is what catches the pre-mortem's "worker dies, nobody notices for two weeks" scenario, since Coolify itself won't surface a silently-dead worker.
- [ ] **Document the rollback path**: Coolify UI → Deployments → Redeploy previous build (~2 min). Note that DB migrations don't auto-rollback — confirm migrations stay backward-compatible or document manual down-migration steps.

---

## External-integration edge cases (extra support steps)

These are the points most likely to break silently in production because they depend on services outside the VPS:

1. **GUS API (Polish business registry, SOAP)** — `gusapi/gusapi` + `soap` PHP extension (already in the Dockerfile). The `.env.dist` `GUS_API_KEY` is a placeholder; GUS issues *separate test and production* API keys, and the SOAP endpoint URL differs between sandbox and production. **Action**: confirm with the user which key/environment they're using, and verify `GUS_API_LIMIT=5` (rate limit) is enforced in code — GUS will throttle/block on excess calls, and there's no local fallback.
2. **Mailer (Brevo/Sendinblue)** — `.env.dist` only declares `EMAIL_LAB_SMTP`/`EMAIL_LAB_APP_KEY`/`EMAIL_LAB_APP_SECRET` but `config/packages/mailer.yaml` reads `MAILER_DSN` directly (currently `null://null` in dev `.env`). **Action**: these two are disconnected — either wire `MAILER_DSN` to assemble from the `EMAIL_LAB_*` vars (Brevo SMTP DSN format: `smtp://<key>:<secret>@smtp-relay.brevo.com:587`) in `.env`/Coolify config, or confirm there's a `MailerDsnFactory`-style service doing the assembly. Test a real send (e.g. password-reset email) post-deploy — `null://null` will silently succeed without sending anything, masking a misconfiguration.
3. **JWT keypair persistence** — covered in Phase 1/3, but worth re-flagging: if the `config/jwt` volume isn't wired correctly, every redeploy invalidates all issued tokens (forces all users to re-login) and may throw 500s if `lexik:jwt:generate-keypair --skip-if-exists` races with the app boot. Verify the volume mount *before* the first real user logs in.
4. **Vich Uploader file persistence** — `avatars`/`logos` are written to `public/uploads/images/...` inside the container. Without the named volume from Phase 1, a redeploy silently deletes all user-uploaded images (no error, just 404s on old URLs). Confirm the volume survives a manual "Redeploy" before going live.
5. **2FA / TOTP QR codes (`scheb/2fa-totp`, `endroid/qr-code`)** — QR generation embeds an `otpauth://` URI that includes the issuer/host. Behind Traefik's reverse proxy, confirm Symfony's `trusted_proxies`/`request.trust_proxy_headers` are configured (check `config/packages/framework.yaml`) so generated URIs use `https://<domain>` and not an internal container hostname — a wrong host here breaks every user's authenticator app silently.
6. **CORS** — `CORS_ALLOW_ORIGIN` is a regex matching `localhost`/`127.0.0.1` only. If there's a separate frontend origin in production, this must be updated in Coolify env vars or every API call from the frontend will be blocked by the browser (visible only in browser devtools, not server logs — easy to miss in smoke testing if you only `curl` the API directly).

---

## Verification checklist (end-to-end)

- [ ] `https://<domain>/health` returns 200 and reflects true DB + queue state (kill the worker manually once to confirm it flips to 503 — proves the monitor would actually catch the pre-mortem failure mode)
- [ ] A full sign-in → JWT issuance → authenticated `/api/v1` request round-trip succeeds over HTTPS
- [ ] An async message (e.g. triggering log ingestion) is processed by the worker within the PRD's freshness NFR window
- [ ] A file upload (avatar/logo) survives a manual Coolify "Redeploy"
- [ ] A test email is actually delivered (not just accepted by `null://null`)
- [ ] `docker logs` on every service shows rotation config applied; disk usage (`df -h`) is sane after a day of traffic
- [ ] Reboot the VPS once (low-traffic window) and confirm the worker comes back up via the `depends_on: condition: service_healthy` healthcheck — this is the exact failure mode `infrastructure.md`'s pre-mortem describes
