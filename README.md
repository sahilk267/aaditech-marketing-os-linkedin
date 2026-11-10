# Aadi Marketing OS — LinkedIn & Multi-Brand Marketing Foundation

This repository contains the **Phase 0 preflight kit** and **Phase 1 Laravel source overlay** for Aadi Marketing OS. It is **not yet a runnable Laravel application**, and nothing here has been deployed to production.

## Scope

Four separated brands: Aadi Tech Solutions, Overseas Job Placement, AadiPro, and ToolboxGalaxy. Planned stack: Laravel + MySQL on Hostinger shared hosting, local Python desktop worker via outbound HTTPS, free-only OpenRouter with local Ollama fallback, FFmpeg media processing, and official authorized social-network publishing adapters. No paid AI calls, Redis, n8n, browser bots, or unofficial publishing endpoints.

## Source packages

- [`phase0/`](phase0/README.md) — architecture, full SRS, Hostinger/desktop preflight tools, CPU-only Ollama benchmark, and validation evidence.
- [`phase1/`](phase1/README.md) — Laravel 13-compatible **source overlay**, migrations, RBAC, four-brand policy/CRUD, session login, audit logs, worker registration/heartbeat, demo Blade dashboard, tests, Python prototype, and overlay installer.
- [`phase1/docs/PHASE1_STATUS.md`](phase1/docs/PHASE1_STATUS.md) — what is verified and what remains blocked.
- [`phase1/docs/PHASE1_NEXT_STEPS.md`](phase1/docs/PHASE1_NEXT_STEPS.md) — ordered validation and next steps.

## Installation boundary

**Do not deploy this repository root as a Laravel app.** Start from a newly generated Laravel 13 project with compatible PHP, Composer, required extensions, and MySQL, then dry-run `phase1/scripts/apply_overlay.py` against the inspected skeleton. Follow [`phase1/README.md`](phase1/README.md) for details.

No credentials or `.env` files belong in Git. Environment variable examples intentionally contain only blank or placeholder values. This initial repository push is source-only; full Laravel/MySQL integration tests, Hostinger access verification, desktop Ollama benchmark, and social OAuth approvals are still pending. Do not assume automated posting is enabled.