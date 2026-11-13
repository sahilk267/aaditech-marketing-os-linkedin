<?php

namespace App\Http\Controllers;

use App\Domain\Content\ClaimEvidenceGate;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\BrandSource;
use App\Models\ContentItem;
use App\Models\ContentReview;
use App\Models\ResearchItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Attach cited claims to drafts, and record accountable admin decisions. */
class ClaimAttestationController extends Controller
{
    public function attach(Request $request, Brand $brand, int $contentId): JsonResponse
    {
        $actor = $request->user();
        if (!$actor || !$actor->hasBrandAccess($brand) ||
            !in_array($actor->role, ['admin', 'editor'], true)) abort(403);
        $data = $request->validate([
            'claim' => ['required', 'string', 'min:10', 'max:1000'],
            'research_item_ids' => ['required', 'array', 'min:1', 'max:20'],
            'research_item_ids.*' => ['required', 'integer', 'distinct', 'min:1'],
        ]);
        $claim = trim($data['claim']);

        return DB::transaction(function () use ($brand, $contentId, $claim, $data, $actor): JsonResponse {
            $item = ContentItem::query()->where('brand_id', $brand->id)
                ->lockForUpdate()->findOrFail($contentId);
            if (!in_array($item->status, ['draft', 'needs_review'], true)) {
                throw ValidationException::withMessages(['content' => 'Edit and re-review approved content before attaching new evidence']);
            }
            if (mb_stripos($item->body, $claim) === false) {
                throw ValidationException::withMessages(['claim' => 'Claim must exactly occur in the draft body']);
            }
            $ids = array_values($data['research_item_ids']);
            $research = ResearchItem::query()->where('brand_id', $brand->id)
                ->whereIn('id', $ids)->get();
            if ($research->count() !== count($ids)) {
                throw ValidationException::withMessages(['research_item_ids' => 'Unknown or foreign-brand research ID']);
            }
            foreach ($research as $r) {
                $source = BrandSource::query()->where('brand_id', $brand->id)->find($r->source_id);
                if (!$source || !$source->allowed || $r->verification_status !== 'verified' ||
                    !app(\App\Domain\Research\ResearchFreshnessGuard::class)->isCurrent($brand, $r) ||
                    ($brand->slug === 'overseas-job-placement' && $source->trust_tier !== 'A')) {
                    throw ValidationException::withMessages(['research_item_ids' => 'Only approved, current, same-brand evidence is eligible']);
                }
            }
            $claims = $item->claim_evidence ?? [];
            if (!is_array($claims) || count($claims) >= 40) {
                throw ValidationException::withMessages(['claim' => 'Claim limit exceeded']);
            }
            foreach ($claims as $existing) {
                if (is_array($existing) && ($existing['claim'] ?? null) === $claim) {
                    throw ValidationException::withMessages(['claim' => 'Claim already attached; create a new reviewed draft to change citations']);
                }
            }
            $claims[] = ['claim' => $claim, 'research_item_ids' => $ids];
            $item->claim_evidence = $claims;
            $item->source_refs = array_values(array_unique(array_merge($item->source_refs ?? [], $ids)));
            $item->save();
            $this->audit($actor->id, $item->id, 'content.claim_attached', [
                'claim_hash' => ClaimEvidenceGate::claimHash($claim),
                'research_item_ids' => $ids,
            ]);
            return response()->json(['content_id' => $item->id, 'claim_count' => count($claims),
                'status' => 'needs_attestation'], 201);
        }, 3);
    }

    public function attest(Request $request, Brand $brand, int $contentId): JsonResponse
    {
        $actor = $request->user();
        if (!$actor || !$actor->isAdmin() || !$actor->hasBrandAccess($brand)) abort(403);
        $data = $request->validate([
            'claim' => ['required', 'string', 'min:10', 'max:1000'],
            'research_item_id' => ['required', 'integer', 'min:1'],
            'source_excerpt' => ['required', 'string', 'min:15', 'max:300'],
            'rationale' => ['required', 'string', 'min:30', 'max:2000'],
            'decision' => ['required', 'in:supported,rejected'],
            'acknowledged' => ['required', 'accepted'],
        ]);
        $claim = trim($data['claim']);

        return DB::transaction(function () use ($brand, $contentId, $actor, $data, $claim): JsonResponse {
            $item = ContentItem::query()->where('brand_id', $brand->id)
                ->lockForUpdate()->findOrFail($contentId);
            if (!in_array($item->status, ['draft', 'needs_review'], true)) {
                abort(409, 'Already-approved content must be revised before new attestations');
            }
            if (mb_stripos($item->body, $claim) === false) {
                throw ValidationException::withMessages(['claim' => 'Claim is no longer present in draft body']);
            }
            $found = collect($item->claim_evidence ?? [])->contains(fn ($entry) =>
                is_array($entry) && ($entry['claim'] ?? null) === $claim &&
                in_array($data['research_item_id'], $entry['research_item_ids'] ?? [], true));
            if (!$found) {
                throw ValidationException::withMessages(['claim' => 'Attach claim and same-brand source first']);
            }
            $research = ResearchItem::query()->where('brand_id', $brand->id)
                ->findOrFail($data['research_item_id']);
            $source = BrandSource::query()->where('brand_id', $brand->id)
                ->findOrFail($research->source_id);
            if (!$source->allowed || $research->verification_status !== 'verified' ||
                !app(\App\Domain\Research\ResearchFreshnessGuard::class)->isCurrent($brand, $research) ||
                ($brand->slug === 'overseas-job-placement' && $source->trust_tier !== 'A')) {
                throw ValidationException::withMessages(['source' => 'Official/current/approved evidence required']);
            }
            if (!ClaimEvidenceGate::excerptPresent($research, $data['source_excerpt'])) {
                throw ValidationException::withMessages(['source_excerpt' => 'Excerpt not found verbatim in stored research facts']);
            }
            $findings = [
                'claim_hash' => ClaimEvidenceGate::claimHash($claim),
                'research_item_id' => $research->id,
                'source_hash' => $research->content_hash,
                'body_hash' => hash('sha256', $item->body),
                'source_excerpt' => trim($data['source_excerpt']),
                'rationale' => trim($data['rationale']),
                'reviewer_acknowledged' => true,
            ];
            $review = ContentReview::query()->create([
                'content_item_id' => $item->id,
                'review_type' => 'claim_attestation',
                'result' => $data['decision'] === 'supported' ? 'approved' : 'rejected',
                'findings' => $findings,
                'reviewer_id' => $actor->id,
                'reviewed_at' => now(),
            ]);
            $this->audit($actor->id, $item->id, 'content.claim_attested', [
                'claim_hash' => $findings['claim_hash'],
                'research_item_id' => $research->id,
                'decision' => $data['decision'],
                'review_id' => $review->id,
            ]);
            return response()->json(['review_id' => $review->id,
                'decision' => $data['decision'],
                'status' => $data['decision'] === 'supported' ? 'recorded_for_review' : 'claim_rejected',
                'next_step' => 'Admin must still review and approve the full content before publishing',
            ], 201);
        }, 3);
    }

    private function audit(int $actorId, int $contentId, string $action, array $metadata): void
    {
        AuditLog::query()->forceCreate([
            'user_id' => $actorId, 'actor_type' => 'user',
            'action' => $action, 'resource_type' => 'content_item',
            'resource_id' => (string) $contentId,
            'metadata' => $metadata,
        ]);
    }
}
