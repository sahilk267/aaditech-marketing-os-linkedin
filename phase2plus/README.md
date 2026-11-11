# Aadi Marketing OS — Phase 2–9 Implementation Overlay

**Source-only development package. No production deployment, credential installation, or account linking has occurred.**

The Phase 1 code is under `phase1/overlay` in the existing repository; the new modules live under `phase2plus/overlay` and `phase2plus/worker`. Both are overlays for a **new, inspected Laravel 13 project**, not a complete vendored Laravel installation. Phase 2–9 features are implemented as functional source paths and extension points, not a claim that every external social API has approved write access.

## Implemented source

- Phase 2: MySQL durable queue, explicit `leased/running/succeeded/retry_wait/dead_letter`, atomic claim, bounded lease, failure backoff and durable outbox, worker API.
- Phase 3: fail-closed OpenRouter free-model catalog/selector, persistent worker quota counter, local Ollama fallback and strict JSON parsing.
- Phase 4: public HTTPS source registry, robots checks, DNS-pinned safe fetches, extracts stored as **unverified** until human verification.
- Phase 5: content job queue, platform variants, claim-to-research evidence links, human approval and high-risk brand gates.
- Phase 6: original editable SVG creatives, backend media checks and optional local FFmpeg packaging function. No generative-video claim.
- Phase 7: LinkedIn organization-text-only official Posts API behind OAuth + explicit permission/approval gates. All other platforms use `manual_ready` packages; unclear LinkedIn outcomes require reconciliation, never blind retries.
- Phase 8: manual metric input with provenance, week-level brand reports, missing metrics never imputed as zero.
- Phase 9: strict roles, brand scoping, secret redaction, job and OAuth tests, operations runbook, GitHub CI scaffolding and backups guidance.

## Development installation (later, on an authorized compatible environment)

1. Create a **fresh Laravel 13 skeleton** on PHP 8.3+ with required extensions, Composer and a local or staging MySQL database.
2. Apply `phase1/scripts/apply_overlay.py --target /path/to/laravel` in dry-run mode, inspect conflicts, then `--confirm` only on a newly inspected skeleton.
3. Dry-run `phase2plus/scripts/apply_overlay.py --target /path/to/laravel` and then explicitly `--confirm`. This script backs up overwritten files.
4. Add the Phase 2 worker Python package and `.env.worker` locally (never commit credentials).
5. `php artisan migrate --force`, create admin, seed brands, issue worker credential, `php artisan test`, `php artisan aadi:maintenance` (only after configuration).
6. Run desktop worker `python -m amo_worker.main --once` in staging before enabling `--forever` and the scheduler.

Detailed limitations and testing evidence are in `docs/PHASE2PLUS_STATUS.md`; operational procedure in `docs/RUNBOOK.md`. Social permissions and publisher versions must be checked against current official docs at rollout.
