# Worker API contract — draft, no endpoints deployed

Base: `/api/v1/worker` over HTTPS. All endpoints require an authenticated, revocable worker identity with capabilities.

| Method | Endpoint | Contract focus |
|---|---|---|
| POST | `/register` | worker identity and capabilities |
| POST | `/heartbeat` | health, version, timestamp |
| POST | `/jobs/claim` | atomically lease eligible job |
| POST | `/jobs/{id}/start` | validate lease owner and expiry |
| POST | `/jobs/{id}/complete` | idempotent completion |
| POST | `/jobs/{id}/fail` | bounded retry or dead letter |
| POST | `/events` | durable event intake |
| GET | `/config` | safe non-secret config |
| GET | `/ai/models` | approved model registry, verified-free only |
| POST | `/ai/usage` | usage and rate-limit ledger |

**Shared invariants:** TLS, nonce/timestamp replay defense, rate limits, DTO validation, correlation IDs, no secret echoes, lease ownership checks, audit events. Workers cannot claim arbitrary job types. See `SRS.md` section 9.

Decide request/response schemas, error codes, lease TTL and heartbeat frequency in Phase 2 with tests; avoid inventing production values in Phase 0.
