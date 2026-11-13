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

- **2026-11-12 · manual package mode · pending CI:** Immediate approved-content manual-ready endpoint/UI, stable duplicate key, event and access-control feature tests. No live provider credentials required; G08 remains partially implemented for official automated adapters.

- **2026-11-12 · manual media package · pending CI:** Secure SVG/PNG/carousel downloads with checksum/size validation, same-brand authorization and test cases. G07/G08 remain pending official E2E verification.

- **2026-11-12 · Facebook Page text adapter · pending CI:** Official Meta Graph API v26-compatible text adapter, scope/approval gate, idempotent uncertain outcome handling and contract tests. T099 in progress, not complete; actual OAuth and Meta app approval pending.

- **2026-11-12 · Facebook OAuth flow · pending CI:** Admin-only Page OAuth with state anti-replay, permissions verification and encrypted token persistence; operator browser form and contract tests. Meta app approval and live Page validation remain G11 blockers.

- **2026-11-12 · Meta implementation tracking · CI pending:** T099 moved to In Progress with explicit source/test evidence and no live certification. Doctor reports optional Facebook OAuth configuration readiness separately from core environment readiness.

- **2026-11-12 · offline SVG rendering · pending CI:** Adds a no-worker PHP creative generator and UI; tests for safe escaping, brand authorization, checksum-based persistence. G07 improves, high-end video and professional design templates remain open.

- **2026-11-12 · approval snapshot / duplicate guard · pending CI:** Added `PublicationIntegrity` fingerprint for content/media, fail-closed publishing on missing/stale seal, deterministic scheduled keys, manual package stale-check and Laravel tests. T089/T094/T107 advanced to In Progress; G08/G10 remain unverified for live race/recovery testing.

- **2026-11-12 · E2E offline journey · pending CI:** Add full manual draft → approval → SVG → manual package Laravel feature regression with no external network calls. E2E-032 has source test evidence; staging/browser verification and complete AI-based journey still open.

- **2026-11-12 · CI evidence reconciliation:** Application CI 43216301822 passed for approval snapshot/deduplicated schedules; Windows portable worker workflow 43217292345 passed and produced artifact 13942804382 after Windows SQLite handle fix. Tasks T017/T031/T042/T089/T094/T107/T122 updated with narrowly scoped evidence; no gate was marked Verified. E2E-032 retains Not Run because full browser/research/report journey is not yet proven.

- **2026-11-12 · per-brand readiness signals · pending CI:** Added capability indicators to the existing overview dashboard and tests for role/brand isolation and credential redaction. T115/T118 source progress recorded, not operational acceptance.


## 2026-11-13 — Governance evidence reconciliation and content revision
- G00 task IDs T002-T008 reviewed against live canonical ownership, route/migration checks, source and traceability documents. Stale Not Started statuses changed only to In Progress or In Review; T001 remains Not Started pending accountable owner signoff.
- T072 started with safe same-brand PATCH revisions, auditing, asset invalidation, and revoked scheduled/manual posts. Independent concurrency/real platform tests pending. E2E-018 remains Not Run and C-014 remains In Review.
- No tasks have been declared Verified from source alone. Status changes are implementation tracking, not full acceptance.

- **2026-11-13 · identity and brand role reconciliation:** T020/T021/T022/T026/T027 move from stale Not Started to In Progress based on existing canonical middleware/controllers and expanded AuthenticationTest, BrandAccessTest and ManualDraftTest negative tests. E2E-002/003/004 retain Not Run until full scenario execution and production policy coverage. No task marked Verified.


## 2026-11-13 — Claim-level evidence attestation implementation
- T062/T063/T064/T067 moved to In Progress. Added ClaimEvidenceGate and ClaimAttestationController with draft citations, admin verdict, excerpt presence check, source/body hash binding and fail-closed send-time validation.
- ClaimAttestationTest.php negative/positive cases passed in GitHub Actions application CI run 43403634588. C-006 is In Review, not Closed. E2E-016 remains Not Run with a PHP test evidence link; semantic entailment without accountable human judgment is not done.
- Added CLAIM_REVIEW_CONTRACT.md with routes, limitations and strict reapproval behavior. No hosting credentials, paid AI model or unsupported social adapter introduced.


## 2026-11-13 - Audited research verification and freshness lifecycle
- Admin-only research verify/reject now requires stored excerpt, written rationale, explicit acknowledgment and audit row. ResearchReviewAuditTest.php passed Laravel CI 43404303368.
- ResearchFreshnessGuard expires old research at maintenance and tick; direct content and generation validation also checks max age if cron is unavailable. Default 7 days for Overseas Job Placement and 90 days for other brands; shorter owner override permitted. Direct freshness CI pending.
- T056/T060/T061/T066 moved to In Progress, not Verified. E2E-015/016 remain Not Run. Source author/history, semantic verification and live-provider evidence remain open.
