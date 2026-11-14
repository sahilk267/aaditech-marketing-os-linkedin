# Worker lease fencing and crash-recovery protocol

Date: 2026-11-14
Tracking: G03 / G10; T032, T033, T034, T035, T039, T040; E2E-006..009; C-004.

## Versioned API contract

The existing workflow_jobs.attempt_count is the monotonic lease generation. A successful row-locked claim increments it. Every worker request to start, renew, complete, fail, context or publish includes this exact attempt_count. Server checks generation, lease owner, expiry and permitted state before processing. Even when the SAME worker-node ID reclaims a job after expiry, responses from the old generation are rejected (HTTP 409).

The worker must be version 0.3.0 or newer. Older nodes fail early during registration, heartbeat version checks or job API access. This is a deliberate breaking protocol upgrade, not an optional setting.

Claim -> start(attempt_count) -> repeat renew(attempt_count) -> complete(attempt_count, result). In case of error use fail(attempt_count, code, retryable). For content research and social posting, GET context and POST publish are likewise fenced. Worker keepalive uses its own session and the same generation.

JobEngine.complete accepts a repeat successful completion for the same generation without re-running the result processor or overwriting the committed result. Older generations cannot modify newer jobs. Complete and result processing share a database transaction and row lock.

## Durable idempotency and failure recovery

Jobs with an idempotency key are firstOrCreate'd against a unique database index. Reuse with mismatching type, brand or payload is rejected rather than silently returning unrelated work. workflow.queued is emitted once for new rows. Cron + worker fallback can safely attempt to enqueue the same keyed work.

Expired leases can be reclaimed until the attempt ceiling is reached. Exhausted attempts become dead_letter through maintenance. The publication adapter marks a job sending BEFORE calling its external provider. Succeeded, manual_ready, needs_reconciliation and blocked_review are stable on recovered jobs; no blind social provider re-send occurs. Ambiguous outcomes still require operator reconciliation. There is no guarantee of exactly-once delivery from third-party platforms.

## Upgrade and rollback

The new Windows portable binary is built in GitHub Actions by the Portable Windows Worker workflow, with a SHA-256 manifest. Obtain the binary for the deployed server commit, upgrade/restart desktop worker, then deploy the Laravel backend. Its installer retains the private .env.worker and scoped credential. An old 0.2.x executable must NOT be left running against this server; it cannot process fenced jobs. If the backend must be rolled back, restore a matching compatible worker/backend pair using the tested release artifacts. Do not weaken the attempt validation to support old workers.

## Evidence and limitations

- Laravel source regressions: tests/Feature/Phase2Plus/JobLeaseFencingTest.php and ContentPublishingTest.php
- Python wire regressions: worker/tests/test_lease_fencing.py and test_runtime.py
- Legacy upgrade refusal: tests/Feature/Phase1/WorkerAccessTest.php
- GitHub Actions: Application CI 43570336852, MariaDB smoke release 43570336826, Windows portable build 43570336818 — passed at code commit 4cc26635.

Still outstanding: simultaneous independent-connection MySQL claim/lease chaos, real Windows machine power-loss/restart, proxy disconnect and timeout fault injection, operator dead-letter recovery, read-side social provider reconciliation, and actual Hostinger deployment. E2E-006..009 remain Not Run. T125 remains Not Started; no formal G03/G10 gate acceptance.
