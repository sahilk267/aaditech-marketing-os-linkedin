# Phase 0 operations runbook

## Hosting preflight

1. Confirm the intended Hostinger website and that its document root can be pointed to Laravel's `public/`.
2. Use SSH, outside `public_html` / public web root, to run `php scripts/preflight/hostinger.php`.
3. Run DB version probe with scoped credentials only if safe. Inspect DDL permissions in staging with explicit opt-in (`--probe-db-ddl`).
4. Check hPanel Cron Jobs for actual schedule capabilities and CLI executable paths. Hostinger says cron schedules use UTC; inspect the account, not assumptions.
5. Record PHP extension gaps, memory/execution limits and database capabilities.

## Desktop preflight

1. On the computer intended to be always on, run `desktop.ps1` to record OS/CPU/RAM/free disk/installed tools.
2. Manually install a small candidate Ollama model after reviewing storage/network needs. Do not auto-download.
3. Run `worker/benchmark_ollama.py --model MODEL` for tokens/s, total latency and strict JSON success; separately review factual quality.
4. Verify outbound HTTPS to application domain once deployed. No inbound worker ports.

## Known gaps

The current delivered kit contains **no Laravel vendor dependencies, no running web app and no deployed social APIs**. It cannot validate host account limits, actual desktop resources or OAuth approvals from a remote sandbox.

## Recovery

Treat preflight outputs as temporary internal diagnostics. Rotate credentials if ever printed accidentally. Store non-secret results outside the repo, and rerun checks after major PHP/MySQL/OS changes.
