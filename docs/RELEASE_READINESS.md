# Release readiness — evidence-based (2026-11-12)

## New canonical application

The repository root now contains a consolidated Laravel 13 application in place of installing separate Phase 1/2+ overlays. The 4 colliding paths were merged by choosing the feature-complete Phase 2+ route/view versions once, with Phase 1 authentication and worker routes preserved. Bootstrap and base Laravel framework migrations/configuration were added. Runtime classes from both overlays exist once at their canonical PSR-4 paths. Old `phase*` trees remain historical and are not Composer-autoloaded.

## What was implemented in this increment

- Single root `composer.json`, `artisan`, `bootstrap/*`, `public/index.php`, config files, standard users/cache/jobs/sessions migrations, all original domain migrations, Blade views, controller and seeded four brands.
- One-command `scripts/install.sh` and safe, non-destructive `scripts/deploy.php`, which can create a MySQL schema only when the MySQL account is allowed, migrate/seed and provision initial admin from protected environment. No web-exposed installer or default password.
- `aadi:doctor` readiness report; `aadi:tick` plans approved research and evidence-backed drafts; authenticated throttled worker tick allows no-Hostinger-cron operation.
- Lease renewal every 240s during long CPU model tasks; new PNG + three-slide carousel renderer using Pillow with server-side PNG checks; latest-per-post metric aggregation (no cumulative snapshot overcount).
- New static/worker and Laravel feature test sources and GitHub Actions configuration to execute them on PHP 8.3/8.4 and Python 3.12.

## Verified here

- PHP syntax validation for all canonical PHP files.
- Worker unit tests including safe-free-provider behavior, SSRF rejection, PNG/carousel output and renew-before-complete behavior.
- Static source/layout/duplicate identifier checks and original governance validator.

## NOT verified and therefore NOT marked complete

1. `composer install` and Laravel feature tests: not runnable in current execution container (Composer, pdo_mysql, mbstring, curl, dom, xml missing). GitHub Actions will be the first compatible real integration run; errors require follow-up fixes.
2. MySQL migration, Hostinger privileges, admin bootstrap and cron/worker tick behavior on live infrastructure.
3. OpenRouter real free model requests or Ollama desktop benchmark; provider free status/quotas change.
4. LinkedIn organization OAuth/scope approval and real publication; other channels remain manual-ready until official adapters are separately built and authorized.
5. Guaranteed legal/employment claim verification, automatic advanced audiovisual production, real provider analytics and recovery/reconciliation of ambiguous external API outcomes.
6. Automatic MySQL database creation on an account without `CREATE DATABASE` grants is technically impossible; installer fails safely with hPanel instruction. Provisioning tables is automatic after authorized database access.
7. Security review, load/restart tests, backup/restore, app key custody, publish idempotency under network faults.
8. Composer dependency lockfile: must be generated and committed by a compatible Composer build before reproducible production release.

## Completion rule

Code exists ≠ integration test passes ≠ live environment connected ≠ E2E verified. Keep G01/G02/G03/G07/G09/G10/G11 unverified until each gate's evidence is linked. The active status and task-to-requirement trace are in `docs/implementation/*.csv`. A public GitHub repository is not an appropriate place for credentials.
