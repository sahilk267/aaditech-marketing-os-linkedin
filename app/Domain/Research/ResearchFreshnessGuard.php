<?php

namespace App\Domain\Research;

use App\Models\Brand;
use App\Models\ResearchItem;
use App\Models\WorkflowEvent;
use Illuminate\Support\Facades\DB;

/**
 * Deterministic stale-evidence lifecycle on shared hosting. No provider API,
 * paid AI, background process, or cron privilege is required for invocation.
 */
class ResearchFreshnessGuard
{
    public function expire(int $batchPerBrand = 150): int
    {
        $updated = 0;
        $batchPerBrand = min(500, max(1, $batchPerBrand));
        foreach (Brand::query()->where('is_active', true)->get() as $brand) {
            $maximum = $brand->slug === 'overseas-job-placement' ? 7 : 90;
            $configured = ($brand->brand_config ?? [])['research_max_age_days'] ?? null;
            $days = is_int($configured) && $configured >= 1
                ? min($configured, $maximum) : $maximum;
            $deadline = now()->subDays($days);
            $ids = ResearchItem::query()->where('brand_id', $brand->id)
                ->where('freshness_status', 'current')
                ->where('fetched_at', '<', $deadline)
                ->orderBy('id')->limit($batchPerBrand)->pluck('id');

            foreach ($ids as $id) {
                DB::transaction(function () use ($brand, $deadline, $id, &$updated): void {
                    $item = ResearchItem::query()->where('brand_id', $brand->id)
                        ->lockForUpdate()->find($id);
                    if (!$item || $item->freshness_status !== 'current' ||
                        !$item->fetched_at || $item->fetched_at->gte($deadline)) return;
                    $item->freshness_status = 'stale';
                    $item->save();
                    WorkflowEvent::query()->create([
                        'event_type' => 'research.stale',
                        'aggregate_type' => 'research_item',
                        'aggregate_id' => $item->id,
                        'payload' => ['brand_id' => $brand->id],
                        'occurred_at' => now(), 'status' => 'pending',
                    ]);
                    $updated++;
                }, 3);
            }
        }
        return $updated;
    }
}
