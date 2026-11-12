<?php

namespace App\Http\Controllers;

use App\Domain\Content\ContentPolicy;
use App\Models\Brand;
use App\Models\ContentItem;
use App\Models\PublishingJob;
use App\Models\WorkflowEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Prepare a copyable, reviewed native-publishing package immediately.
 * Never makes an external API call or invents an approved social account.
 */
class ManualPublishingController extends Controller
{
    public function store(Request $request, Brand $brand, int $contentId, ContentPolicy $policy): JsonResponse
    {
        $actor = $request->user();
        if (!$actor || !$actor->hasBrandAccess($brand) || !in_array($actor->role, ['admin', 'editor'], true)) {
            abort(403);
        }
        $validated = $request->validate([
            'platform' => ['required', 'in:linkedin,facebook,instagram,youtube,pinterest,threads,tiktok'],
        ]);
        $item = ContentItem::query()->where('brand_id', $brand->id)->findOrFail($contentId);
        $policy->requirePublishable($item);
        $platform = $validated['platform'];
        $key = 'manual:' . $brand->id . ':' . $item->id . ':' . $platform;

        $publication = DB::transaction(function () use ($brand, $item, $platform, $key): PublishingJob {
            $publication = PublishingJob::query()->firstOrCreate(
                ['idempotency_key' => $key],
                [
                    'brand_id' => $brand->id,
                    'content_item_id' => $item->id,
                    'social_account_id' => null,
                    'platform' => $platform,
                    'scheduled_at' => now(),
                    'status' => 'manual_ready',
                    'request_metadata' => ['mode' => 'manual', 'creator' => 'first_party'],
                ]
            );
            if ((int) $publication->brand_id !== (int) $brand->id ||
                (int) $publication->content_item_id !== (int) $item->id ||
                $publication->platform !== $platform) {
                abort(409, 'Manual publishing idempotency key mismatch');
            }
            if ($publication->wasRecentlyCreated) {
                WorkflowEvent::query()->create([
                    'event_type' => 'publication.manual_ready',
                    'aggregate_type' => 'publishing_job',
                    'aggregate_id' => $publication->id,
                    'payload' => ['brand_id' => $brand->id, 'platform' => $platform],
                    'occurred_at' => now(),
                    'status' => 'pending',
                ]);
            }
            return $publication;
        });
        return response()->json([
            'publication_id' => $publication->id,
            'status' => $publication->status,
            'platform' => $publication->platform,
            'manual_package_url' => '/api/v1/brands/' . $brand->id . '/manual-package/' . $publication->id,
            'note' => 'Manual package is ready for publishing through the official platform interface.',
        ], $publication->wasRecentlyCreated ? 201 : 200);
    }
}
