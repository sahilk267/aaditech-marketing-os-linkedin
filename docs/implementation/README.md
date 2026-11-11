# Implementation control center

**Canonical planning docs for [https://github.com/sahilk267/aaditech-marketing-os-linkedin](https://github.com/sahilk267/aaditech-marketing-os-linkedin)**. Baseline `9c547ae76fcd3f64793112b0c89a3453dac3ea31` on 2026-11-11. This folder defines all release gates and task evidence; it does not imply code was verified live. **Read `MASTER_IMPLEMENTATION_PLAN.md` before each implementation turn.**

1. `MASTER_IMPLEMENTATION_PLAN.md` — scope, sequencing, acceptance, hard stops.
2. `TASK_TRACKER.csv` — 146 atomic tasks, editable status/evidence/owner.
3. `GATE_TRACKER.csv` — 12 dependency-gated milestones.
4. `MODULE_REGISTRY.csv` — 19 canonical component IDs.
5. `REQUIREMENT_TRACEABILITY.csv` — 40 SRS clusters mapped to tasks.
6. `E2E_TEST_MATRIX.csv` — 40 test scenarios; evidence pending.
7. `CONFLICT_REGISTER.csv` — 16 known collisions/gaps/risks.
8. `DECISION_REGISTER.csv` — 14 decisions and owner-blockers.
9. `PLATFORM_MATRIX.csv` — 9 channel capabilities and safe fallbacks.
10. `IMPLEMENTATION_PROTOCOL.md` — mandatory agent/engineering process.
11. `validate_tracker.py` — structural checker. Run in CI.

**Important:** Excel workbook is a secondary readable view; synchronize edits into these CSVs before merging. `Verified` is forbidden without independent test/review evidence.


## 2026-11-12 canonical app update
Canonical runtime is now the **repository root**. All task status changes remain unverified until framework and live E2E evidence is produced. See `../RELEASE_READINESS.md` and `../DEPLOYMENT_RUNBOOK.md`. Do not execute historical overlay scripts against this root.
