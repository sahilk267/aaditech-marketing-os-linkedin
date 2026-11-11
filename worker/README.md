# Local Python worker (outbound HTTPS only)

This worker runs serially, requests jobs from Laravel, and does not expose ports. It does not automate social network browsers. OpenRouter is used only when both catalog and server registry independently confirm zero-priced models. A persistent SQLite daily counter limits the local request budget; Ollama is a local no-fee fallback.

Use `python -m amo_worker.main --once` to test, then `--forever`. Configure variables in the process environment per `.env.worker.example`. Python standard-library safety unit tests: `PYTHONPATH=phase2plus/worker python -m unittest discover -s phase2plus/worker/tests -v`.

Never log OAuth tokens, raw provider responses, source pages containing PII or applicant records. The worker is *not* a shell-execution agent. Source fetching uses pinned TLS DNS connection verification, public HTTPS only, robots decisions and an upper-bound payload size. Media uses escaped text in owner-generated SVG and optional FFmpeg with safe subprocess argument lists.

## Local Windows quick setup

From a private PowerShell window after you have issued a server-side scoped credential:

```powershell
cd worker
.\install_windows.ps1
```

The installer creates a Python virtual environment, runs unit tests, asks once for the app HTTPS URL and worker credential, stores them in a user-restricted `.env.worker` file and registers a Windows at-logon scheduled task. It does not automatically install Ollama, grant OAuth scopes, or open inbound ports. The task runs only while the Windows user session is active; use a properly configured service for always-on headless operation. Keep a backup/re-issuance plan for the worker credential.

For a manual session, `worker/.venv/Scripts/python.exe -m amo_worker.main --forever` reads `.env.worker` automatically. Rotate the credential if the local config is exposed. The app reports missing Ollama/provider configuration as an operational blocker rather than fabricating content.
