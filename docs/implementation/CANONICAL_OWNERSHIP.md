# Canonical component ownership — working registry

This document maps each of the 19 SRS components to its **single current runtime owner**. The archived phase0/phase1/phase2plus overlays are **not installed** by the canonical Laravel app. A path here is an ownership anchor, not a claim that every component feature is complete. Recheck when refactoring.

| ID | Runtime owner | Key integration boundary |
|---|---|---|
| M01 | app/Http/Controllers; resources/views; bootstrap | Browser session and Blade dashboard |
| M02 | routes/web.php; routes/api.php; app/Http/Controllers | Authenticated /api/v1 contracts |
| M03 | app/Http/Controllers/AuthController.php; app/Models/User.php | Session, roles, brand membership |
| M04 | app/Models/Brand.php; app/Models/Campaign.php | Brand IDs on all mutable business records |
| M05 | app/Domain/Research; app/Models/BrandSource.php | Approved public HTTPS source access |
| M06 | app/Domain/Content; app/Models/ContentItem.php; app/Http/Controllers/ContentRevisionController.php | Content review and revision authorization |
| M07 | app/Domain/AI; worker/amo_worker/ai.py | Verified-free-only routing |
| M08 | app/Domain/Workflow/JobEngine.php; app/Console/Commands/AadiTick.php | Database leases, attempts and scheduled work |
| M09 | app/Models/WorkflowEvent.php; app/Console/Commands/AadiOutbox.php | Durable outbox events |
| M10 | app/Domain/Content/ContentPolicy.php; app/Models/ResearchItem.php | Evidence and human verification controls |
| M11 | app/Models/MediaAsset.php; app/Http/Controllers/OfflineCreativeController.php | Checksum-bound private assets |
| M12 | app/Domain/Publishing/PublishService.php | Publication fencing and reconciliation |
| M13 | app/Domain/Publishing/PlatformRegistry.php | Official adapters or manual-only fallback |
| M14 | app/Domain/Analytics/WeeklyReport.php; app/Models/AnalyticsSnapshot.php | Metric semantics and reports |
| M15 | app/Console/Commands/AadiOutbox.php | Outbox-backed notification delivery |
| M16 | app/Models/AuditLog.php; docs/implementation | Audit events, change control, readiness |
| M17 | worker/amo_worker/main.py; app/Http/Controllers/WorkerJobsController.php | Authenticated outbound HTTPS worker jobs |
| M18 | worker/amo_worker/ai.py | Local Ollama inference and CPU budget |
| M19 | worker/amo_worker/media.py | CPU-limited FFmpeg/media subprocess utility |

**Collision policy:** Do not implement a duplicate live controller, migration or adapter inside an archived overlay. Every new endpoint must appear once in canonical routes. Preserve public contract compatibility and add negative brand/role tests before replacing a service. The canonical file inventory and migration uniqueness are enforced by scripts/check_source.py, while CI / actual database tests remain authoritative for runtime behavior.

**Incomplete:** Full contract descriptions, independent architecture signoff, and actual runtime ownership of every implied feature still require review. This registry does not close G00 or G01.
