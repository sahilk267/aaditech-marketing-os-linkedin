# Database baseline — implementation not started

`SRS.md` section 8 specifies 19 tables: users, brands, brand_sources, research_items, campaigns, content_items, content_reviews, media_assets, social_accounts, publishing_jobs, analytics_snapshots, ai_providers, ai_models, ai_usage, workflow_jobs, workflow_events, worker_nodes, audit_logs, notification_logs.

The complete list above contains 19 tables. No migration has been run yet.

- Use MySQL-compatible Laravel migrations, InnoDB, FKs, indexes and UTC timestamps.
- Ensure every brand-owned query is restricted by brand context, with negative cross-brand tests.
- Encrypt credentials at application layer, never return them in API responses.
- `workflow_jobs` uses conditional atomic leases, `attempt_count` and expiration; `workflow_events` implements durable outbox.
- `publishing_jobs.idempotency_key` unique; timeout outcomes require reconciliation to prevent duplicates.
- Source-related evidence must retain trust tier and freshness metadata; explicitly verify `content_items` claim evidence before publication.
- Existing SRS has an unresolved edge: `research_items.source_id` references a brand source with potentially nullable `brand_id`. Resolve brand visibility rules in Phase 1/4 without weakening isolation.

**Gate:** No real schema is claimed until migrations and tests have run against a verified MySQL server.
