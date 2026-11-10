# Phase 1 implementation plan (pending Phase 0 gate)

**Preconditions:** Verified Hostinger PHP/MySQL/cron support, desktop diagnostics, selected target Git repository/branch, Composer available in development, and Laravel skeleton installed/tested. No production secrets in source control.

## Ordered implementation tickets

1. **Laravel baseline:** initialize supported Laravel version using Composer, configure MySQL database drivers, authenticated web entrypoint, `APP_DEBUG=false` in production and PHP test tooling. Prove smoke test.
2. **Roles/auth:** implement owner/admin, marketing editor, analyst and operator role model with Laravel policies/middleware; fail closed and prevent staff credential exposure.
3. **Brands:** `brands` migration/model/CRUD/service/policies, slug and configuration JSON validation, brand profile seed data, audit entries on edits. Four profiles only; AadiPro offerings stay unspecified.
4. **Brand isolation:** associate brand ownership to content entities and authorisation context; deny cross-brand read and mutation. Include IDOR tests, policies and scoped query helpers.
5. **Audit:** append-only `audit_logs` table, sanitized metadata, actor tracking, no soft delete of audit events.
6. **Dashboard shell:** password-protected navigation, four brand cards, role-aware controls. Use simple server-rendered Blade and built assets suitable for shared hosting.
7. **Worker registration/heartbeat foundation:** `worker_nodes` migration, signed/revocable worker credentials, HTTPS-only endpoints and heartbeat persistence; defer leases to Phase 2.
8. **Deployment checks:** tests, `php artisan migrate --force` procedure, asset build, cache warmup and secure `public/` document root, rollback plan.

## Test set

- Auth: unauthenticated requests blocked, inactive users denied, roles restricted.
- Brand separation: users cannot view/edit another brand without permission.
- Audit: create/update/delete config operations logged without secrets.
- Worker: invalid signature, replayed nonce, unauthorized job capability rejected; heartbeat persists minimal metadata.
- DB: migrations work on target MySQL version and rollback works in staging.
- Deploy: only `public/` exposed and `.env` cannot be fetched.

## Exit evidence

A phase completion report must enumerate changed files, exact automated test command/results, deployment commands, security review and known gaps. Do not call Phase 1 complete based on generated code alone.
