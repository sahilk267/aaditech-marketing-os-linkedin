<?php
namespace Tests\Feature\Phase2Plus;

use App\Domain\Analytics\WeeklyReport;
use App\Models\Brand;
use App\Models\PublishingJob;
use App\Models\ContentItem;
use App\Models\AnalyticsSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsSnapshotTest extends TestCase
{
    use RefreshDatabase;
    public function test_latest_cumulative_metric_replaces_previous_snapshot():void
    {
        $brand=Brand::create(['name'=>'Metrics','slug'=>'metrics','brand_config'=>[],'is_active'=>true]);
        $item=ContentItem::query()->create([
            'brand_id'=>$brand->id,'title'=>'T','topic'=>'T','content_type'=>'educational_post','language'=>'en',
            'body'=>'Test','hashtags'=>[],'source_refs'=>[],'claim_evidence'=>[],
            'platform_variants'=>[],'content_hash'=>str_repeat('a',64),'status'=>'approved',
        ]);
        $post=PublishingJob::query()->create(['brand_id'=>$brand->id,'content_item_id'=>$item->id,'platform'=>'linkedin',
            'scheduled_at'=>now()->subDays(2),'published_at'=>now()->subDays(1),'status'=>'succeeded',
            'idempotency_key'=>'metric-test']);
        foreach ([11,25] as $i=>$value) {
            AnalyticsSnapshot::query()->create(['publishing_job_id'=>$post->id,'platform'=>'linkedin',
                'metric_name'=>'impressions','metric_value'=>$value,'metric_definition'=>'cumulative_impressions',
                'source'=>'manual','captured_at'=>now()->subHours(6-$i)]);
        }
        $report=(new WeeklyReport())->forBrand($brand->id);
        $this->assertSame(25.0,$report['reported_metric_totals']['impressions (linkedin)']);
    }
}
