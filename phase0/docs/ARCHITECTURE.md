# Architecture baseline — Phase 0

Source of truth: `SRS.md`, sections 7 and 15. This document records the implementation boundaries, not yet working components.

```text
Hostinger Business (PHP + MySQL; capabilities to verify)
  browser -> Laravel dashboard / API / RBAC
             | brands | research | content | verification
             | AI model registry | publication adapters | analytics
             | MySQL workflow_jobs + events + publishing_jobs
             ^ HTTPS POST authenticated, worker initiated
             |
Local Windows desktop (not inspected yet)
  Python worker -> Ollama on 127.0.0.1:11434 (CPU only)
                -> FFmpeg for templated media
                -> OpenRouter free-only HTTPS, if selected
```

## Non-negotiable boundaries

1. Database state is authoritative; no Redis, VPS, n8n, Zapier or persistent server process requirement.
2. Worker **initiates outbound HTTPS**. Hostinger need not reach the desktop.
3. Jobs transition through queued -> leased -> running -> succeeded, or bounded failure states. Atomic leases and retry idempotency precede content automation.
4. Provider model metadata defaults to **not allowed** when price/free status is missing. No paid provider fallback.
5. Research HTML is untrusted input, never executable instructions. High-risk recruitment, salary and immigration claims require Tier A evidence/human review.
6. Platform permissions are not assumed. Disabled adapters return manual-ready packages.
7. Use UTC for persistence, explicit time-zone conversion at display and scheduling boundaries.

## Delivery dependency order

Phase 0 preflight -> Phase 1 identity/brands -> Phase 2 job leases/outbox -> Phase 3 AI gateway -> Phase 4 source provenance -> Phase 5 content -> Phase 6 media -> Phase 7 publish -> Phase 8 analytics -> Phase 9 hardening.

## Open architecture checks

- Hostinger actual PHP version/extensions, MySQL version, directory structure, cron frequency and CLI paths.
- Windows OS/CPU/RAM/disk, Ollama installation and CPU benchmark.
- Deterministic scheduler: prefer Hostinger cron when validated; local poller may enqueue/check due items only through authenticated, idempotent server-side operations.
- Reconciling unknown-outcome post submissions requires platform-specific support; otherwise stop in needs_reconciliation.
