# Content revision history and optimistic editing

**Status:** engineering implementation in progress; acceptance E2E-018 not signed off. Applies to G06/G08/G10 and T072 / C-014.

## API contract

- GET /api/v1/brands/{brand}/content/{id}/history — authenticated same-brand read. Returns current content snapshot, editorial_fingerprint and latest 30 human_revision records with pagination.
- PATCH /api/v1/brands/{brand}/content/{id} — editor/admin with brand membership only. Supply intended edit fields plus **required** expected_fingerprint from GET history. A missing/invalid fingerprint returns validation error; a stale fingerprint returns HTTP 409 and does not mutate data.
- Every real revision saves the previous content fields into a content_reviews human_revision findings snapshot, while the separate audit_logs row stores only hashes and affected IDs.
- Every changed post returns to needs_review, invalidates approval/claim references/channel variants, disconnects obsolete creative assets and blocks scheduled/manual-ready publications.
- Publication already sending/succeeded/unknown outcome is immutable: create a new draft and reconcile live provider state rather than rewriting it.
- The manual dashboard has Load current copy and revision history actions; it uses the current fingerprint for PATCH.

## Known limits

The fingerprint is not a database version column. Its domain covers content fields/approval/media as computed by PublicationIntegrity('editorial'); it cannot prevent every external provider race or privileged DB modification. Historical source attestation and future remote social status are not snapshotted in this endpoint. Existing content_reviews records from before this release may have no previous_snapshot field. Actual concurrent browser/MySQL chaos acceptance and data-retention/privacy signoff remain open.

No owner-issued credentials or paid automation are involved.
