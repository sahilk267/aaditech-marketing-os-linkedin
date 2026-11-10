# Phase 1 status and validation evidence

**Date:** 2026-11-11 (Asia/Kolkata)  
**Status:** **PARTIAL / SOURCE PACKAGE PREPARED** — target deployment and Laravel integration testing blocked by environment preflight requirements.

## Phase scope completed as source code

1. Laravel 13-compatible `bootstrap/app.php`, `routes/web.php`, `routes/api.php`, `routes/console.php`.
2. Account login/logout with session regeneration, inactive-account denial, password-protected dashboard and no public sign-up. Owner/admin bootstrap via password prompt; create/assign staff from CLI.
3. Database migration overlays: additional user role and status fields; brands; explicit `brand_user` memberships; audit log; worker nodes; hashed credentials and replay-nonce table.
4. Authorization: admin full visibility, other roles only assigned brand read access; non-admin brand mutation denied. Cross-brand policy and query filtering.
5. Brand seed entries for Aadi Tech Solutions, Overseas Job Placement, AadiPro and ToolboxGalaxy; unknown AadiPro offerings deliberately left empty. Employment/source verification safeguards preserved in profile configuration.
6. Audit observer logs create/update action and changed field names without full configuration text or secrets. Brand mutations wrapped in DB transactions so auditing shares the same transaction.
7. Minimal Blade dashboard, brand list/detail/create/edit pages and CSS without remote UI dependencies.
8. Worker bearer-token hash, server-side revoke flag, timestamp ±5-minute freshness, unique nonce anti-replay, short rate limiting, production HTTPS enforcement, allowed capability checks, registration and heartbeat. Prototype Python HTTPS client.
9. Laravel feature test source covering auth, cross-brand IDOR prevention, audit/policy preservation, token revocation, nonce replay, unauthorized capabilities and stale timestamps.
10. Dry-run-first overlay installer that backs up overwritten framework skeleton files before an explicitly confirmed application to an inspected new project.

## Verified **here**

- `find overlay -type f -name '*.php' ! -name '*.blade.php' -print0 | xargs -0 -n1 php -l` — **30 PHP files with no syntax errors**, using PHP 8.4.24.
- `python -m unittest discover -s tests -v` — **10/10 package/helper tests passed**, Python 3.13.5.
- Python worker and installer parse successfully. The helper tests also check real request header generation with a mocked HTTP client and overlay installer backups (no network or framework).
- No real `.env` files or credentials are packaged.

The above checks prove source parseability and package integrity only; they **do not** demonstrate that Laravel routes, migrations, policies, or application-level tests pass.

## Tests still required on suitable development/staging host

1. PHP >= 8.3; cURL, DOM, XML, mbstring, PDO MySQL or SQLite extension available; Composer installed.
2. Create Laravel 13 skeleton. Run overlay installer after inspecting conflicts. `php artisan --version` must succeed.
3. `php artisan migrate:fresh --env=testing` and `php artisan test --filter=Phase1`; inspect failure details and fix framework/API mismatches.
4. Test migrations and rollback on **staging MySQL 8-compatible** database. Verify unique indexes and JSON column behavior.
5. Check admin login, editor/analyst/operator brand restrictions, inactive account/session behavior, audit log update atomicity and onboarding operations.
6. Run worker test from actual desktop to real HTTPS site. Verify proxy HTTPS recognition and that no worker token appears in access/error logs.
7. Verify CSRF, login throttling, cookie flags, file/system access, and that only Laravel `public/` is publicly served.
8. Confirm a cron schedule and cleanup task with the actual Hostinger plan; hosting and Windows benchmark preflights remain blocked.

## Security caveats for deployment review

- Strong bearer token secrets are stored only as SHA-256 hashes. Token is shown once by the CLI and must be stored securely. Rotate or revoke credentials if exposed; implement administrative credential management in future hardening.
- Replay table rows are cleaned by a daily Laravel scheduled task. If cron is not configured, the rows continue accumulating; use manual deletion of old rows as documented until cron is verified.
- The production HTTPS check uses Laravel `Request::isSecure()`. Correct trusted-proxy configuration is essential on TLS-terminating hosting.
- Working credentials must **never** be pasted into chats, stored in tracked files or included in API logs.
- Sessions use stock Laravel session and CSRF middleware; confirm `SESSION_SECURE_COOKIE=true`, `APP_DEBUG=false`, and correct APP_KEY on production.
- Admin-only brand editor preserves risk-restriction keys, but it is not a production policy-rule editor. Additional safety constraints and profile versioning are future work.
- Before shipping to Hostinger, add integration tests for role provisioning, multi-worker races, credential revocation, audit immutability, schema constraints, and deployment rollback.
- Worker capability list controls registration declarations only. Actual job capability enforcement, lease claims, and idempotency belong to Phase 2 and **are not yet implemented**.

## Phase 1 exit criteria

Not met. Full Laravel feature tests, staging MySQL verification, official Hostinger/desktop preflight, and a deployed Laravel skeleton are pending. Phase 2 should not start as a live deployment until this gate is met.
