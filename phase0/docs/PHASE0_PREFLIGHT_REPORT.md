# Phase 0 preflight report

**Project:** Aadi Marketing OS  
**Baseline:** SRS v1.0 (2026-11-03)  
**Inspection date:** 2026-11-11 (Asia/Kolkata)  
**Status:** **IN PROGRESS — not ready to declare Phase 0 complete**  
**Scope:** Uploaded requirements specification, current isolated authoring environment, and a read-only look at one potentially related GitHub repository. Neither the user's Hostinger account nor actual Windows worker desktop was inspected.

## 1. Inspected development sandbox — observed

| Check | Observed | Phase 0 impact |
|---|---|---|
| OS | Debian 13 Linux sandbox | Not the required Windows desktop |
| PHP | 8.4.24 CLI | Meets Laravel 13 PHP >= 8.3 minimum |
| PHP required extensions | ctype, fileinfo, filter, hash, openssl, pcre, pdo, session, tokenizer present | Missing **curl, DOM, mbstring, XML** in sandbox |
| PDO MySQL driver | Not present; PDO drivers list empty | Cannot test Laravel/MySQL integration here |
| Composer | Not installed | Cannot install a verifiable Laravel skeleton here |
| MySQL client / server | Client not found; server not tested | Schema version / DDL not verified |
| Python | 3.13.5 | Available for test scripts, not actual worker host |
| Git | 2.47.3 | Available |
| Node | 22.16.0 | Available |
| FFmpeg | 7.1.5 | Available in sandbox; no proof for desktop |
| Ollama | Not found | Local model benchmark **not performed** |

Diagnostic command evidence: `python scripts/preflight/local.py`, `php scripts/preflight/hostinger.php` in this temporary sandbox. These results **do not describe Hostinger or the real worker desktop**.

## 2. Hostinger Business — unverified

- PHP CLI and web SAPI versions, extensions, PDO MySQL driver, MySQL server version, create/index/alter privileges.
- Cron schedule granularity and command line paths; document root isolation and writable storage paths.
- HTTPS, current website SSL, storage and account limitations, PHP memory/time/upload limits.
- Hosting ability to deploy Composer-generated Laravel dependencies and `public/` as web root.

**Current external documentation (not account-level verification):** Laravel 13 requires PHP 8.3+ and extensions (https://laravel.com/framework/docs/deployment). Hostinger documents hPanel Cron Jobs and UTC scheduling (https://www.hostinger.com/support/1583465-how-to-set-up-a-cron-job-at-hostinger/). These do not prove the user's specific plan is configured.

## 3. Windows local worker — unverified

- Actual Windows edition/build, processor, 16 GB RAM confirmation, free SSD/disk space.
- Python installation, Ollama availability, FFmpeg PATH, service or task scheduler startup and outbound HTTPS.
- CPU-only model latency, RAM usage, structured JSON validity and content quality.

Bundled `desktop.ps1` and `worker/benchmark_ollama.py` gather evidence on the **real desktop**. The benchmark never downloads a model or attempts paid services.

## 4. Repository inspection — read-only

The connected GitHub results include a potentially related repository:
`r8898278453-coder/aaditechbussinessgrowthmarketing`
(https://github.com/r8898278453-coder/aaditechbussinessgrowthmarketing).
Its accessible `package.json` declares a React/Vite/Express stack. The standard `composer.json`, `README.md` and `docs/SRS.md` paths were not present in the inspected default branch. Therefore **do not infer it is the intended Laravel repository**, do not overwrite it and do not push any code without target selection.

## 5. Produced in Phase 0 so far

- Preserved unmodified SRS under `docs/SRS.md`.
- Architecture, data model inventory, security, API, roadmap and runbook docs.
- Hostinger PHP CLI diagnostic (no secret output, opt-in DDL probe).
- Windows desktop PowerShell diagnostic.
- Python environment diagnostic and Ollama structured-JSON model benchmark.
- Phase 1 ordered implementation tickets and unresolved decision log.
- Automated static and CLI smoke tests with results documented below.

## 6. Acceptance gate / blockers

| Requirement from SRS phase 0 | Status |
|---|---|
| Hostinger PHP/MySQL/cron verification | **Blocked** — no target account evidence |
| Desktop OS/disk/CPU check | **Blocked** — target desktop not accessible |
| CPU-only Ollama candidate benchmark | **Blocked** — actual desktop/model unavailable |
| Intended repository confirmed | **Blocked** — candidate React repo not established as target |
| Deployable Laravel skeleton | **Blocked** — Composer and mandatory PHP extensions missing in sandbox |
| Phase 1 plan prepared | **Complete as planning artifact** |
| Documentation/preflight scripts | **Prepared; validation below** |

**Do not mark Phase 0 passed or initiate Phase 1 development until target preflights and application skeleton have evidence.**

## 7. Validation log

Validation runs in isolated sandbox only; all HTTP integrations and target-platform tests remain unexecuted. See `PHASE0_TEST_RESULTS.txt` for observed test command/output.

## 8. Next actions in order

1. Run Hostinger CLI preflight and inspect hPanel PHP, MySQL and Cron; document exact evidence without exposing credentials.
2. Run Windows PowerShell diagnostic on the actual CPU worker computer.
3. Install/choose a small quantized Ollama candidate under owner's control, benchmark and review outputs; do not assume performance.
4. Confirm correct GitHub repo/branch, create a safe baseline branch and install supported Laravel with Composer on a compatible development toolchain.
5. Verify `php artisan --version`, tests and minimal HTTP health route; then update report with evidence and proceed to Phase 1.
