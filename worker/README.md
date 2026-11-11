# Local Python worker (outbound HTTPS only)

This worker runs serially, requests jobs from Laravel, and does not expose ports. It does not automate social network browsers. OpenRouter is used only when both catalog and server registry independently confirm zero-priced models. A persistent SQLite daily counter limits the local request budget; Ollama is a local no-fee fallback.

Use `python -m amo_worker.main --once` to test, then `--forever`. Configure variables in the process environment per `.env.worker.example`. Python standard-library safety unit tests: `PYTHONPATH=phase2plus/worker python -m unittest discover -s phase2plus/worker/tests -v`.

Never log OAuth tokens, raw provider responses, source pages containing PII or applicant records. The worker is *not* a shell-execution agent. Source fetching uses pinned TLS DNS connection verification, public HTTPS only, robots decisions and an upper-bound payload size. Media uses escaped text in owner-generated SVG and optional FFmpeg with safe subprocess argument lists.
