# Aadi Marketing OS — Unified Laravel Application

**Canonical runnable source** is at the repository root: `artisan`, `composer.json`, `app/`, `bootstrap/`, `database/`, `routes/`, `resources/`, `worker/`. The older `phase0/`, `phase1/` and `phase2plus/` folders are historic reference overlays **and are not installed or autoloaded**. Do not apply them again.

## Deployment contract

- PHP **8.3+**, Laravel 13 and extensions documented at [Laravel 13 deployment requirements](https://laravel.com/framework/docs/13.x/deployment).
- Existing Hostinger Business plan with supported PHP, MySQL 8-compatible instance and a domain whose document root points to `public/`. The project root must **never** be web-accessible.
- Zero paid AI requests: the worker checks current OpenRouter model prices before making any call and uses local Ollama when free capacity is unavailable. No paid fallback, Redis, n8n, paid search API, browser posting bots or VPS.
- Four brand profiles are seeded idempotently. Publishing requires human approval, verified account permissions and official API access. Unsupported channels provide `manual_ready` export packages, not fake auto-posting.

## One-command backend installation / upgrade

After deploying code, run the installer. In an interactive SSH terminal with no `.env`, it prompts securely for database/app/admin credentials; for unattended deployment, configure private `DB_*`, `APP_URL`, and `AADI_ADMIN_EMAIL` in the environment or `.env` (never Git):

```bash
bash scripts/install.sh
```

This uses the prebuilt, CI-test-gated GitHub release dependencies when bundled (`vendor/`) or installs them via Composer when absent, checks requirements, attempts to create the configured MySQL database **only if the supplied MySQL account has CREATE privilege**, applies migrations, seeds the four brands, initializes `APP_KEY` if missing, and builds caches. It does not erase existing data. For noninteractive owner creation, provide a high-entropy password through the protected `AADI_ADMIN_PASSWORD` environment variable (minimum 14 characters); otherwise run `php artisan user:bootstrap-admin owner@your-domain.example` interactively. No default password or public installer exists.

**Hosting limitations cannot be bypassed:** without working database/server credentials, required PHP modules, and MySQL privileges the installer fails closed with a diagnostic rather than falsely reporting success. Database **tables** are always automatically migrated once connectivity is available. See `docs/DEPLOYMENT_RUNBOOK.md`.

To preflight only:

```bash
composer install --no-dev --optimize-autoloader
php scripts/deploy.php --check
php artisan aadi:doctor --json
```

Configure a hosting cron every minute (`php /private/path/to/artisan schedule:run`) **or** have the local Python worker call the protected `/api/v1/worker/tick` endpoint once per minute. Do not expose worker tokens in URLs. The app can operate with the worker in outbound-only mode; CPU-only content generation/rendering happens on that desktop.

## Tests

```bash
composer install
php artisan test
cd worker && python -m pip install -r requirements.txt && python -m unittest discover -s tests -v
```

GitHub Actions runs framework tests and worker tests on push. **Until CI has produced a green Laravel run and the Hostinger/MySQL/worker/OAuth live checks have passed, the system is not production-verified.** Test cases and source-level checks do not count as a live integration pass.

## Control documents

- [`docs/RELEASE_READINESS.md`](docs/RELEASE_READINESS.md) — actual implementation status, evidence and blockers.
- [`docs/DEPLOYMENT_RUNBOOK.md`](docs/DEPLOYMENT_RUNBOOK.md) — exact first-deploy/upgrade/rollback process.
- [`docs/implementation/`](docs/implementation/) — 19 components, 146 tasks, 40 requirements, 40 E2E test cases, conflict/decision trackers.
- [`phase0/docs/SRS.md`](phase0/docs/SRS.md) — original requirements baseline, unchanged.

There is no claim of universal autonomous publishing. Official platform permissions and acceptable source evidence must be granted/verified by the account owner.

## Available operator workflow

Authenticated brand marketing screens include source registration/approval, permitted fetch, human research verification, evidence-backed AI draft, accountable content approval, media render, scheduling/manual-ready package, metric import, and publication reconciliation. LinkedIn organization text posting remains behind official app authorization and independently verified scopes; other channels stay manual-ready. A Windows startup-task installer is available at `worker/install_windows.ps1` (requires desktop verification).

**Source does not equal 100% deployment readiness:** unresolved provider permissions, hosting grants, remote desktop operation, full Laravel/MySQL/Windows tests, and remaining advanced channel features are still tracked in the gate register. No code can grant OAuth scopes or database privileges that the hosting/platform account does not possess.

Database compatibility CI includes MySQL 8.0 and MariaDB 11.4, plus the default SQLite test run. Production release bundles are gated on MariaDB integration before publication.

## Offline-first publishing fallback

The marketing screen now includes a manual draft form that requires neither OpenRouter nor an installed worker. Drafts are scoped to the selected brand, checked for exact duplicates, and saved as `needs_review` with `risk_level=high`; an admin must review claims/evidence before publication. When no approved official platform API is available, the existing manual-ready publishing package remains the safe supported route. This is a usable fallback, **not** a substitute for authorized API publishing or semantic fact verification.
