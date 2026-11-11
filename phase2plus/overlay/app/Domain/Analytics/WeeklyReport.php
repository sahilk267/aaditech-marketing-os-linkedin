<?php

namespace App\Domain\Analytics;

use App\Models\AnalyticsSnapshot;
use App\Models\ContentItem;
use App\Models\PublishingJob;
use Illuminate\Support\Carbon;

class WeeklyReport
{
    public function forBrand(int $brandId, ?Carbon $end=null): array
    {
        $end=$end??now(); $start=$end->copy()->subDays(7);
        $posts=PublishingJob::query()->where('brand_id',$brandId)->where('status','succeeded')
            ->whereBetween('published_at',[$start,$end])->get();
        $metrics=AnalyticsSnapshot::query()->whereIn('publishing_job_id',$posts->pluck('id'))
            ->whereBetween('captured_at',[$start,$end])->get();
        $totals=[];
        foreach ($metrics as $item) {
            $k=$item->metric_name.' ('.$item->platform.')';
            if ($item->metric_value!==null) $totals[$k]=($totals[$k]??0)+(float)$item->metric_value;
        }
        return [
            'brand_id'=>$brandId, 'period_start'=>$start->toDateString(), 'period_end'=>$end->toDateString(),
            'posts_published'=>$posts->count(),
            'drafts_created'=>ContentItem::query()->where('brand_id',$brandId)->whereBetween('created_at',[$start,$end])->count(),
            'reported_metric_totals'=>$totals,
            'data_quality'=>'Only collected/manual snapshots included; missing metrics are unknown, not zero',
        ];
    }
}
