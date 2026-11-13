# Release readiness — evidence-based (2026-11-12)

## New canonical application

The repository root now contains a consolidated Laravel 13 application in place of installing separate Phase 1/2+ overlays. The 4 colliding paths were merged by choosing the feature-complete Phase 2+ route/view versions once, with Phase 1 authentication and worker routes preserved. Bootstrap and base Laravel framework migrations/configuration were added. Runtime classes from both overlays exist once at their canonical PSR-4 paths. Old `phase*` trees remain historical and are not Composer-autoloaded.

## What was implemented in this increment

- Single root `composer.json`, `artisan`, `bootstrap/*`, `public/index.php`, config files, standard users/cache/jobs/sessions migrations, all original domain migrations, Blade views, controller and seeded four brands.
- One-command `scripts/install.sh` and safe, non-destructive `scripts/deploy.php`, which can create a MySQL schema only when the MySQL account is allowed, migrate/seed and provision initial admin from protected environment. No web-exposed installer or default password.
- `aadi:doctor` readiness report; `aadi:tick` plans approved research and evidence-backed drafts; authenticated throttled worker tick allows no-Hostinger-cron operation.
- Lease renewal every 240s during long CPU model tasks; new PNG + three-slide carousel renderer using Pillow with server-side PNG checks; latest-per-post metric aggregation (no cumulative snapshot overcount).
- New static/worker and Laravel feature test sources and GitHub Actions configuration to execute them on PHP 8.3/8.4 and Python 3.12; CI release artifact embeds Composer `vendor/` so the hosting server does not require Composer.

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

## 2026-11-12 hardening update (pending CI and live testing)

**Added source:** private first-deploy setup wizard (`scripts/setup.sh`), test-gated release workflow and embedded build lock, server-verified provider model catalog, strict LinkedIn OAuth scope handling, audited reconciliation of uncertain publishing outcomes, operator workflow forms, user-scoped Windows worker startup script and local configuration loader. The duplicate `media_result` definition is removed and future duplicates are rejected by `scripts/check_source.py`.

**Local evidence:** Python worker tests 13/13 passed, PHP syntax checks, source structure checks, tracker integrity validator, JavaScript syntax and Bash syntax checks. Composer and required PHP DB extensions are unavailable in this sandbox, so fresh Laravel + MySQL execution and CI green status are *not* established. Windows Task Scheduler installer is not tested on an actual desktop.

**Still not complete:** All authorized social publishing adapters (Facebook/Instagram/YouTube/Pinterest/Threads/TikTok), media upload through platform APIs, claim-level semantic evidence proof, fully automated analytics collection, high-quality video production, security/load/backup recovery drills, actual Hostinger install, actual Windows/Ollama benchmark, and live OAuth permissions. Readiness remains **NOT PRODUCTION VERIFIED**. Public repository must never contain credentials.

## Database compatibility coverage added (2026-11-12)

Hostinger's current support documentation describes MariaDB on shared/cloud hosting, although it provides MySQL-compatible access. CI now tests the Laravel feature suite against **both MySQL 8.0 and MariaDB 11.4**, with the release artifact additionally gated on the MariaDB suite. These are * CI tests*, not a substitute for actual Hostinger PHP/MySQL permissions, migration backup and post-deploy smoke tests.

## Database healthcheck fix (2026-11-12)

The MariaDB 11.4 Docker image uses `healthcheck.sh --connect --innodb_initialized`; it must not inherit MySQL's `mysqladmin` probe, which may be absent. CI now selects the healthcheck by database image and the release bundle uses the MariaDB-native check. The first MySQL 8.0 matrix job passed, while MariaDB container initialization remained pending in the preceding run. This change requires a **fresh GitHub Actions rerun** before calling the MariaDB compatibility/release gate passed.


## Worker and notification reliability increment (2026-11-12)

Worker explicitly requests a 900-second lease. Its 240-second keepalive was inside that limit, but could leave an active long-running CPU task appearing offline. The worker now renews its lease and heartbeat every 60 seconds, with a regression test.

Telegram operational outbox now atomically claims alerts, retries unacknowledged deliveries, and recovers stale sending claims. Messages contain only event type and internal numeric ID, never untrusted event payloads or credentials. Publication success/manual fallback/reconciliation and worker reconnect state now emit events; maintenance creates deduplicated backlog and OAuth expiry alerts. Telegram is at-least-once because Telegram offers no caller-supplied idempotency key. The new Laravel tests exercise missing credentials, delivery, retry, and data redaction.


## Release artifact smoke-install gate (2026-11-12)

The GitHub release workflow now extracts its produced archive into a clean folder and calls the **same `scripts/install.sh` shipped to users** against a fresh disposable MariaDB database. The gate validates automatic database creation (with temporary CI root grants), migrations, four-brand seeding, admin bootstrap and `aadi:doctor`, then runs installation a second time to check idempotence. The ZIP/tar artifact is uploaded **only if these steps pass**. This is CI evidence, not proof of Hostinger permissions, DNS, TLS or live platform OAuth.


### Installer dotenv credential parsing (2026-11-12)

The deploy preflight now uses Laravel's own `vlucas/phpdotenv` parser instead of a custom quote-stripper. This fixes the mismatch for valid database passwords containing embedded quotes or backslashes. An integration regression checks that the interactive setup's escaped password encoding round-trips with the deployment parser.


## Official LinkedIn single-image publishing (2026-11-12)

The authorized LinkedIn organization adapter now supports **single PNG images** in addition to text. It initializes the official Images API upload, uploads only to a narrowly allowed HTTPS LinkedIn upload URL (no redirect), checks the returned image URN/status, and posts using the official Posts API. It validates same-brand media ownership, the stored checksum and file bytes, supported PNG format, scope, organization, token and API version. Scheduling image content requires a rendered same-brand PNG. Failures enter manual reconciliation rather than blind publish retry. An HTTP-mocked feature test covers the intended requests and malicious upload URL rejection.

This **does not implement** LinkedIn video, document, multi-image or carousel publishing, nor prove real organization OAuth access. Those remain manual-ready and approval-gated. The mocked test must pass GitHub CI before marked verified; real end-to-end test awaits an authorized account.

## Offline-first manual authoring increment (2026-11-12)

Added `ManualContentController`, `POST /api/v1/brands/{brand}/content/manual`, a browser form and feature tests for immediate brand-scoped draft creation without an AI provider or desktop worker. All manual drafts require admin approval; no unverifiable claim is automatically marked verified. Duplicate exact drafts within a brand/platform are rejected. CI execution and actual staging verification are required; no assertion of 100% live readiness follows from source changes.

## Instant native-platform publishing package (2026-11-12)

Added a non-worker/non-OAuth flow: approved same-brand content can be moved directly to a manual-ready publication with a stable idempotency key, durable event and copyable package URL. Cannot be used for unreviewed content or across brand permissions. The feature is covered by Laravel tests; current GitHub CI and live staging results must be checked before verification. This is deliberately **not** an automatic API publisher.

## Secure media download coverage (2026-11-12)

The previously SVG-only private asset download path now supports same-brand PNG and carousel slides with SHA-256, byte-size and dimensions integrity checks. `manual-package` JSON includes branded media download links. Download/brand-permission feature tests added. Source changes require current CI and live file-storage verification; this does not imply video generation or automatic media publishing support.

## Meta Facebook Pages text publishing (2026-11-12)

Added an official Graph API text Page adapter with exact page ID, version, Page-token, expiry and scope checks. Only an admin-approved, connected Facebook Page with all required scopes can be scheduled for API publishing; image/video remain manual-ready. Post IDs must be explicitly confirmed; ambiguous responses enter reconciliation, never blind retry. Added HTTP-mocked contract/approval tests. This is source and CI contract coverage only: Meta OAuth setup, real Page account approval and live publishing are NOT verified.

## Meta Facebook Pages OAuth connection (2026-11-12)

Added authenticated admin-only Facebook Page OAuth, one-time state with replay prevention, provider permission validation, Page-list verification and encrypted Page token storage. Tokens are never returned to browser; connected accounts remain `api_access_approved=false` until separate owner verification. The Page lookup currently covers the first 100 Pages; pagination and permission revocation lifecycle require additional work. HTTP-mocked feature tests added; Meta app review and live Page authorization remain unverified.

## PHP-only creative generation (2026-11-12)

Added a server-side deterministic SVG renderer for approved brand content. It requires no AI key, Python worker, GD or media API; the SVG is XML-escaped, uses validated brand colors, is stored by checksum and is available through authenticated download/manual packages. Laravel tests cover cross-role blocks, no-worker rendering, idempotency and SVG escaping. This is a basic creative fallback, not high-end image/video generation. CI and real hosting file permissions still require verification.

## Immutable publication approval snapshot (2026-11-12)

Scheduled official publication now records a SHA-256 seal over the approved content, variants, evidence, approval actor/time and linked media hashes. Before API submission, the posting service checks that seal and blocks stale or legacy-unsealed scheduled entries as `blocked_review`, without contacting the provider. New schedules use deterministic keys so identical requests do not create multiple queue publications. The manual package also refuses changed content. Laravel regressions cover changed content, missing seals and duplicate schedules. **Remaining:** deep edit-vs-send concurrency fencing under parallel transactions, real provider idempotency/timeout drills, and verified operator correction UI. No claim of zero duplicate posts in all network failures.

## Portable Windows worker (2026-11-12)

Added prebuilt Windows executable CI, no-Python PowerShell installer and frozen executable config path support, with worker tests and packaged binary smoke test. Until the Windows workflow succeeds its executable is not verified. Actual Windows Task Scheduler and Ollama/desktop availability remain owner setup checks.

## Offline editorial end-to-end regression (2026-11-12)

New OfflineJourneyTest checks the complete native browser/API path on a disposable database with no AI, worker, OAuth or live external calls: brand-scoped manual draft, accountable admin approval, PHP SVG creative generation, authenticated media download and copyable manual-ready package. This is framework integration evidence after CI passes, not proof of real Hostinger/Web UI accessibility or autonomous social posting.

## Verified CI evidence for 2026-11-12 hardening

- Approval snapshot and deterministic schedule deduplication: GitHub Actions application CI run `43216301822` success; release smoke installation run `43216301791` success.
- Windows portable executable: workflow run `43217292345` success, artifact `13942804382`. Initial run found and fixed Windows SQLite open-handle cleanup (worker/amo_worker/ai.py).
- No-AI no-worker browser/API journey: `tests/Feature/Phase2Plus/OfflineJourneyTest.php` added, acceptance remains pending exact commit CI and controlled browser verification.

These are **CI/staging approximations**, not live Hostinger, desktop uptime, OAuth provider approval, all-provider analytics or 100% finished scope evidence. Preserve remaining gaps in the tracker.

## Brand capability readiness panel (2026-11-12)

Brand overview now reports offline drafts, SVG generation, manual packages, worker heartbeat, fresh zero-priced AI catalog, approved source count and currently approved same-brand publishing platforms without exposing tokens. A brand-scope regression test rejects other-brand access or token leakage. Results describe **configured code paths**, not proof of live remote provider authorization or uptime; CI validation required.


## Content revision invalidation and publishing lock order (2026-11-13)
Added same-brand PATCH content editing with mandatory new review, revision audit hashes, detach of stale creative assets, and explicit blocking of scheduled/manual-ready posts. Sent, succeeded and ambiguous provider outcomes cannot be rewritten. Official publishing and content-edit transitions share the content-before-publication row-lock order; offline render rechecks approved content under lock before persisting media. Laravel tests exercise role isolation, review revocation, scheduled cancellation, stale asset protection and immutable sent posts. Owner approval, full parallel race/chaos testing, real hosting verification, and full revision history remain open. This is not a 100% production-readiness claim.


## Factual claim evidence gate (2026-11-13)
Existing code trusted research-ID references for claim support. New ClaimEvidenceGate and ClaimAttestationController require an accountable admin attestation for each exact claim/source pairing, an excerpt present in stored research facts, an explicit reject/support decision and a written rationale. The review binds to the exact content body and research content hash. Changed/revoked/stale sources fail publication checks; a scheduled post is blocked_review instead of sent. Original workflow remains human-approval-first. Regression cases in ClaimAttestationTest.php passed Laravel application CI 43403634588.
This is **not automated semantic fact verification**: excerpt containment does not prove correctness, and no unattended claim can be self-certified. Real source review and stronger semantic adversarial tests remain open. The claim verification module, G05/G10/G11, and production activation are not formally complete.


## Audited research review and freshness expiry (2026-11-13)
Research verification now requires admin approval/rejection, source excerpt, rationale and an auditable decision. Unapproved, Tier D, old or foreign-brand research cannot be verified. Periodic maintenance/tick expires sources older than 7 days for Overseas Job Placement and 90 days for other brands; shorter owner-defined periods are permitted. Request-time policy also checks the actual fetch date when scheduled cron is unavailable. This is a conservative engineering safety default, not proof that remote information is accurate or legally sufficient. Full live provider/source acceptance is pending.
