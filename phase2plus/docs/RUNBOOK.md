# Operator runbook (execute only when ready for setup)

## System requirements

- Hostinger shared account: inspect PHP 8.3+, curl, DOM, mbstring, XML, PDO MySQL, secure document root and cron. MySQL 8-compatible InnoDB with transaction support and row locking.
- Local Windows desktop: Python 3.11+, 16 GB RAM, CPU-only Ollama small quantized model with manual benchmark; FFmpeg optional. Outbound HTTPS; no inbound ports.
- No paid APIs, Redis, VPS, n8n, Docker or browser robots.

## Install source on a **new, inspected Laravel project**

```
composer create-project laravel/laravel aadi-marketing-os "^13.0"
python phase1/scripts/apply_overlay.py --target /path/to/aadi-marketing-os
python phase1/scripts/apply_overlay.py --target /path/to/aadi-marketing-os --confirm
python phase2plus/scripts/apply_overlay.py --target /path/to/aadi-marketing-os
python phase2plus/scripts/apply_overlay.py --target /path/to/aadi-marketing-os --confirm
cd /path/to/aadi-marketing-os
php artisan key:generate
php artisan migrate
php artisan db:seed --class=BrandSeeder
php artisan test
```

Read every dry-run overwrite and install on a staging checkout first. Backups are created when overwrite occurs. Set server `APP_DEBUG=false`, production HTTPS, `DB_CONNECTION=mysql`, correct `APP_KEY`, CORS and session settings before production. The Phase 1 `BootstrapAdmin` and `IssueWorkerCredential` artisan commands handle local admin/worker enrollment.

## Worker configuration

```
cd phase2plus/worker
python -m venv .venv
# Activate venv in PowerShell, install: pip install -r requirements.txt
# Copy .env.worker.example to a secure local .env.worker and set values; do NOT git add
python -m amo_worker.main --once
python -m amo_worker.main --forever
```

The worker reads process environment variables, not `.env.worker` automatically. Load them with a service manager/PowerShell environment configuration. Issue worker credential with `php artisan worker:issue-credential desktop --capability=research.fetch --capability=content.generate --capability=media.render --capability=publish.post --capability=ai.catalog_sync`. The token is printed once, never stored in Git. Capability names must match exactly.

## Scheduler

Verify Hostinger cron support. Schedule `php artisan schedule:run` once per minute if allowed. The core worker polls for due jobs regardless, but maintenance/offline alerts and Telegram outbox processing require an enabled schedule or a supported administrator invocation.

## LinkedIn OAuth and manual fallback

Set `LINKEDIN_CLIENT_ID`, `LINKEDIN_CLIENT_SECRET`, `LINKEDIN_REDIRECT_URI` (HTTPS), `LINKEDIN_API_VERSION` (supported YYYYMM). Register the redirect in the official LinkedIn developer app. Use the authenticated POST `/brands/{brand}/linkedin/connect` with `organization_id`, follow the authorized OAuth flow, then verify company role/app permissions separately. Admin approval endpoint `/api/v1/brands/{brand}/social-accounts/{id}/approval` takes `{"approved":true}` only after documented tests. Unsupported accounts remain manual ready.

## Daily operations

Monitor worker heartbeats, queue counts, `retry_wait` and `dead_letter`; review `needs_reconciliation` before any repeated publish; preserve provider post IDs and checksums. Import verified manual analytics; missing metrics should remain missing. Rotate credentials periodically. Do not forward raw requests/tokens to Telegram.

## Backup and restore

Use hosting-supported daily database backups and periodic encrypted exports of `brands`, `content_items`, `publishing_jobs`, `social_accounts` and workflow metadata. Keep encrypted APP_KEY separately; without it OAuth token ciphertext cannot be decrypted. Exercise MySQL restore on staging; verify owner-generated media checksum manifests, and never depend solely on desktop temp files.

## Integration sign-off checklist

- Fresh Laravel skeleton and all migrations complete.
- Auth, brand access, CSRF and worker replay tests pass in staging.
- MySQL concurrent worker lease and crash/reclaim tested.
- OpenRouter live catalog free-only enforcement verified; Ollama fallback CPU benchmarked.
- Research blocked SSRF/robots proof, claim evidence and human review tested.
- LinkedIn author roles/scopes and Posts API results verified with test account (no duplicate on timeout).
- Backup/restore completed and alert privacy reviewed.
