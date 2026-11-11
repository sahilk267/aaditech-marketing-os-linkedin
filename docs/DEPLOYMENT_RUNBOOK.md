# Automated deployment and database bootstrap (2026-11-12)

## Preconditions

1. GitHub repository root is the Laravel application; historical overlay folders are ignored.
2. PHP >=8.3, Composer, PHP extensions `pdo_mysql`, `curl`, `dom`, `xml`, `mbstring`, `openssl`, `fileinfo`. Verify Hostinger PHP CLI version matches your web runtime; failing closed is intentional.
3. Private application directory above public webroot; domain document root **must point to `public/`**. Never expose source, `.env`, `storage/`, or `vendor/` publicly.
4. Hostinger/MySQL database credentials in `.env` or protected environment. Set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` and `APP_URL`. MySQL user must have schema privileges. Provision database once in hPanel when `CREATE DATABASE` is not allowed: no application can self-grant server permissions.
5. A secure backup and restoration path for the database and encrypted `APP_KEY` (losing key permanently loses encrypted OAuth tokens).

## Fresh deploy

```bash
# When .env is missing, interactive SSH setup prompts for private credentials
bash scripts/install.sh
# For unattended deploy, copy .env.example to .env privately and set APP_URL/DB_* first
php artisan aadi:doctor --json
php artisan route:list
```

`install.sh` reuses bundled framework packages from a CI-built release artifact when present; otherwise it installs them with Composer; runs `scripts/deploy.php --apply --create-database`. Script creates DB if authorized, generates an `APP_KEY` if absent, runs all migrations and four-brand seeder without destructive reset, and builds config/view caches. Existing admin is never overwritten. If admin was not set with secure environment credentials, create interactively:

```bash
php artisan user:bootstrap-admin owner@your-domain.example
```

## Upgrade / repeat run

Create a verified encrypted database and `.env` backup first, deploy a reviewed commit, then run `bash scripts/install.sh`. Migrations are idempotent and `BrandSeeder` uses upsert. **Never run `migrate:fresh`, `db:wipe` or `config:clear` as a substitute for migrating production.** Test rollback restoring DB+matching code+same app key in staging.

## Scheduler and local worker

Hosting cron alternative (recommended on Hostinger when permitted): `* * * * * /usr/bin/php /private/path/artisan schedule:run > /dev/null 2>&1`.

Without hosting cron, authorize a credential with `heartbeat` capability; local worker calls authenticated `POST /api/v1/worker/tick` every ~60s. Tick is idempotent/guarded server-side, schedules research only from approved sources and drafts only from verified evidence. It **does not** autonomously approve sensitive claims or bypass platform scopes. The worker needs Python 3.11+, `pip install -r worker/requirements.txt`, optional local Ollama, and FFmpeg for video. Install as startup task or service using your OS mechanism; do not expose Ollama publicly.

## Operations / read-only verification

`php artisan aadi:doctor` evaluates PHP, app key, storage, DB, migrations, brands/admin, optional connector credentials and worker heartbeat. Never emit secrets. `php artisan test` and Python tests must pass in a compatible staging build; check GitHub Actions evidence. A successful `aadi:doctor` cannot verify a real LinkedIn post or provider API approval.

## Failure modes

- `BLOCKED: extension` — activate PHP extensions in hosting; no code bypass.
- `BLOCKED: MySQL connection/CREATE permission failed` — correct credentials or create the database through hPanel. Tables still migrate automatically afterward.
- `BLOCKED: vendor` — ensure Composer installation and compatible PHP version.
- `CORE_READY: false` — fix missing app key/admin/storage/DB, do not treat deployment as complete.
- Worker offline — publish queue waits, manual-ready packages remain downloadable; no blind duplicate-post retries.
- OAuth approval missing — official adapter blocked, content goes to manual-ready.

## Security baseline

No wildcard public installer, no hardcoded passwords, no fake API authorizations, no paid model fallback, no secrets in GitHub Actions logs. In public deployment keep `APP_DEBUG=false`, HTTPS only, encrypted tokens, restricted CORS and login throttling. The absence of credentials must always be visible in readiness status.

## Verified setup and platform recovery workflow

On a private SSH terminal, `bash scripts/install.sh` launches `scripts/setup.sh` if no `.env` is present. It prompts for app URL, DB details and initial admin credentials without a default password. If no interactive shell exists, provide secure environment variables/.env before running the installer. The MySQL user still needs valid grants; the installer never changes server permissions or resets existing data.

GitHub Build Deployment Bundle now runs Laravel tests, worker tests, source integrity and tracker checks *before* creating the tarball; the archive includes its resolved `composer.lock` and production `vendor/`. An artifact should not be deployed if the workflow did not pass. No source-only syntax check is sufficient for production.

On the desktop, `worker/install_windows.ps1` provisions Python virtual environment, checks worker tests, requests a scoped credential, restricts `.env.worker` permissions and creates a user-level startup task. An at-logon task is not an always-on headless service; use a controlled Windows service where needed.

An ambiguous LinkedIn provider response is never automatically retried. Use the brand Marketing page reconciliation panel to record a verified provider post ID or explicitly mark the item manual-ready after checking official organization activity. This action is admin-only and audited.

Hostinger may use **MariaDB** behind its MySQL-compatible connection. The GitHub CI database matrix covers MySQL 8.0 and MariaDB 11.4. The actual database and grant set are established at owner deployment; database creation remains conditional on `CREATE DATABASE` permission.
