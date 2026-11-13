# Claim-level factual verification contract

**Implementation date:** 2026-11-13
**Tracking:** T062, T063, T064, T067 · C-006 · E2E-016 · REQ-008 / REQ-021 / REQ-024 / REQ-033.

## The safety boundary

A research item marked "verified" by ID is not sufficient evidence that a particular marketing claim is true. OpenRouter/Ollama/worker drafts are **never** allowed to set their own evidence verification status. A legitimate user must attach the exact claim and separately reference the approved same-brand research item(s). An accountable **admin** reviews an excerpt and manually selects supported or rejected with a written rationale acknowledging that the original source was checked.

The excerpt must occur within the stored short research summary/extracted facts. **This is a text-presence check, not semantic entailment or proof that the remote source was truthful.** The explicit human support decision is a compensating control, and cannot guarantee the absence of misinformation.

## Canonical workflow

1. Register and admin-approve a public HTTPS source; fetch its short research summary. Employment/immigration Tier A evidence is required by the current strict implementation policy.
2. A human reviews the original source and verifies/freshens its research item. The research record retains brand ID, source ID, fetched/published timestamps and SHA-256 content hash.
3. Draft with exact factual sentences in its body; POST /api/v1/brands/{brand}/content/{contentId}/claims with the claim and integer research_item_ids. Cross-brand/unverified/stale/unapproved sources are rejected. The claim must literally occur in the draft body. Add citations before approving.
4. Admin POST /api/v1/brands/{brand}/content/{contentId}/claims/attest with claim, research_item_id, verbatim excerpt <=300 chars, rationale, decision and acknowledged=true. Supports both rejection and approval; a later rejection supersedes older support.
5. Ordinary full-content approval remains mandatory. On approval and **every subsequent publication/package request**, the ContentPolicy re-checks all linked evidence. A claim requires a supported review for every linked source and matching content-body/source hashes.
6. Source disabled, freshness changed, source content hash changed, text changed or a review rejected? Publication fails closed; the automatic post moves to blocked_review without a network call, manual exports fail validation.
7. Editing approved content clears claims/approval and invalidates prepared media/posts. New edits need fresh citation and approval.

No new SQL migration: this increment intentionally reuses content_reviews with review_type=claim_attestation, audit_logs, research_items, and brand_sources. The new records are written within existing SQL transactions, with content-row locks and GitHub CI tests.

## Negative tests and limits

PHPunit: tests/Feature/Phase2Plus/ClaimAttestationTest.php covers no-citation rejection, no-attestation rejection, explicit rejected evidence, unsupported excerpt, unassigned editors, changed source hashes, revoked sources and stale research. GitHub Actions CI: 43403634588 (passed).

Known remaining limitations:
- Automatic source-vs-claim semantic entailment is NOT implemented; matching excerpt can be true while claim is an inaccurate paraphrase. It still needs accountable human reviewer and stronger multi-source/source-HTML validation.
- Unstructured, implicitly factual sentences outside claim_evidence are not exhaustively detected; overseas employment keywords receive a conservative additional fail-closed check.
- Existing historical claims now require re-attestation before approval/publication; intentionally fail-closed.
- A published post is not retractable when source facts later change. Existing sent/uncertain publication states require operator reconciliation and follow-up.
- Human reviews have no formal second-approver requirement; independent high-risk review remains a separate engineering/owner acceptance task.
- No real live source exercise, legal correctness certification, or E2E-016 acceptance is claimed.

**Status:** implementation in progress; CI tested, source governance and live review not fully verified. Do not mark G05/G10/G11 accepted from this increment alone.
