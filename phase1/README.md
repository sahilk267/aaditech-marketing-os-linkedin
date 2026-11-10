# Aadi Marketing OS — Phase 1 source overlay

**Prepared:** 2026-11-11 (Asia/Kolkata)  
**Status:** Source-level implementation package; **not yet a deployed or fully tested Laravel application**.  
**Basis:** `docs/SRS.md` (unchanged baseline specification) and Phase 0 preflight.

This package advances the four-brand marketing OS with a **Laravel 13-compatible overlay** for a newly generated Laravel application. It preserves the earlier decisions: Laravel/MySQL on the existing Hostinger Business web hosting, local Python worker over outbound HTTPS, OpenRouter free-only AI as a later phase, and no Redis, n8n, browser publishing bots, paid AI API, or VPS.

## Contents

- `overlay/` — PHP migrations, models, brand policy, session login, CRUD, audit observer, worker bearer auth, replay-nonce protection, registration/heartbeat endpoints, console provisioning, dashboard Blade views and Laravel feature tests.
- `worker/heartbeat_client.py` — a minimal **Python stdlib** outbound HTTPS registration/heartbeat client; **does not** claim jobs or publish anything.
- `scripts/apply_overlay.py` — dry-run-first installer for a **fresh Laravel skeleton**; `--confirm` creates backups of overwritten stock skeleton files.
- `tests/test_phase1_package.py` — static package checks runnable without Composer.
- `validation/` — captured PHP syntax and Python static package test output, plus detailed limits in `docs/PHASE1_STATUS.md`.
- `docs/` — the original SRS, architecture/security references, installation/runbook and phase report.

## Install in a new, isolated Laravel skeleton

Never run against an unrelated React/Express project, an existing production installation, or a working tree containing unreviewed changes. First verify Hostinger PHP version/extensions, MySQL, cron, and the target Git repository.

From a **development machine** with PHP >= 8.3, Composer, cURL/DOM/Mbstring/XML/PDO MySQL and a supported database:

```bash
composer create-project laravel/laravel aadi-marketing-os "^13.0"
cd aadi-marketing-os
# Inspect stock composer.json and .env.example first.
python /path/to/aadi-marketing-os-phase1/scripts/apply_overlay.py --target .
python /path/to/aadi-marketing-os-phase1/scripts/apply_overlay.py --target . --confirm
composer install
cp .env.example .env  # do not commit the real .env
php artisan key:generate
# Configure DB_* for the LOCAL or staging database, no production values in Git.
php artisan migrate
php artisan db:seed --class=BrandSeeder
php artisan user:bootstrap-admin admin@example.com  # enter real owner email; password prompted securely
php artisan test --filter=Phase1
php artisan route:list
php artisan serve  # development only
```

`composer create-project` should be run with supported PHP extensions already installed. For a new Laravel 13 application, confirm the stock skeleton includes expected `users`, `sessions`, `cache` and database queue migrations before applying this overlay. The installer will overwrite core starter files **after backup** (routes, bootstrap, AppServiceProvider, User, DatabaseSeeder). The example address above is **a placeholder**.

For a staff account:

```bash
php artisan user:create-staff editor@example.com --role=editor
php artisan user:assign-brand editor@example.com aadi-tech-solutions
```

Available roles are `admin`, `editor`, `analyst`, `operator`. `admin` is the owner/admin role; editor, analyst and operator users must be explicitly assigned brands. No public registration route exists.

Provision one worker credential and keep its one-time secret out of logs:

```bash
php artisan worker:issue-credential desktop-01 --capability=heartbeat
```

Set `AADI_API_BASE_URL` and `AADI_WORKER_TOKEN` in a protected environment on the desktop using `worker/.env.worker.example` as an example; **do not** put the actual token into the repository. Validate the worker endpoint only after HTTPS and database migrations are confirmed:

```bash
python worker/heartbeat_client.py
```

A successful prototype registers the local node and sends one heartbeat by default; `AADI_WORKER_CONTINUOUS=1` enables the minute-by-minute loop. Production worker-service setup and phase 2 job leases remain unimplemented.

## Important limitations

- This package is **not** the full framework distribution: it requires the composer-installed Laravel skeleton. Composer, required PHP extensions, and a DB driver are missing in the current authoring sandbox.
- No integration tests or migrations have been executed against MySQL or a running Laravel app.
- Hostinger account environment, actual Windows worker machine, OAuth approval, and Ollama performance remain unverified.
- No background services, social publishing, AI model routing, content factory, analytics, or social API calls have been activated.
- Brand configuration editing currently protects seeded high-risk restriction fields by preserving custom config; advanced schema-based catalog/source management is future work.
- Phase 1 cannot be declared accepted until `docs/PHASE1_STATUS.md` blockers are resolved and all feature tests pass in a real Laravel environment.
