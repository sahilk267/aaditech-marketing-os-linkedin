<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\ContentItem;
use App\Models\ContentReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Offline-first content drafting. No AI, desktop worker or OAuth is needed to
 * prepare a reviewed publishing package. Manual drafts never auto-approve.
 */
class ManualContentController extends Controller
{
    public function store(Request $request, Brand $brand): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasBrandAccess($brand) || !in_array($user->role, ['admin', 'editor'], true)) {
            abort(403);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'topic' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'min:40', 'max:12000'],
            'hook' => ['nullable', 'string', 'max:1000'],
            'cta' => ['nullable', 'string', 'max:1000'],
            'platform' => ['required', 'in:linkedin,facebook,instagram,youtube,pinterest,threads,tiktok'],
            'content_type' => ['required', 'in:text,educational_post,image_post,visual_post,carousel_outline,blog_outline'],
            'language' => ['required', 'string', 'regex:/^[a-z]{2}(?:-[A-Z]{2})?$/D', 'max:12'],
            'hashtags' => ['sometimes', 'array', 'max:15'],
            'hashtags.*' => ['required', 'string', 'regex:/^[A-Za-z0-9_]{2,40}$/D'],
        ]);
        $body = trim($data['body']);
        $platform = $data['platform'];
        $hash = hash('sha256', json_encode([$brand->id, $platform, $body], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        if (ContentItem::query()->where('brand_id', $brand->id)->where('content_hash', $hash)->exists()) {
            throw ValidationException::withMessages(['body' => 'Duplicate content already exists in this brand']);
        }

        $item = DB::transaction(function () use ($brand, $data, $body, $platform, $hash, $user): ContentItem {
            $item = ContentItem::query()->create([
                'brand_id' => $brand->id,
                'title' => trim($data['title']),
                'topic' => trim($data['topic']),
                'hook' => $data['hook'] ?? null,
                'body' => $body,
                'cta' => $data['cta'] ?? null,
                'content_type' => $data['content_type'],
                'language' => $data['language'],
                'hashtags' => array_values(array_unique($data['hashtags'] ?? [])),
                'source_refs' => [],
                'claim_evidence' => [],
                'platform_variants' => [$platform => ['body' => $body]],
                'status' => 'needs_review',
                'content_hash' => $hash,
                'ai_provider' => 'manual',
                'prompt_version' => 'human-v1',
                // All human-entered drafts need accountable admin review.
                // This never labels unverified factual claims as verified.
                'risk_level' => 'high',
            ]);
            ContentReview::query()->create([
                'content_item_id' => $item->id,
                'review_type' => 'human_submission',
                'result' => 'pending',
                'findings' => ['source' => 'manual', 'requires_evidence_review' => true],
                'reviewer_id' => $user->id,
                'reviewed_at' => now(),
            ]);
            return $item;
        });
        return response()->json([
            'content_id' => $item->id,
            'status' => $item->status,
            'requires_admin_review' => true,
            'next_step' => 'Review content and attach independently verified evidence before approval; unsupported claims must be removed.',
        ], 201);
    }
}
