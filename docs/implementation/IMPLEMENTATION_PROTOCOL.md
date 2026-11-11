# Mandatory Implementation Protocol — Aadi Marketing OS

## Before a task
1. Open `MASTER_IMPLEMENTATION_PLAN.md` and select one `TASK_TRACKER.csv` task by stable ID. Never implement a feature with no task/requirement/test trace.
2. Fetch/inspect the current repository branch and related files; compare overlays and ensure no duplicate class/route/migration already exists.
3. Check all prerequisite gate statuses and decisions. If blocked by external permission/host, implement only safe mock/contract pieces and mark blocked.
4. Create an implementation plan and specify files to modify, migrations/rollbacks, contracts, threat-model implications, test cases, and expected behavior.

## During coding
5. Work on a feature branch with small commits, one canonical owning service/module. Respect four-brand scoping in every query; no bypassed policy.
6. Add negative tests before sensitive logic changes. Model output and source content are always untrusted data. Paid AI selection is denied pre-network.
7. Add versioned database migration rather than manually changing prod schema. Reconcile existing routes/views/bootstrap; never blindly overwrite.
8. Integrate artifacts with stable source/revision hashes and owner-reviewed claims; no silent publishing when official scopes are absent.

## Merge gates
9. Run `validate_tracker.py`, automated source scans, lint, PHP/worker unit and integration tests, MySQL concurrent/restart tests where applicable, all relevant E2E cases.
10. Verify acceptance criteria and attach evidence references (CI URL/commit/test log). If a test cannot run, use `Blocked` or `In Review`; never `Verified`.
11. Update docs, decision log, conflict closure, dependency inventory, release notes and rollback plan. Require reviewer approval and pass security + regression gate.
12. After merging, re-sync statuses. No undocumented hotfix, duplicate endpoint or hidden scope expansion.

## Status semantics
- `Not Started`: no implementation attempt in current canonical app, even if legacy overlay source exists.
- `In Progress`: change in active branch; evidence not complete.
- `Blocked`: missing dependency, permission, environment or failed acceptance.
- `In Review`: implementation proposed; tests/independent review pending.
- `Verified`: all definition-of-done evidence attached and reviewed, dependencies Verified.
- `Deferred`: owner-approved removal from release scope; written decision required and traceability retained. Never silently defer P0.

## Checklist for `Verified`
- Owner / reviewer recorded; commit SHA and CI log link documented
- Requirements and tests linked; all negative/security fixtures pass
- MySQL migrations and rollback verified if schema changed
- No duplicate paths/routes/services/schema or unresolved critical conflicts
- Worker recovery/idempotency and provider unknown-outcome behavior verified where relevant
- No paid model, unapproved social publish, copyright violation or unsupported claims
- Docs/changelog updated with exact current behavior and safe fallback

## Governance validation command
`python docs/implementation/validate_tracker.py docs/implementation`

The validator catches structural omissions; it cannot prove security or functional completeness. That requires the tests and evidence gates above.
