# Aadi Marketing OS

## Codex-Ready Software Requirements Specification, Architecture & Delivery Plan

**Version:** 1.0\
**Date:** 2026-11-03\
**Status:** Baseline specification for implementation\
**Deployment target:** Existing Hostinger Business shared hosting +
always-on local desktop worker\
**Budget constraint:** ₹0 additional AI API spend; no paid AI API calls;
no n8n\
**Primary AI:** OpenRouter free models\
**Fallback AI:** Ollama on local desktop\
**Backend:** Laravel + MySQL\
**Worker hardware:** Intel Core i5 10th Gen desktop, 16 GB RAM, no
NVIDIA GPU

------------------------------------------------------------------------

# 1. Executive Summary

Aadi Marketing OS is a self-owned, scratch-built marketing operations
platform for four brands:

1.  Aadi Tech Solutions
2.  Overseas Job Placement
3.  AadiPro
4.  ToolboxGalaxy

The platform will research approved public sources, plan campaigns,
create brand-specific content, render reusable creative assets, validate
content, schedule posts, publish through authorized official platform
APIs, collect available analytics, and recommend improvements.

The system must not depend on n8n, Zapier, Make, paid AI APIs, Redis, a
VPS, or an always-on cloud worker. It must use a Laravel application and
MySQL on the existing shared hosting destination, with a local Python
worker that polls the Laravel API over outbound HTTPS. The local desktop
runs Ollama as the AI fallback and FFmpeg for media processing.

**Critical limitation:** OpenRouter free model availability, quotas and
provider capacity can change. The application must query/configure
current free models rather than hard-code a model assumption. The system
must never silently fall back to a paid model. If OpenRouter free
capacity is exhausted or unavailable, use Ollama; if both fail, queue
the task for retry.

**Publishing limitation:** "Fully automatic" means automatic only for
platforms/accounts where the user has completed official OAuth
authorization, the relevant platform app has required
permissions/approval, and the content passes configured policy gates.
The system must not use browser bots, session-cookie automation,
scraping behind login, fake engagement, or unofficial publishing
endpoints. Unsupported channels remain in a "ready-to-publish" queue for
native/manual publishing.

------------------------------------------------------------------------

# 2. Goals and Non-Goals

## 2.1 Goals

-   Manage four brands from one dashboard with strict tenant-like brand
    separation.
-   Build a custom workflow/task execution engine in Laravel.
-   Use OpenRouter free models as primary and Ollama as fallback.
-   Run without paid AI API usage.
-   Run on the specified no-GPU desktop with 16 GB RAM.
-   Store jobs and state durably in MySQL.
-   Support research provenance and evidence-backed content.
-   Generate platform-specific text, image briefs, static creatives and
    video packages.
-   Publish only through official, authorized platform APIs.
-   Track publication state, failures, retries and analytics.
-   Provide daily and weekly reports.
-   Recover cleanly after worker restart, network loss or API failure.
-   Keep all source code and data under the owner's control.

## 2.2 Non-goals for MVP

-   A public multi-tenant SaaS for external customers.
-   Paid advertising campaign management or ad spend.
-   Automated mass outreach, auto-DMs, auto-follow, auto-like or
    auto-comment.
-   Scraping authenticated social networks or bypassing access controls.
-   Guaranteed API access or automatic publishing on every social
    platform.
-   Training/fine-tuning an LLM.
-   High-end AI video generation on CPU-only hardware.
-   Autonomous publication of legally, financially, employment- or
    immigration-sensitive claims without evidence/policy checks.
-   Using ChatGPT Go as a programmatic API. ChatGPT Go is a
    human-operated assistant subscription; OpenRouter and Ollama are the
    programmatic model providers.

------------------------------------------------------------------------

# 3. Confirmed Infrastructure and Constraints

## 3.1 Hosting

**Shared hosting destination** - Existing Hostinger Business plan. -
Laravel web application and MySQL database. - No assumption of root
access, Docker, Redis, persistent daemons or unrestricted cron. - Use
the hosting provider's supported PHP version and database version after
preflight checks. - Laravel queue driver: `database`. - Laravel
cache/session: database or file, based on host compatibility. - Laravel
scheduler: hosting cron if available; otherwise a supported
low-frequency external trigger is not assumed. The local worker can poll
the API for due work, but schedule creation and due-time evaluation must
remain deterministic.

**Local desktop execution node** - Intel Core i5 10th Gen. - 16 GB
RAM. - No NVIDIA GPU. - Windows desktop assumed unless preflight
discovers otherwise. - Python worker, Ollama CPU inference, FFmpeg. -
Outbound HTTPS to the application host and OpenRouter. - No inbound
public ports required. - Worker must be installable as a Windows Service
or scheduled startup task. Service installation is a deployment concern
and must be documented.

## 3.2 Zero-additional-budget policy

-   Do not call paid AI models.
-   OpenRouter requests must target verified free model identifiers /
    free routing only.
-   Configure provider-side spend limits where available;
    application-side hard deny for any model not marked free.
-   No automatic paid fallback.
-   Ollama is local inference and has no per-request API fee.
-   Do not assume external search APIs are free. Start with permitted
    RSS feeds, official public pages, sitemaps, user-provided sources
    and manual source registration.
-   External platform APIs may be free to call but still require app
    registration, permission review, account eligibility, quota
    compliance or audit.
-   Hosting, electricity, internet, domain renewals and existing
    subscription costs are outside the incremental AI API budget.

------------------------------------------------------------------------

# 4. Users, Roles and Permissions

MVP roles:

  -----------------------------------------------------------------------
  Role                                Permissions
  ----------------------------------- -----------------------------------
  Owner/Admin                         Full access, settings, credentials,
                                      brands, publishing, users

  Marketing Editor                    Create/edit campaigns and content;
                                      approve content

  Analyst                             View analytics and reports; no
                                      publishing

  Operator                            Manage queues, retries and
                                      connector health; no credential
                                      reveal
  -----------------------------------------------------------------------

Single-owner deployment may initially use only Admin, but authorization
policies must support the roles above.

Security rules: - Deny by default. - Enforce server-side authorization
on every mutation. - Brand-scoped access on every content, campaign,
asset and analytics query. - No secrets rendered in HTML or API
responses.

------------------------------------------------------------------------

# 5. Functional Requirements

Priority labels: **P0 = required for MVP**, **P1 = next release**, **P2
= later enhancement**.

## 5.1 Brand Management (P0)

The system shall: - Create and edit four brand profiles. - Store brand
name, slug, website, logo, colors, voice, audience, geography,
languages, prohibited claims, approved CTA, social links and content
pillars. - Store brand-specific source allowlists and blocklists. -
Store brand-specific content templates and hashtag policies. - Prevent
content/assets/campaigns from being associated with the wrong brand. -
Maintain an audit trail for brand configuration changes.

Initial brand configuration:

### Aadi Tech Solutions

-   Objective: B2B IT services leads.
-   Topics: IT infrastructure, networking, cybersecurity awareness,
    servers, cloud, managed IT, support processes.
-   Audience: IT managers, business owners, operations managers,
    procurement.
-   CTA: consultation / contact / service enquiry.
-   Avoid unsupported security guarantees and fabricated case studies.

### Overseas Job Placement

-   Objective: legitimate job seeker and employer enquiries.
-   Topics: verified vacancies, career guidance, interview preparation,
    country-specific work information.
-   Audience: job seekers and employers.
-   CTA: view verified vacancy / submit enquiry.
-   High-risk policy: no invented vacancies, salary, employer, visa,
    work permit or guaranteed placement claims. Require source and
    freshness evidence. Sensitive immigration/legal claims require
    official sources and cautious wording.

### AadiPro

-   Objective: product/service awareness and qualified enquiries.
-   Actual product/service catalog is not yet fully specified. Keep
    configurable; do not invent offerings.
-   Content must only describe verified services/products in the brand
    profile/catalog.

### ToolboxGalaxy

-   Objective: product discovery, organic website traffic and repeat
    use.
-   Topics: online tools, productivity tutorials, developer utilities,
    tool demonstrations and feature updates.
-   CTA: open relevant tool page.
-   Store a verified URL for each promoted tool.

## 5.2 Audience and Campaign Planning (P1)

-   Define audience personas per brand and channel.
-   Create campaigns with objective, dates, content pillar, target
    audience, CTA and KPI.
-   Campaign planner proposes content slots.
-   Avoid over-posting and repeated topic saturation.
-   Campaign performance must be compared with baseline.

## 5.3 Research and Source Registry (P0)

The system shall: - Maintain a registry of approved public sources: RSS
feeds, official blogs, public documentation, official employment
portals, government sources, company websites and manually entered
URLs. - Store source URL, source title, source type, domain, publication
date (if known), fetch time, content hash, permission/robots decision,
trust tier and status. - Fetch only public pages where access is
permitted. - Respect robots.txt, website terms, rate limits and
copyright. - Not bypass login, paywalls, CAPTCHA, access controls or
technical restrictions. - Not scrape private social profiles or
authenticated feeds. - Extract short factual notes and metadata; avoid
storing/reproducing full copyrighted articles. - Store source evidence
attached to every factual content claim. - Deduplicate by canonical URL
and content hash. - Mark stale sources and require refresh for
time-sensitive content. - Support manual source entry if no automated
feed is available.

Source trust tiers: - Tier A: official government, employer, platform
documentation, official company announcement. - Tier B: reputable
industry publication or established organization. - Tier C: other public
source requiring corroboration. - Tier D: unverified social post or
user-generated content; not sufficient for high-risk claims.

For employment/immigration-related claims, require Tier A sources or
explicit human verification.

## 5.4 Content Planning and Generation (P0)

Content types: - Text post - Image post - Carousel/document outline -
Short video script - Long video outline - Infographic brief - Poll
concept - Blog outline - Repurposed content variant

Every content item must include: - Brand ID - Campaign ID (optional) -
Topic and content pillar - Audience - Platform target(s) - Language -
Content type - Hook/headline - Body/caption - CTA - Hashtags/keywords -
Source references - Claim list and evidence references - AI
provider/model metadata - Prompt/template version - Quality-check
results - Content status

AI output must be structured JSON validated against a versioned schema.
Invalid JSON must be repaired once with a constrained retry, then
rejected/queued for review.

Content must be original, brand-specific and platform-adapted. Do not
post identical text to all platforms without adaptation.

## 5.5 AI Model Router (P0)

Primary provider: OpenRouter free models. Fallback: Ollama local model.

Requirements: - OpenRouter-compatible chat completion client. - Model
registry table with provider, model ID, free status, context limit,
capabilities, availability, last checked time and priority. - Free-model
discovery/update task using the provider's documented model catalogue. -
Only models explicitly marked free may be selected. - No paid fallback,
even when free models fail. - Rate-limit and daily quota accounting. -
Retry with exponential backoff and jitter for transient errors. - Handle
401/403 as credential/configuration errors; do not loop retries. -
Handle 429 as quota/rate-limit and fall back to Ollama. - Handle
5xx/timeouts with bounded retry then fallback. - Record token usage and
estimated cost where returned. - Application hard guard: if a model is
not free or price metadata is unknown, reject it under zero-budget
mode. - Never send social OAuth tokens, passwords, private customer data
or confidential lead records to an LLM. - Redact secrets and personal
information from prompts/logs. - Store provider response metadata, not
unnecessary sensitive prompt content. - Circuit breaker for unhealthy
providers.

Routing policy: 1. Select highest-priority healthy free OpenRouter model
supporting the task. 2. Check internal daily request budget and rate
limit. 3. Call OpenRouter. 4. Validate HTTP status, response shape and
task JSON schema. 5. On quota/availability/provider error, call
configured Ollama model. 6. If Ollama unavailable or invalid, persist
retryable job. 7. Alert operator after configured failure threshold.

OpenRouter free-tier limits are subject to provider policy changes.
Never hardcode an assumed request/day quota as a permanent fact;
configure limits and read current provider documentation/account state.

## 5.6 Ollama CPU Backup (P0)

-   Install Ollama on the desktop.
-   CPU-only inference.
-   Begin with a small quantized instruction model suitable for 16 GB
    RAM; model selection must be benchmarked locally before production.
-   Do not assume large 14B/32B models will run acceptably.
-   Limit concurrent generations to 1 initially.
-   Set memory and timeout ceilings.
-   Use Ollama local HTTP API through the Python worker.
-   Health-check local endpoint.
-   Model pull and updates are manual/controlled; no automatic huge
    downloads.
-   Keep Ollama prompt templates aligned with OpenRouter templates.
-   Test output quality on representative brand tasks.

Recommended starting benchmark candidates should be selected from
current Ollama model catalogue at implementation time. Model name must
be configuration, not code.

## 5.7 Creative Studio and Media Factory (P1)

Zero-budget-first approach: - Generate SVG/HTML creatives using brand
templates. - Render PNG/JPEG using a supported local renderer. - Create
carousel slides from validated content blocks. - Use FFmpeg for video
assembly, captions, transitions and audio tracks that are
owned/licensed/royalty-free. - No claim of generative AI video
capability on the CPU-only desktop. - No use of copyrighted music or
images without rights. - Validate aspect ratio, resolution, file size,
duration and text legibility per destination platform. - Preserve
original asset and rendered variants.

## 5.8 Publishing Engine (P0 foundation; connector rollout P1)

Architecture: - Common `PlatformAdapter` interface. - Separate adapters
for each platform. - Platform account OAuth credentials stored
encrypted. - Store granted scopes, token expiry, account/page IDs and
permission health. - Publishing queue with idempotency key. - Validate
content/media against platform requirements before API call. - Save
platform post ID, permalink, response status and publication
timestamp. - Never assume API success until provider confirms it. -
Token refresh where supported. - If API access/approval is unavailable,
mark `manual_ready`, generate a downloadable/copyable package and do not
attempt unofficial automation.

Target adapters: - LinkedIn organization pages - Facebook Pages -
Instagram Professional accounts - YouTube - Pinterest - Threads -
TikTok - X is excluded from zero-cost auto-publishing unless free
official access is verified; no paid endpoint use.

A connector may be enabled only after a documented permission matrix and
test account validation. Platform policy and API availability must be
rechecked during implementation because they change.

## 5.9 Analytics and Growth (P1)

-   Collect only metrics available through authorized APIs or manual CSV
    entry.
-   Store metric snapshots with source and collection time.
-   Normalize platform metrics without pretending all definitions are
    equivalent.
-   Calculate post-level and brand-level performance.
-   Track UTM-tagged website clicks where feasible.
-   Do not infer unique reach if platform does not provide it.
-   Weekly report: top posts, weak posts, content themes, clicks, leads
    and recommendations.
-   Recommendations are advisory; system must not autonomously change
    sensitive claims or publish new strategy without policy validation.

## 5.10 Notifications (P0)

Telegram Bot API notifications for: - Worker offline/online - OpenRouter
quota exhausted - Ollama fallback invoked - Both AI providers
unavailable - Publishing success/failure - OAuth expiry/revocation -
High-risk content held - Daily report ready - Queue backlog above
threshold

Do not send access tokens or sensitive lead information in Telegram
messages.

------------------------------------------------------------------------

# 6. Non-Functional Requirements

  Area              Requirement
  ----------------- ----------------------------------------------------
  Reliability       Durable DB-backed jobs; restart-safe
  Idempotency       No duplicate publication on retries
  Security          Encrypted secrets; least privilege
  Performance       Dashboard common pages \< 3 seconds under MVP load
  Scalability       Four brands initially; modular brand growth
  Observability     Structured logs, job history, provider health
  Maintainability   Typed DTOs, service boundaries, tests
  Portability       Worker installable on Windows; no inbound port
  Cost              No paid AI model invocation
  Compliance        Official APIs and permitted public sources only
  Recovery          Worker resumes pending tasks after restart

------------------------------------------------------------------------

# 7. System Architecture

## 7.1 Logical components

1.  Laravel Web Application
2.  REST API
3.  Authentication and RBAC
4.  Brand and Campaign Service
5.  Research Source Service
6.  Content Service
7.  AI Gateway / Model Router
8.  Job Queue and Scheduler
9.  Event Dispatcher
10. Verification Service
11. Media Asset Service
12. Publishing Service
13. Platform Adapter Registry
14. Analytics Service
15. Notification Service
16. Audit and Observability Service
17. Python Local Worker
18. Ollama local inference service
19. FFmpeg media process

## 7.2 Deployment topology

**Hostinger shared hosting** - Laravel application - MySQL - Static
dashboard assets - Authenticated worker API - Database-backed job
queue - No Ollama - No long-running Python daemon assumed - No Redis -
No Docker dependency

**Local Windows desktop** - Python worker service - Ollama CPU runtime -
FFmpeg - Local temporary media directory - Outbound HTTPS polling -
Optional secure tunnel only if a specific callback requires it; prefer
outbound polling to avoid inbound exposure.

## 7.3 Job execution lifecycle

`queued -> leased -> running -> succeeded`

Failure states: - `retry_wait` - `failed` - `dead_letter` - `cancelled`

Worker lease: - Each job has `lease_owner`, `lease_expires_at`,
`attempt_count`. - Worker claims a job atomically using a DB transaction
and row locking where supported. - Lease expires if worker crashes. -
Another worker can reclaim after expiry. - Use short transactions; never
hold a DB transaction during an LLM/API request. - Use idempotency keys
for external publication.

## 7.4 Event model

Events: - `brand.created` - `source.discovered` - `source.fetched` -
`research.completed` - `campaign.created` - `content.draft_created` -
`content.validated` - `media.rendered` - `publication.scheduled` -
`publication.started` - `publication.succeeded` - `publication.failed` -
`analytics.snapshot_created` - `provider.degraded` -
`worker.heartbeat_missed`

Event delivery must be durable via an outbox table, not only in-memory
dispatch.

------------------------------------------------------------------------

# 8. Database Design

Use MySQL 8-compatible migrations. Prefer InnoDB, foreign keys, UTC
timestamps, indexes and JSON only for flexible metadata. Keep core
searchable fields relational.

## 8.1 Core tables

### `users`

-   id
-   name
-   email unique
-   password
-   role
-   is_active
-   last_login_at
-   timestamps

### `brands`

-   id
-   name
-   slug unique
-   website_url
-   description
-   logo_path
-   brand_config JSON
-   is_active
-   timestamps

### `brand_sources`

-   id
-   brand_id FK
-   source_url
-   canonical_url
-   source_type
-   trust_tier
-   allowed
-   robots_checked_at
-   last_fetched_at
-   last_status
-   timestamps

### `research_items`

-   id
-   brand_id FK nullable
-   source_id FK
-   title
-   summary
-   extracted_facts JSON
-   published_at nullable
-   fetched_at
-   content_hash
-   freshness_status
-   verification_status
-   timestamps

### `campaigns`

-   id
-   brand_id FK
-   name
-   objective
-   audience
-   content_pillars JSON
-   start_date
-   end_date
-   status
-   timestamps

### `content_items`

-   id
-   brand_id FK
-   campaign_id nullable FK
-   title
-   topic
-   content_type
-   language
-   hook
-   body LONGTEXT
-   CTA
-   hashtags JSON
-   source_refs JSON
-   claim_evidence JSON
-   platform_variants JSON
-   status
-   quality_score nullable
-   content_hash
-   ai_provider
-   ai_model
-   prompt_version
-   timestamps

### `content_reviews`

-   id
-   content_item_id FK
-   review_type
-   result
-   findings JSON
-   reviewer_id nullable FK users
-   reviewed_at
-   timestamps

### `media_assets`

-   id
-   brand_id FK
-   content_item_id nullable FK
-   asset_type
-   storage_path
-   mime_type
-   file_size
-   width
-   height
-   duration_seconds nullable
-   license_source
-   checksum
-   metadata JSON
-   timestamps

### `social_accounts`

-   id
-   brand_id FK
-   platform
-   account_name
-   external_account_id
-   access_token_encrypted
-   refresh_token_encrypted nullable
-   scopes JSON
-   token_expires_at nullable
-   connection_status
-   last_health_check_at
-   timestamps

Unique constraint: `(platform, external_account_id)`.

### `publishing_jobs`

-   id
-   brand_id FK
-   content_item_id FK
-   social_account_id FK
-   scheduled_at
-   status
-   idempotency_key unique
-   attempt_count
-   platform_post_id nullable
-   permalink nullable
-   published_at nullable
-   last_error nullable
-   request_metadata JSON
-   response_metadata JSON
-   timestamps

### `analytics_snapshots`

-   id
-   publishing_job_id FK
-   platform
-   metric_name
-   metric_value DECIMAL
-   metric_definition
-   captured_at
-   source
-   timestamps

### `ai_providers`

-   id
-   provider_name
-   base_url
-   is_enabled
-   priority
-   config JSON
-   health_status
-   last_health_check_at
-   timestamps

### `ai_models`

-   id
-   ai_provider_id FK
-   model_id
-   display_name
-   is_free
-   capabilities JSON
-   context_limit nullable
-   input_price nullable
-   output_price nullable
-   is_enabled
-   priority
-   last_verified_at
-   timestamps

### `ai_usage`

-   id
-   provider
-   model_id
-   task_type
-   request_count
-   input_tokens nullable
-   output_tokens nullable
-   estimated_cost DECIMAL default 0
-   status
-   created_at

### `workflow_jobs`

-   id
-   job_type
-   payload JSON
-   status
-   priority
-   scheduled_at
-   started_at nullable
-   completed_at nullable
-   lease_owner nullable
-   lease_expires_at nullable
-   attempt_count
-   max_attempts
-   idempotency_key nullable
-   last_error nullable
-   timestamps

### `workflow_events`

-   id
-   event_type
-   aggregate_type
-   aggregate_id
-   payload JSON
-   occurred_at
-   processed_at nullable
-   status
-   timestamps

### `worker_nodes`

-   id
-   node_name
-   platform
-   worker_version
-   capabilities JSON
-   last_heartbeat_at
-   status
-   timestamps

### `audit_logs`

-   id
-   user_id nullable
-   actor_type
-   action
-   resource_type
-   resource_id
-   ip_address nullable
-   user_agent nullable
-   metadata JSON
-   created_at

### `notification_logs`

-   id
-   channel
-   event_type
-   destination_masked
-   status
-   sent_at nullable
-   error nullable
-   timestamps

## 8.2 Required indexes

-   `workflow_jobs(status, priority, scheduled_at)`
-   `workflow_jobs(lease_expires_at)`
-   `publishing_jobs(status, scheduled_at)`
-   `publishing_jobs(social_account_id, status)`
-   `content_items(brand_id, status, created_at)`
-   `research_items(brand_id, fetched_at)`
-   `research_items(content_hash)`
-   `analytics_snapshots(publishing_job_id, captured_at)`
-   `ai_models(is_enabled, is_free, priority)`
-   `worker_nodes(last_heartbeat_at)`

Use soft deletes only where recovery/business requirements justify them.
Do not soft-delete audit logs.

------------------------------------------------------------------------

# 9. API Design

All worker APIs must be versioned under `/api/v1/worker`.

Endpoints:

  Method   Endpoint                       Purpose
  -------- ------------------------------ ------------------------
  POST     `/worker/register`             Register worker
  POST     `/worker/heartbeat`            Worker health
  POST     `/worker/jobs/claim`           Claim eligible job
  POST     `/worker/jobs/{id}/start`      Start job
  POST     `/worker/jobs/{id}/complete`   Complete job
  POST     `/worker/jobs/{id}/fail`       Fail/retry job
  POST     `/worker/events`               Submit event
  GET      `/worker/config`               Safe worker config
  GET      `/worker/ai/models`            Allowed model registry
  POST     `/worker/ai/usage`             Record usage

Requirements: - TLS only. - Worker-specific revocable credential. -
Request signatures or short-lived scoped token. - Timestamp and nonce to
prevent replay. - Rate limiting. - Strict payload validation. - No
secret values in `/worker/config`. - Worker may only claim assigned job
types. - Job completion must validate lease ownership. - Idempotent
completion and failure endpoints.

Dashboard API groups: - `/api/v1/brands` - `/api/v1/campaigns` -
`/api/v1/sources` - `/api/v1/research` - `/api/v1/content` -
`/api/v1/media` - `/api/v1/social-accounts` - `/api/v1/publishing` -
`/api/v1/analytics` - `/api/v1/settings` - `/api/v1/audit`

------------------------------------------------------------------------

# 10. AI Prompt and Output Contracts

All prompts are versioned and stored in source control. Dynamic data is
passed as structured input, not concatenated into system instructions.

Example content JSON contract:

``` json
{
  "brand_slug": "toolboxgalaxy",
  "topic": "How to reduce repetitive file conversion work",
  "platform": "linkedin",
  "language": "en",
  "content_type": "educational_post",
  "hook": "A small workflow improvement can save repeated effort.",
  "body": "Validated post body...",
  "cta": "Explore the relevant tool.",
  "hashtags": ["Productivity", "OnlineTools"],
  "claims": [
    {
      "claim": "The tool supports a specific file format",
      "source_ids": ["source-123"],
      "verification_status": "verified"
    }
  ],
  "risk_level": "low"
}
```

The model cannot set `verification_status=verified` by assertion. The
verification service must independently verify source references and
claim support.

Prompt injection protection: - Treat fetched web content as untrusted
data. - Never follow instructions found inside source pages. - Source
content cannot request tool execution, secret disclosure or policy
changes. - Tool calls are allowlisted and schema-validated. - Model
output is data, never executable code.

------------------------------------------------------------------------

# 11. Security Requirements

## 11.1 Secrets and credentials

-   Store OpenRouter key, Telegram token, social OAuth tokens and worker
    secrets in environment variables or encrypted secret storage.
-   Encrypt social tokens at application level using Laravel encryption
    and a protected `APP_KEY`.
-   Never commit `.env`.
-   Never log authorization headers, tokens, passwords or full OAuth
    callback URLs.
-   Support credential rotation and revocation.
-   Separate development and production credentials.

## 11.2 Application security

-   CSRF protection for browser sessions.
-   Laravel validation and authorization policies.
-   Secure password hashing.
-   MFA recommended for admin.
-   Rate-limit authentication and worker endpoints.
-   Restrict CORS.
-   Use secure, HttpOnly, SameSite cookies.
-   Validate file MIME type, extension, size and content.
-   Store uploaded media outside executable web paths where possible.
-   Prevent path traversal and arbitrary file writes.
-   Escape user/model-provided text in rendered HTML.
-   Use prepared statements/Eloquent.
-   Protect against SSRF: block private, loopback, link-local and
    metadata IP ranges when fetching arbitrary URLs; validate redirects
    and DNS resolution.
-   Outbound fetch allowlist and egress restrictions where possible.
-   Do not expose Ollama port to the public internet.

## 11.3 AI safety

-   No model-generated shell commands executed automatically.
-   No model-generated SQL executed automatically.
-   No direct model access to credentials.
-   No arbitrary URL fetching based solely on model output.
-   No automatic high-risk employment/legal/financial claims.
-   Human review required for high-risk or uncertain claims.
-   No automatic engagement manipulation.

## 11.4 Backup and recovery

-   Daily database backup if hosting supports it.
-   Periodic encrypted export of critical configuration and content.
-   Local worker logs rotate.
-   Test restore procedure.
-   Media assets have checksums.
-   Never treat local worker temp files as the only copy of an asset.

------------------------------------------------------------------------

# 12. Observability and Operations

Dashboard health cards: - Local worker online/offline. - Last
heartbeat. - Pending/running/retry/dead-letter jobs. - OpenRouter health
and free quota state (when known). - Ollama health and loaded model. -
Social connector status. - Token expiry warnings. - Publishing
success/failure. - Disk usage and media render failures.

Structured log fields: - `correlation_id` - `job_id` - `brand_id` -
`agent_name` - `provider` - `model` - `duration_ms` - `status` -
`error_code`

Do not log full private source text, access tokens or personal data.

------------------------------------------------------------------------

# 13. Implementation Roadmap

## Phase 0 -- Preflight and repository baseline

-   Confirm PHP version and Laravel compatibility on Hostinger.
-   Confirm MySQL version and migration permissions.
-   Confirm cron availability and limitations.
-   Confirm desktop OS, Python, disk space and CPU.
-   Benchmark Ollama candidate models.
-   Create Git repository and development/staging environment.
-   Establish coding standards and CI.

**Exit criteria:** Environment report and deployable Laravel skeleton.

## Phase 1 -- Core foundation

-   Laravel app and authentication.
-   RBAC.
-   Brand CRUD and four initial brand profiles.
-   Database migrations and seeders.
-   Audit logging.
-   Dashboard shell.
-   Worker registration and heartbeat.

**Exit criteria:** Four brands isolated and manageable.

## Phase 2 -- Job engine

-   DB-backed workflow jobs.
-   Atomic claim/lease.
-   Retry/backoff.
-   Dead-letter queue.
-   Event outbox.
-   Idempotency.
-   Worker API and Python worker.

**Exit criteria:** Worker survives restart and does not lose/duplicate
jobs.

## Phase 3 -- AI Gateway

-   OpenRouter client.
-   Free model registry.
-   Hard no-paid-model guard.
-   Usage/quota ledger.
-   Ollama adapter.
-   Health checks and circuit breaker.
-   Fallback and output schema validation.

**Exit criteria:** Automated tests prove OpenRouter failure triggers
Ollama and never a paid model.

## Phase 4 -- Research

-   Source registry.
-   RSS reader.
-   Public page fetcher with robots/terms safeguards.
-   Extractor and deduplication.
-   Evidence storage.
-   Freshness and trust tiers.

**Exit criteria:** Each research item has provenance and no unsupported
high-risk claim can pass.

## Phase 5 -- Content factory

-   Brand-specific prompts.
-   Topic planner.
-   Content generation.
-   Platform variants.
-   Hashtag/keyword support.
-   Duplicate checks.
-   Quality verification.
-   Content calendar.

**Exit criteria:** Generate a week of distinct drafts for all four
brands.

## Phase 6 -- Creative/media

-   Brand templates.
-   SVG/HTML rendering.
-   Carousel generator.
-   FFmpeg video assembly.
-   Captions/subtitles.
-   Media validation and storage.

**Exit criteria:** Valid assets generated on CPU desktop within resource
limits.

## Phase 7 -- Publishing foundation

-   Adapter contract.
-   OAuth connection management.
-   LinkedIn connector first.
-   Meta connector next.
-   YouTube/Pinterest/Threads/TikTok connectors after eligibility
    review.
-   Manual-ready fallback.
-   Idempotent publication and reconciliation.

**Exit criteria:** One authorized test account per enabled platform
successfully publishes a test post and records platform ID.

## Phase 8 -- Analytics and growth

-   Metrics adapters.
-   Manual CSV import fallback.
-   UTM generation.
-   Weekly reports.
-   Content performance analysis.
-   Growth recommendations.

**Exit criteria:** Report clearly distinguishes measured, unavailable
and manually entered metrics.

## Phase 9 -- Production hardening

-   Security review.
-   Backup/restore test.
-   Load test.
-   Worker outage test.
-   OAuth expiry test.
-   Publishing timeout reconciliation.
-   Documentation and deployment runbook.

**Exit criteria:** All P0 tests pass and no critical/high security
findings remain.

------------------------------------------------------------------------

# 14. Acceptance Test Plan

## 14.1 Brand isolation

-   Create four brands.
-   Create content under each.
-   Attempt cross-brand access using unauthorized user/context.
-   Expected: access denied; no data leakage.

## 14.2 AI free-only guard

-   Configure one free model and one paid model.
-   Simulate model selection.
-   Expected: paid model is rejected before network request.
-   Simulate unknown pricing.
-   Expected: reject under strict zero-budget mode.

## 14.3 OpenRouter to Ollama fallback

-   Simulate OpenRouter 429.
-   Expected: fallback to Ollama.
-   Simulate OpenRouter timeout.
-   Expected: bounded retry then Ollama.
-   Simulate Ollama unavailable.
-   Expected: job enters retry_wait/dead_letter according to policy and
    alert is generated.
-   Verify no paid model is called.

## 14.4 Worker recovery

-   Claim a job and terminate worker.
-   Wait for lease expiry.
-   Start another worker.
-   Expected: job is safely reclaimed; no duplicate side effect.

## 14.5 Publishing idempotency

-   Simulate platform timeout after request submission.
-   Reconciliation checks platform response/state before retry.
-   Expected: no duplicate publication.
-   If outcome cannot be reconciled, mark `needs_reconciliation`, not
    blind retry.

## 14.6 Research safety

-   Fetch a page containing prompt-injection text.
-   Expected: content treated as untrusted source data.
-   Fetch a disallowed/private URL.
-   Expected: blocked.
-   Fetch a page with unsupported job salary claim.
-   Expected: claim not approved.

## 14.7 Media

-   Generate image and video assets.
-   Validate MIME type, dimensions, duration and checksum.
-   Expected: invalid media cannot enter publishable queue.

## 14.8 OAuth

-   Revoke token.
-   Expected: connector status changes to
    disconnected/reauthorization_required; publishing pauses.
-   Verify token never appears in logs or API responses.

## 14.9 Analytics

-   Import a sample metrics file.
-   Expected: values retain source and capture time.
-   Missing metrics remain null, not zero unless platform explicitly
    reports zero.

## 14.10 Notifications

-   Trigger worker offline, AI fallback and publishing failure.
-   Expected: Telegram notification contains operational details but no
    secrets or personal data.

------------------------------------------------------------------------

# 15. Codex Implementation Instructions

Codex must implement this specification incrementally, not generate the
entire application in one unverified pass.

## 15.1 Repository structure

``` text
aadi-marketing-os/
├── app/
│   ├── Domain/
│   │   ├── Brands/
│   │   ├── Research/
│   │   ├── Content/
│   │   ├── AI/
│   │   ├── Workflow/
│   │   ├── Media/
│   │   ├── Publishing/
│   │   └── Analytics/
│   ├── Http/
│   ├── Models/
│   ├── Policies/
│   └── Providers/
├── database/
│   ├── migrations/
│   └── seeders/
├── resources/
│   ├── views/
│   └── js/
├── routes/
├── config/
├── tests/
│   ├── Feature/
│   ├── Unit/
│   └── Security/
├── worker/
│   ├── app/
│   │   ├── clients/
│   │   ├── jobs/
│   │   ├── ai/
│   │   ├── media/
│   │   └── runtime/
│   ├── tests/
│   ├── requirements.txt
│   └── README.md
├── docs/
│   ├── SRS.md
│   ├── ARCHITECTURE.md
│   ├── DATABASE.md
│   ├── SECURITY.md
│   ├── API_CONTRACTS.md
│   ├── ROADMAP.md
│   └── RUNBOOK.md
├── .env.example
├── README.md
└── composer.json
```

## 15.2 Mandatory Codex workflow

For every phase:

1.  Inspect repository and environment first.
2.  Write an implementation plan.
3.  Identify assumptions and blockers.
4.  Implement only the current phase.
5.  Add migrations and tests.
6.  Run static analysis and tests.
7.  Review security and failure modes.
8.  Update documentation.
9.  Produce a phase completion report with changed files, test output
    and known gaps.
10. Do not claim completion without test evidence.

## 15.3 Initial Codex task

``` text
You are implementing Aadi Marketing OS from scratch.

Read docs/SRS.md, docs/ARCHITECTURE.md, docs/DATABASE.md,
docs/SECURITY.md and docs/ROADMAP.md.

Do not use n8n, Redis, paid AI APIs, browser automation,
unofficial social publishing endpoints, or external agent frameworks.

Do not implement all phases at once.

Start with Phase 0:
- inspect the existing repository and runtime;
- confirm Laravel/PHP/MySQL compatibility;
- inspect hosting constraints;
- inspect local desktop worker environment;
- benchmark CPU-only Ollama feasibility;
- create an evidence-based preflight report;
- identify blockers and propose the exact Phase 1 plan.

Do not overwrite existing project files without inspection.
Do not invent API permissions or claim platform publishing is enabled.
No paid model may ever be selected by the AI router.
```

------------------------------------------------------------------------

# 16. Open Decisions Before Production

These are deliberately configurable rather than invented:

1.  Actual AadiPro product/service catalog.
2.  Exact Windows version and free disk space on desktop.
3.  Exact Hostinger PHP, MySQL and cron capabilities.
4.  OpenRouter free model availability at deployment time.
5.  Ollama model selected after CPU/RAM benchmark.
6.  Social API app approvals and account permissions.
7.  Exact official source allowlists for Overseas Job Placement.
8.  Which channels each brand should activate first.

------------------------------------------------------------------------

# 17. Definition of MVP

MVP is complete when:

-   Four brands are manageable from one Laravel dashboard.
-   Worker can claim and finish durable jobs.
-   OpenRouter free-only routing and Ollama fallback work.
-   Research records source provenance.
-   Content generation produces validated platform-specific drafts.
-   Duplicate and factual verification gates operate.
-   Media templates produce valid assets.
-   Publishing queue works with manual-ready fallback.
-   At least one official connector is successfully tested, if
    permissions are approved.
-   Analytics can store authorized or manually imported metrics.
-   Worker recovery, token security and audit tests pass.

**Final architectural decision:** Laravel + MySQL on Hostinger, custom
database-backed orchestration, Python worker on the CPU-only desktop,
OpenRouter free as primary, Ollama as fallback, FFmpeg for media,
official APIs for distribution, and no n8n or paid AI model dependency.
