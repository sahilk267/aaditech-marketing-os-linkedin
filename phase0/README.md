# Aadi Marketing OS — Phase 0 Preflight Kit

This is **not yet a running Laravel application**. It is the evidence-gathering, architecture and phased-delivery handoff required before the Laravel project is scaffolded. No production credentials, third-party publishing permissions, or paid APIs are included.

## System boundary

- **Hostinger Business web hosting:** future Laravel + MySQL application, API, dashboard and database-backed job state.
- **Local Windows desktop:** future Python worker, local Ollama inference and FFmpeg; polls Hostinger over outbound HTTPS.
- **AI:** only verified-free OpenRouter models; local Ollama fallback; fail closed rather than pay.
- **Publishing:** authorized official APIs after approval, otherwise `manual_ready`.
- **Brands:** Aadi Tech Solutions, Overseas Job Placement, AadiPro, ToolboxGalaxy. AadiPro catalog remains unverified.

See `docs/SRS.md` for the unmodified requirements baseline.

## Run the read-only preflight

**On Hostinger using SSH (outside public web root):**

```bash
mkdir -p preflight-results
php scripts/preflight/hostinger.php > preflight-results/hostinger-preflight.json
```

Do not expose the diagnostic as an HTTP page. Database version can be probed using environment variables (listed below); no credentials are emitted:

```bash
PREFLIGHT_DB_HOST=localhost PREFLIGHT_DB_NAME=your_db PREFLIGHT_DB_USER=your_user \
php scripts/preflight/hostinger.php
```

For password-protected databases, supply `PREFLIGHT_DB_PASSWORD` through your approved secure secret mechanism (not a committed file or shell history). The optional DDL privileges probe requires **both** `--probe-db-ddl` and database environment variables, and creates then drops a random-named temporary table within the selected database. Do not run it in production until authorized.

**On the actual Windows desktop, using PowerShell:**

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\preflight\desktop.ps1
```

**Test Ollama CPU inference, without downloading models:**

```bash
python worker/benchmark_ollama.py --list-models
python worker/benchmark_ollama.py --model YOUR_INSTALLED_MODEL --output preflight-results/ollama-results.json
```

The benchmark uses a localhost-only URL, a schema-constrained response, three tiny generic prompts, and reports durations plus parse validity. It does **not** certify a model's factual correctness or safety. Do not insert private prompts or PII.

**Local environment diagnostic:**

```bash
python scripts/preflight/local.py --output preflight-results/local-preflight.json
python -m unittest discover -s tests -v
```

## Next delivery gate

`docs/PHASE0_PREFLIGHT_REPORT.md` distinguishes inspected sandbox evidence from **not-yet-verified Hostinger and desktop facts**. `docs/PHASE1_IMPLEMENTATION_PLAN.md` is the sequenced work plan after preflight. Do **not** assume phase 0's exit criteria are met before Laravel dependencies and the target environments are verified.

## Additional safeguards

Never publish `.env`, logs containing credentials, diagnostic JSON containing internal host paths, or social OAuth tokens. Generated preflight results are git-ignored. If these files are added to another repo, inspect existing files and use a separate branch; do not overwrite.
