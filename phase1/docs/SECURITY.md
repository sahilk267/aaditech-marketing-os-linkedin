# Security requirements — Phase 0 handoff

Source of truth: `SRS.md`, especially sections 5, 9-11 and 14.

## Credential handling

- Store OpenRouter, Telegram, worker and social OAuth secrets in environment or encrypted secret storage. Never commit `.env` or print secret values in preflight output.
- Worker authentication should be revocable, least-privilege, rate-limited, replay-resistant, and tied to explicit job capabilities.
- Use `APP_KEY` backed Laravel encryption for OAuth tokens, and plan key rotation.

## Untrusted input and network

- Guard public page fetching against SSRF (DNS, redirects, private IPs, metadata endpoints), access control bypass, copyright violations and prompt injection.
- No arbitrary shell, SQL or URL actions from model output. Prompts/outputs are schema validated.
- Don't automate social login sessions or engagement; official platform APIs and manual-ready fallback only.
- Require independent checks for job/salary/visa/immigration claims.

## Deploy safety

- Serve only Laravel's `public/` directory; do not expose `.env`, `vendor/`, `storage/`, logs, CLI diagnostics or worker files.
- Keep application debug disabled in production, secure cookies/CSRF/MFA, validate uploads and backups.
- Never run the Hostinger CLI preflight PHP file through a public HTTP URL.

## Phase 1 security acceptance

1. Authorized admin access; CSRF and secure cookies.
2. Server-side RBAC policies and brand-scoped queries.
3. Negative cross-brand tests and no secret leakage.
4. Audit logs for configuration changes and important mutations.
5. No public diagnostic exposure.
