> **2026-11-12 implementation update:** canonical runnable source moved to repository root; historical overlay directories are now read-only references. Read [Release readiness](../RELEASE_READINESS.md) before interpreting any source-level status as production ready.

# Aadi Marketing OS — Master Implementation Plan

**Planning baseline:** 2026-11-11 (Asia/Kolkata)  
**Plan version:** 1.0.0  
**Source repository:** https://github.com/sahilk267/aaditech-marketing-os-linkedin  
**Audited commit:** `9c547ae76fcd3f64793112b0c89a3453dac3ea31` (149 tracked files observed via GitHub tree)  
**Plan status:** **Proposed for execution; no phase claimed production-verified.**

> This document is the execution contract, not a guarantee of zero defects. “No missing/duplicate/conflicting implementation” is enforced by traceability, static checks, single ownership, contract tests and signoff gates. Any uncovered dependency or test failure must block release rather than be hidden.

## 1. Non-negotiable product constraints

- Hostinger Business shared hosting: Laravel + MySQL; **no Redis, Docker, VPS, n8n or paid AI APIs**. No assumption of persistent cloud processes.
- CPU-only local desktop: Python worker over outbound HTTPS; OpenRouter **verified-free-only** with hard cost guard; Ollama local fallback; FFmpeg for permitted media templates.
- Four brand-separated profiles: Aadi Tech Solutions, Overseas Job Placement, AadiPro, ToolboxGalaxy. AadiPro offerings are **unknown until verified**.
- Public sources only where permissible. Honor robots, site terms, rate limits, copyright and source freshness. High-risk employment/immigration claims require stronger evidence and accountable human approval.
- Official platform APIs + authorized credentials **only**. No browser bots, session-cookie publishing, private-profile scraping, fake engagement or mass unsolicited outreach.
- “Fully automatic” is conditional: approved account + approved platform app + policy/quality/asset gates + verified API support. Else `manual_ready`.
- X auto publishing excluded absent verified free official access. Unknown/paid pricing or permission failure = deny, not a workaround.

## 2. Reality baseline / no completion inflation

The existing repository contains Phase 0, Phase 1 and Phase 2+ **source overlays**. They are not one deployable Laravel app; full Laravel/MySQL feature testing, Hostinger/desktop preflight, real AI/provider interaction, official OAuth and live posting are **not verified**. The baseline PHP/Python syntax/unit source checks are useful but insufficient for production signoff. Original source SRS: `phase0/docs/SRS.md`; earlier status: `phase1/docs/PHASE1_STATUS.md` and `phase2plus/docs/PHASE2PLUS_STATUS.md`.

Four **confirmed** overlapping overlay paths have different content: `routes/api.php`, `routes/web.php`, `routes/console.php`, `resources/views/brands/show.blade.php`. G01 must merge them into one canonical application. More confirmed/risks are tracked in `CONFLICT_REGISTER.csv`.

## 3. Tracking system — one source of truth

| File | Authority / how to use |
|---|---|
| `MASTER_IMPLEMENTATION_PLAN.md` | Design, sequencing, acceptance and implementation policy |
| `MODULE_REGISTRY.csv` | Exactly 19 logical components and canonical ownership |
| `TASK_TRACKER.csv` | Atomic tasks, stable IDs, dependencies, evidence, owner and status |
| `GATE_TRACKER.csv` | Phase prerequisites, immutable exit criteria and signoff |
| `REQUIREMENT_TRACEABILITY.csv` | SRS requirement → module → task → gate |
| `E2E_TEST_MATRIX.csv` | Executable scenarios and acceptance evidence |
| `CONFLICT_REGISTER.csv` | Existing collisions, risk owners and required closure tests |
| `DECISION_REGISTER.csv` | Open product/config choices + fail-closed defaults |
| `PLATFORM_MATRIX.csv` | Explicit supported/unsupported capabilities, approvals |
| `IMPLEMENTATION_PROTOCOL.md` | Mandatory run process for every Codex/engineering turn |
| `validate_tracker.py` | Mechanical duplicate, dependency, coverage and status checks |

Keep all CSVs in Git; the downloadable Excel tracker is a user-friendly mirror and **not the authoritative status database**. When an Excel row changes, sync it back via a reviewed CSV commit or report the mismatch. Task IDs **must never be reused** after deletion (archive them). No task may be marked `Verified` without reproducible evidence and independent review.

## 4. Phase-by-phase implementation sequence

| Gate | Scope | Depends on | Exit gate (abbreviated) |
|---|---|---|---|
| `G00` | Baseline & scope governance | Baseline | SRS-to-code inventory, conflict list and frozen scope approved; repository baseline repeatable |
| `G01` | Unified Laravel bootstrap | G00 | Single application boots; routes, commands, providers, migrations and tests discovered |
| `G02` | Identity, brand integrity & data | G01 | Policies, brand scoping, seeding, migrations and replay protections pass in MySQL |
| `G03` | Durable workflow + worker | G02 | Concurrent claims, crash recovery, leases, outbox and idempotency proven |
| `G04` | AI providers & CPU fallback | G03 | Free-only guard, bounded fallback, benchmark harness and contract tests pass; actual desktop benchmarking deferred to G11 |
| `G05` | Safe research & verification | G04 | Approved-source pipeline proven; high-risk unsupported claims cannot pass |
| `G06` | Campaigns & content lifecycle | G05 | Four brands produce distinct, reviewed platform variants with traceable provenance |
| `G07` | Creative/media factory | G06 | Deterministic template output and assets pass platform constraints and CPU tests |
| `G08` | Official publishing adapters | G07 | Each enabled connector passes contract tests; unsafe/unsupported remains manual_ready |
| `G09` | Analytics, alerts & dashboard | G08 | Metric meaning/provenance stable, UI operator flows and notifications pass |
| `G10` | System-wide E2E & security | G09 | Full regression + failure drills, security, backup restore and no-critical-finding gate pass |
| `G11` | Owner deployment & activation | G10 | Hostinger/desktop/OAuth preflight and controlled live trial proven; not implied by source-only work |


**Dependency policy:** Gates are ordered on purpose to prevent breaking downstream contracts. Internal parallel work may occur only behind stable DTO/schema contracts; a downstream gate cannot be marked Verified until upstream gates are Verified. External activation G11 can remain Blocked while code is being developed, but this must never be described as production readiness.

## 5. Acceptance criteria by functional workstream

### A. Application, security and data (G01–G02)

Canonical Laravel app with Composer lock and CI; routes/API consolidated; all four brands seeded; per-brand query and authorization boundaries; anti-replay worker auth; OAuth secrets encrypted; MySQL schema/index/transaction, migration rollback and backup-safe deployment. Validate UI session, CSRF, rate limit and permission roles using real MySQL feature tests.

### B. Durable orchestration (G03)

MySQL `queued -> leased -> running -> succeeded` and failure states; atomic claim, lease renewal, stale-owner fencing, constrained retries, dead-letter intervention, transactional outbox and idempotency. Python worker polls outbound HTTPS, runs bounded tasks, rotates logs and survives kill/network outage. Never retain a DB transaction during provider/API call. Test two simultaneous workers and uncertain external results.

### C. AI + evidence quality (G04–G06)

Free catalog discovery & TTL; verify price/capability **before** selecting provider; quota ledger and circuit breaker; on free 429/5xx, bounded retry then local Ollama; both unavailable => durable retry, never paid fallback. Every generated draft is schema validated with evidence lineage. Sources are public, allowlisted, robots/terms compliant, deduplicated and trust-tiered. Claim-to-source semantic checks and high-risk human review are independent from the model. Brand/catalog correctness and versions persist through edit, approval, revision and schedule.

### D. Creative and official distribution (G07–G08)

Versioned brand templates, SVG/raster/carousel/video output, image/audio rights metadata, checksum-based asset storage, platform media validation, CPU resource ceilings. Each social platform has separate OAuth/permissions matrix and adapter contract. LinkedIn text exists as source, not evidence of active publishing. Additional official adapters (LinkedIn media, Facebook, Instagram Professional, YouTube, Pinterest, Threads, TikTok) are planned; X excluded unless verified free. Any lack of permission or API eligibility produces manual-ready export. Ambiguous publish timeout is never blindly retried.

### E. Measurement and operations (G09–G11)

Metric dictionary documents units, sources, collection time, cumulative vs delta and missing-vs-zero. Normalize without pretending cross-platform measures are equivalent. Weekly reports and recommendations use only observed data. Operator dashboard handles queue, approvals, alerts, retries and reconciliation. Final gate includes tests, staging recovery, security review, backup/restore, performance, platform approval and controlled authorized trial.

## 6. Definition of Done and release blockers

A task can become **Verified** only when all pass:

1. Exact repository commit recorded; changes inspected against ownership map; no new unreviewed duplicate path/class/schema/endpoint.
2. Unit + integration + contract + regression + negative security tests for the changed behavior pass in CI/staging (not only PHP syntax).
3. All dependencies marked Verified; migrations are backward-safe with rollback/restore plan; event/outbox/lease behavior follows invariants.
4. Evidence stored as immutable CI run, test log, screenshot/report where appropriate; owner/reviewer and UTC timestamp recorded in tracker.
5. Docs, architecture decisions, API/schema and threat model updated together with code; security/brand policy rechecked.
6. Product acceptance script passes with realistic fixtures; external approval gates remain explicitly Blocked until real evidence exists.

**Hard stop / no-go:** any paid or unknown-cost AI route; unsafe source SSRF; missing brand scoping; OAuth secrets exposed; unauthorized publish; duplicate publish after timeout; high-risk unsupported claims; failing MySQL migrations; no backup/rollback; disabled or unavailable official account permissions; critical/high unresolved security findings.

## 7. Critical integration and conflict handling

- **Route collisions:** inspect the four overlapping Phase 1/2 paths; merge only after route/action snapshots. `php artisan route:list` must show no duplicated signature (`verb + path + action`). Do not apply overlays by overwrite in production.
- **Schema single ownership:** migration registry and indexes; one canonical table and lifecycle per business identity; migration up/down and seeded re-run tests required.
- **API/data contracts:** DTO/schema versioning; reject invalid worker payloads; keep provider model output as untrusted data; formalize error/retry taxonomy.
- **External publication:** publishing job carries approved immutable content version + media checksum + account scope; HTTP timeout enters `needs_reconciliation`; never retry blindly.
- **Analytics:** weekly snapshot aggregation must be metric-type aware; cumulative snapshots use last-as-of, deltas sum correctly; null ≠ 0.
- **Approval & evidence:** reference IDs alone are not proof that a claim matches a source; reviewer/semantic evidence must be traceable.
- **Quota consistency:** local SQLite worker usage and Laravel server usage must not be treated as independent spending authorities; hard guard should be central.
- **Version churn:** Laravel PHP floor, OpenRouter free pricing and LinkedIn supported REST versions/permissions must be rechecked at implementation/go-live, recorded in decision register.

## 8. Source-of-truth implementation workflow

**Before every coding change:** read this plan, current tracker, `CONFLICT_REGISTER.csv` and exact target files; fetch latest main; branch from current head; inspect existing implementation and tests; update/approve affected tasks; declare expected migration/API/schema impact.

**During implementation:** change the smallest relevant ownership area; prefer patches over rewrites; no duplicate services/routes/tables; run unit tests after every slice; guard official APIs and free-model conditions in code not comments; keep new features off until acceptance.

**Before merge:** run `validate_tracker.py`, PHP lint and PHPStan/format checks, Python lint/type/tests, MySQL migration/feature/concurrency suite, route & schema uniqueness scan, security/scenario regression; attach CI proof and review notes. Merge only with gates met and clean branch history. If environment unavailable, mark `Blocked` or `In Review`, never `Verified`.

**After merge:** synchronize task evidence, changelog, decisions, threat model, release/rollback instructions and dependency inventory; CI must fail on missing traceability or duplicate IDs. Maintain baseline version/timestamp.

## 9. Acceptance/test evidence expectations

`E2E_TEST_MATRIX.csv` includes brand separation, strict free AI, fallback, worker recovery, official publishing idempotency/reconciliation, research safety, media validity, OAuth revocation, analytics null-vs-zero, privacy-preserving Telegram alerts, restore, operations and controlled live trial. An in-scope task maps to a test reference `AUTO-Tnnn`; specific executable test paths are assigned while implementing. Task `acceptance` is the binding target; test source without a passing execution is NOT completion.

## 10. Owner-provided dependencies and safe defaults

The eight open production decisions from SRS §16 are tracked individually in `DECISION_REGISTER.csv`. The owner will configure hosting/accounts later. Engineering can implement contract-safe integration first, but cannot promise real official publishing, CPU quality or Hostinger compatibility in the absence of credentials/hardware/approval. These remain **externally blocked** and must not be substituted with unofficial APIs, fabricated brand data or secret requests in chat.

## 11. Scope coverage numbers (baseline, not progress)

- **19** original logical components (8 foundational and 11 expert-complexity components, a planning classification).
- **146** atomic implementation/verification tasks.
- **40** source requirement clusters traced to tasks and gates.
- **40** E2E / security / recovery acceptance scenarios.
- **16** confirmed conflict/risk entries (classified per row; not all confirmed bugs).
- **14** implementation or owner decisions, including the original 8 open items.
- **9** channel capability rows; enable only official supported authorized APIs.

The 19 components are architectural scope, not 19 finished modules. Current number of **production-verified** components is **0**, because full integrated production evidence is unavailable. This is an evidence statement, not a claim that all source is defective.

## 12. External documentation revalidation

Vendor facts are dynamic; check on every integration/activation, pin a verification date and URL in decision/evidence records:

- Laravel 13 server requirements: https://laravel.com/framework/docs/deployment
- OpenRouter free-model information and quota notes: https://openrouter.ai/collections/free-models
- LinkedIn official Posts API and scope requirements: https://learn.microsoft.com/en-us/linkedin/marketing/community-management/shares/posts-api
- LinkedIn version/permission migration notices: https://learn.microsoft.com/en-us/linkedin/marketing/community-management/community-management-api-migration-guide

**This plan must be updated through reviewed commits when external API policies change.**

## 13. Code-first delivery without pretending live setup is done

**Engineering gates G00–G10** can be advanced with deterministic local/CI/staging MySQL and mocked/contract-tested platform integrations. The owner does **not** need to install Hostinger/OAuth keys during the code-completion sprint. Actual Hostinger limits, desktop CPU/Ollama measurements, real paid/free account balances, page roles and provider approvals are assessed only in **G11**, and will remain `Blocked` until owner supplies those environments. The gate status must distinguish code/test readiness from live activation.

**Safe offline substitute:** mocked OpenRouter 429/timeout, simulated Ollama local response, fake LinkedIn response, fixture source pages and synthetic metrics. **Never** treat mocked evidence as proof of live provider permission, rate limit, CPU performance or successful external publication.

**Deliverable checkpoints:** G01 canonical Laravel app boot and migration; G03 durable worker; G06 approved four-brand drafts; G08 contract-verified official adapters/manual fallback; G09 operator-ready system; G10 staging full regression; G11 real-world activation. No key, secret or permission is needed to merely review this plan.
