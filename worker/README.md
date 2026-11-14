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

## Portable Windows worker (no local Python needed)

The Portable Windows Worker GitHub workflow builds and smoke-tests a standalone Windows executable, packages its SHA-256 hash, and includes install_windows_binary.ps1. Extract the CI artifact in a private directory and run that installer. It requests your HTTPS app URL and existing scoped worker token, stores the credential with user-only Windows permissions, and registers an at-logon task. This is an unsigned executable (Windows may display SmartScreen); it does not install Ollama or grant social OAuth, and requires a logged-in user session for the at-logon task.

## Lease protocol compatibility (2026-11-14)

**Breaking safety upgrade:** Laravel worker job APIs now require `attempt_count` from the claim response on start, renew, complete, fail, context and publish requests. Backend registration requires worker `0.3.0` or newer; older binaries cannot safely participate in generation-fenced retries. Download the new Portable Windows Worker artifact built from the same git revision as the deployed server; run its installer again in the existing private directory (the installer preserves an existing .env.worker and scoped token). Restart the at-logon worker task. Backend job API rejects outdated versions before work is claimed. A newer Windows binary may still show an unsigned publisher warning, so verify SHA-256 in the GitHub artifact.
