# Exact next steps / Codex continuation task

Use the provided Aadi Marketing OS SRS and `README.md`. Do not treat static checks as application tests. Do not use n8n, Redis, paid AI APIs, unofficial publishing endpoints, or browser automation.

1. Read `docs/SRS.md`, `docs/PHASE1_STATUS.md` and the architecture documents.
2. Confirm intended Git repository and branch, Hostinger PHP/MySQL/cron, and Windows worker preflights without touching production. If these are not available, work only in isolated staging.
3. Install PHP 8.3+ with required Laravel extensions and Composer, initialize **a new Laravel 13 application** and use `scripts/apply_overlay.py` dry-run before explicit apply.
4. Inspect every generated/overwritten stock skeleton file and fix integration mismatches; do not overwrite an unrelated project.
5. Run migrations/seeders and PHPUnit tests under local SQLite or MySQL, then staging MySQL. Log actual commands and test evidence.
6. Inspect security: cross-brand IDOR, inactive sessions, restricted CLI commands, DB constraint integrity, worker replay checks, token rotation, TLS proxy recognition, and audit immutability.
7. Run actual Windows desktop registration and heartbeat against staging HTTPS with revocable test credential.
8. Build a phase completion report with exact changed files, commands, results, remaining blockers and deployment/rollback steps. Only mark Phase 1 complete if all exit tests pass.

**Then and only then** proceed to Phase 2: database-backed leases/claim/start/complete/fail, retry/backoff, event outbox, replay-safe idempotency and resilient Python worker loops.
