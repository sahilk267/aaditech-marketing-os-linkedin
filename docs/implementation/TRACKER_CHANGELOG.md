# Tracker change history

- **2026-11-11 · v1.0.0 · planning baseline:** 19 modules, 146 tasks, 12 release gates, 40 SRS requirement groups, 40 E2E tests, 16 conflict/risk entries, 14 decisions, 9 channel rows. No production/completion claims. Baseline source commit `9c547ae76fcd3f64793112b0c89a3453dac3ea31`.

Future changes must record date, author/reviewer, affected IDs, reason, status/evidence changes and GitHub commit. Never silently drop an unmet P0 requirement or reassign an existing stable ID.

## 2026-11-12: Unified application / safe first-deploy increment
- App canonical Laravel root added; old overlays remain archival; 4 overlap files reconciled to one runtime path.
- Automated conditional MySQL DB creation + mandatory migrations, four-brand seed, secure owner bootstrap, readiness diagnostic.
- Worker scheduler fallback and lease renewal, PNG/carousel renderer, latest cumulative metric logic.
- Task statuses moved only to In Progress/In Review; **zero new tasks are Verified without integrated test evidence**.
- Conflict C-001/C-002/C-004/C-005/C-008/C-015 moved to In Review, not Closed. C-003/C-006/C-007/C-009/C-010/C-011/C-012/C-013/C-014/C-016 remain open.

- Added GitHub Actions deployment archive carrying `vendor/` to reduce Hostinger runtime setup; actual Actions run/Composer dependency resolution pending.

## 2026-11-12 — Security and deployment hardening increment

- First-run private interactive setup, release CI must test Laravel + worker before archiving and embeds resolved Composer lock.
- Catalog pricing is now fetched and verified by the server (not asserted by worker), missing LinkedIn scope metadata fails closed, and doctor checks actual enabled model prices.
- Admin-only, audited reconciliation of uncertain provider outcomes; automatic duplicate post retries remain forbidden.
- Browser operator forms cover source approval/fetch, research verification, content approval/media/schedule, manual exports, analytics and OAuth.
- Windows user-level worker installer + protected worker env config, duplicate Python `media_result` removed and source checker guards duplicate definitions.
- All touched task IDs remain `In Progress`/`In Review`, not Verified. No claim of executed Laravel/MySQL tests or live OAuth.

### 2026-11-12 — Hostinger database engine compatibility
- Hostinger documentation currently identifies MariaDB for shared/web hosting. Added MySQL 8.0 and MariaDB 11.4 GitHub CI integration jobs and a MariaDB release gate.
- `phpunit.database.xml` uses disposable CI-only credentials; no production credentials are committed.
- T031 marked In Progress, decision D-016 recorded. Feature suite must actually pass before gate verification.


### 2026-11-12 — MariaDB CI boot readiness fix
- MySQL 8 matrix passed on commit `6f8f741`; MariaDB service initialization was still in progress.
- Switched MariaDB health probing to the image's supported `healthcheck.sh --connect --innodb_initialized` in app CI and release workflow; MySQL matrix retains its `mysqladmin` probe.
- Added structural regression checks and left real-host readiness and release gates unverified until rerun evidence is available.


### 2026-11-12 — Worker liveness and audited Telegram outbox
- Worker renewal/heartbeat interval reduced to 60s against the explicitly requested 900s lease; added unit regression test.
- Implemented atomic claim and stale-claim recovery for Telegram outbox, cautious status notifications and safety tests; no secrets sent.
- Publishing updates emit durable events for reconciliation/success/manual-ready.
- No live Telegram or platform deployment claim: Laravel tests must pass in GitHub CI and live API integrations are still owner-gated.


### 2026-11-12 — CI first-install and upgrade smoke gate
- Release workflow now extracts the built archive and executes `scripts/install.sh` twice with disposable MariaDB root permissions, then verifies route migrations, admin bootstrap and doctor readiness.
- The artifact upload step is after the smoke-install gate; no completion status changes until that GitHub run finishes successfully.


### 2026-11-12 — Deployment dotenv parsing parity
- Use dependency-provided `Dotenv::parse` for deployment database credentials; add an escaped-password round-trip test to the Laravel suite.
- CI release smoke-install on fresh MariaDB is required before publishing an archive; no real Hostinger authorization is assumed.


### 2026-11-12 — LinkedIn single-image adapter and evidence gate
- Implemented official Images API initialization/upload/readiness and Posts API media payload for single PNG organization publishing.
- Rejects provider-controlled upload URLs unless on strict `www.linkedin.com/dms-uploads/` HTTPS allowlist; verifies local brand asset checksum and PNG type, and disallows redirects.
- Added mocked successful media-post/failed unsafe URL regression tests and pre-schedule asset checks.
- T098 is In Progress, not verified: LinkedIn video/document/real OAuth and provider media API integration remain outstanding.

- **2026-11-12 · offline-first fallback · pending CI:** Add no-AI manual draft creation, role/brand isolation, duplicate prevention, admin review gate and feature tests. This improves G06 but does not certify G06/G10/G11 or close external publishing/deployment blockers.
