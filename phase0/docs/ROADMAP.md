# Delivery gates

From `SRS.md` section 13. Every phase requires inspection, plan, implementation, migration/tests where applicable, documentation and evidence-based completion report.

| Phase | Deliverable | Exit gate |
|---|---|---|
| 0 | Preflight, repo baseline, Laravel skeleton | Verified target environments and runnable app skeleton |
| 1 | Auth/RBAC, four brands, audit and worker heartbeat | Brand isolation demonstrated |
| 2 | DB job leases, Python worker, durable events | Restart recovery; no duplicate side effects |
| 3 | Free-only AI router + Ollama fallback | Paid models rejected before request |
| 4 | Allowed public-source research and provenance | No unsupported high-risk claim passes |
| 5 | Platform-adapted draft content | Week of distinct drafts across brands |
| 6 | Template media and FFmpeg | Valid CPU-generated assets |
| 7 | OAuth + official publishing adapters | Authorized test post per enabled adapter |
| 8 | Analytics and reporting | Measured vs manual vs unavailable distinguished |
| 9 | Security/restore/outage testing | All P0 tests pass |

`PHASE1_IMPLEMENTATION_PLAN.md` defines the next phase only; don't implement later phases prematurely.
