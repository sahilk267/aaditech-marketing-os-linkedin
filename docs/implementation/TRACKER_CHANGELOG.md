# Tracker change history

- **2026-11-11 · v1.0.0 · planning baseline:** 19 modules, 146 tasks, 12 release gates, 40 SRS requirement groups, 40 E2E tests, 16 conflict/risk entries, 14 decisions, 9 channel rows. No production/completion claims. Baseline source commit `9c547ae76fcd3f64793112b0c89a3453dac3ea31`.

Future changes must record date, author/reviewer, affected IDs, reason, status/evidence changes and GitHub commit. Never silently drop an unmet P0 requirement or reassign an existing stable ID.

## 2026-11-12: Unified application / safe first-deploy increment
- App canonical Laravel root added; old overlays remain archival; 4 overlap files reconciled to one runtime path.
- Automated conditional MySQL DB creation + mandatory migrations, four-brand seed, secure owner bootstrap, readiness diagnostic.
- Worker scheduler fallback and lease renewal, PNG/carousel renderer, latest cumulative metric logic.
- Task statuses moved only to In Progress/In Review; **zero new tasks are Verified without integrated test evidence**.
- Conflict C-001/C-002/C-004/C-005/C-008/C-015 moved to In Review, not Closed. C-003/C-006/C-007/C-009/C-010/C-011/C-012/C-013/C-014/C-016 remain open.
