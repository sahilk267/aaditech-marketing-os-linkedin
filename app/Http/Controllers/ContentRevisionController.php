<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\ContentItem;
use App\Models\ContentReview;
use App\Models\MediaAsset;
use App\Models\PublishingJob;
use App\Models\WorkflowEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * An edit always revokes a prior approval, generated assets and prepared posts.
 * Historical sent/ambiguous publications are immutable; create a new draft.
 */
class ContentRevisionController extends Controller
{
    public function update(Request $request, Brand $brand, int $contentId): JsonResponse
    {
        $actor = $request->user();
        if (!$actor || !$actor->hasBrandAccess($brand) ||
            !in_array($actor->role, ['admin', 'editor'], true)) abort(403);

        $fields = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'topic' => ['sometimes', 'required', 'string', 'max:255'],
            'body' => ['sometimes', 'required', 'string', 'min:40', 'max:12000'],
            'hook' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'cta' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'hashtags' => ['sometimes', 'array', 'max:15'],
            'hashtags.*' => ['required', 'string', 'regex:/^[A-Za-z0-9_]{2,40}$/D'],
        ]);
        if ($fields === []) {
            throw ValidationException::withMessages(['content' => 'Supply at least one editable field']);
        }

        return DB::transaction(function () use ($brand, $contentId, $actor, $fields): JsonResponse {
            // Shared lock order: content -> publications (including PublishService).
            $item = ContentItem::query()->where('brand_id', $brand->id)
                ->lockForUpdate()->findOrFail($contentId);
            $publications = PublishingJob::query()
                ->where('brand_id', $brand->id)->where('content_item_id', $item->id)
                ->orderBy('id')->lockForUpdate()->get();

            // This draft might already be visible to third parties. Never rewrite
            // historical provider evidence or try to retract a published post.
            if ($publications->contains(fn ($p) => in_array($p->status, [
                'sending', 'succeeded', 'needs_reconciliation',
            ], true))) {
                abort(409, 'Content has been sent or its outcome is unknown; create a new draft');
            }

            $changes = [];
            foreach ($fields as $field => $value) {
                if (is_string($value)) $value = trim($value);
                if (in_array($field, ['title', 'topic', 'body'], true) && $value === '') {
                    throw ValidationException::withMessages([$field => 'Field may not be empty']);
                }
                if ($field === 'hashtags') $value = array_values(array_unique($value));
                if ($field === 'hook' || $field === 'cta') $value = $value === '' ? null : $value;
                if ($item->{$field} !== $value) $changes[$field] = $value;
            }
            if ($changes === []) return response()->json([
                'content_id' => $item->id, 'status' => $item->status, 'changed' => false,
            ]);

            $before = $item->content_hash;
            foreach ($changes as $field => $value) $item->{$field} = $value;
            $item->status = 'needs_review';
            $item->approved_by = null;
            $item->approved_at = null;
            $item->risk_level = 'high';
            $item->quality_score = null;
            // Source claims and AI-rendered variants must be explicitly reviewed again.
            $item->claim_evidence = [];
            $item->source_refs = [];
            $item->platform_variants = [];
            $item->content_hash = hash('sha256', json_encode([
                'brand' => $brand->id, 'body' => $item->body, 'title' => $item->title,
                'revision' => now()->format('Y-m-d H:i:s.u'),
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $item->save();

            // Preserve files for audit/retention, but never include stale media
            // in a new publishing package or reuse it after reapproval.
            $detached = MediaAsset::query()
                ->where('brand_id', $brand->id)->where('content_item_id', $item->id)
                ->update(['content_item_id' => null]);

            $revokedIds = [];
            foreach ($publications as $publication) {
                if (!in_array($publication->status, ['scheduled', 'manual_ready'], true)) continue;
                $publication->status = 'blocked_review';
                $publication->last_error = 'CONTENT_REVISION_REQUIRES_APPROVAL';
                $publication->save();
                $revokedIds[] = $publication->id;
                WorkflowEvent::query()->create([
                    'event_type' => 'publication.blocked_review',
                    'aggregate_type' => 'publishing_job', 'aggregate_id' => $publication->id,
                    'payload' => ['brand_id' => $brand->id, 'reason' => 'content_revision'],
                    'occurred_at' => now(), 'status' => 'pending',
                ]);
            }

            $meta = [
                'changed_fields' => array_keys($changes),
                'previous_hash' => $before, 'new_hash' => $item->content_hash,
                'revoked_publications' => $revokedIds, 'detached_assets' => $detached,
                'approval_reset' => true,
            ];
            ContentReview::query()->create([
                'content_item_id' => $item->id,
                'review_type' => 'human_revision', 'result' => 'pending',
                'findings' => $meta, 'reviewer_id' => $actor->id, 'reviewed_at' => now(),
            ]);
            AuditLog::query()->forceCreate([
                'user_id' => $actor->id, 'actor_type' => 'user',
                'action' => 'content.revised', 'resource_type' => 'content_item',
                'resource_id' => (string) $item->id, 'metadata' => $meta,
            ]);
            return response()->json([
                'content_id' => $item->id, 'status' => $item->status, 'changed' => true,
                'requires_admin_review' => true,
                'revoked_publications' => count($revokedIds),
                'detached_assets' => $detached,
            ]);
        }, 3);
    }
}
