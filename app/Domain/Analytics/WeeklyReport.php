<?php
namespace App\Domain\Analytics;

use App\Models\AnalyticsSnapshot;
use App\Models\ContentItem;
use App\Models\PublishingJob;
use Illuminate\Support\Carbon;

/** Snapshots are often cumulative. Use latest observation per post/metric, never sum snapshots over time. */
class WeeklyReport
{
    public function forBrand(int $brandId,?Carbon $end=null):array
    {
        $end=$end??now();$start=$end->copy()->subDays(7);
        $posts=PublishingJob::query()->where('brand_id',$brandId)->where('status','succeeded')
            ->whereBetween('published_at',[$start,$end])->get();
        $snapshots=AnalyticsSnapshot::query()->whereIn('publishing_job_id',$posts->pluck('id'))
            ->whereBetween('captured_at',[$start,$end])->orderBy('captured_at')->orderBy('id')->get();
        $latest=[];
        foreach($snapshots as $snap) {
            $key=$snap->publishing_job_id.'|'.$snap->platform.'|'.$snap->metric_name;
            $latest[$key]=$snap;
        }
        $totals=[];$definitions=[];
        foreach($latest as $snap) {
            $label=$snap->metric_name.' ('.$snap->platform.')';
            if($snap->metric_value===null) continue;
            $totals[$label]=($totals[$label]??0)+(float)$snap->metric_value;
            $definitions[$label]=$snap->metric_definition;
        }
        return [
            'brand_id'=>$brandId,'period_start'=>$start->toDateString(),'period_end'=>$end->toDateString(),
            'posts_published'=>$posts->count(),
            'drafts_created'=>ContentItem::query()->where('brand_id',$brandId)->whereBetween('created_at',[$start,$end])->count(),
            'reported_metric_totals'=>$totals,'metric_definitions'=>$definitions,
            'snapshot_selection'=>'latest_per_publication_and_metric',
            'data_quality'=>'Only authorized/manual snapshots included; missing metrics are unknown (not zero); different platform metric definitions are not directly comparable',
        ];
    }
}
