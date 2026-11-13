<?php

namespace Tests\Feature\Phase2Plus;

use App\Domain\Research\ResearchFreshnessGuard;
use App\Models\Brand;
use App\Models\BrandSource;
use App\Models\ResearchItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResearchFreshnessGuardTest extends TestCase
{
    use RefreshDatabase;

    private function record(string $slug, int $daysOld, ?int $override = null): ResearchItem
    {
        $brand=Brand::query()->create([
            'name'=>$slug,'slug'=>$slug,
            'is_active'=>true,
            'brand_config'=>$override ? ['research_max_age_days'=>$override] : [],
        ]);
        $source=BrandSource::query()->create([
            'brand_id'=>$brand->id,'source_url'=>'https://docs.example.org/'.$slug,
            'canonical_hash'=>hash('sha256',$slug),
            'source_type'=>'public_page','trust_tier'=>'A','allowed'=>true,
        ]);
        return ResearchItem::query()->create([
            'brand_id'=>$brand->id,'source_id'=>$source->id,
            'title'=>'Sample fact','summary'=>'A verified research summary.',
            'extracted_facts'=>['title'=>'A verified research summary.'],
            'fetched_at'=>now()->subDays($daysOld),'published_at'=>now()->subDays($daysOld),
            'content_hash'=>hash('sha256','research-'.$slug),
            'freshness_status'=>'current','verification_status'=>'verified',
        ]);
    }

    public function test_overseas_evidence_expires_after_seven_days_but_new_research_does_not(): void
    {
        $old=$this->record('overseas-job-placement',8);
        $new=$this->record('tech',60);
        $count=app(ResearchFreshnessGuard::class)->expire();
        $this->assertSame(1,$count);
        $this->assertSame('stale',$old->fresh()->freshness_status);
        $this->assertSame('current',$new->fresh()->freshness_status);
        $this->assertDatabaseHas('workflow_events',[
            'event_type'=>'research.stale','aggregate_type'=>'research_item',
            'aggregate_id'=>$old->id,
        ]);
        $this->assertSame(0,app(ResearchFreshnessGuard::class)->expire());
        $this->assertDatabaseCount('workflow_events',1);
    }

    public function test_non_overseas_research_max_age_is_capped_at_ninety_days(): void
    {
        $expired=$this->record('tools',95,1000);
        $this->artisan('aadi:maintenance')->assertExitCode(0);
        $this->assertSame('stale',$expired->fresh()->freshness_status);
    }

    public function test_stale_item_fails_immediate_freshness_check_before_cron_sweep(): void
    {
        $item=$this->record('overseas-job-placement',8);
        $brand=$item->brand;
        $this->assertSame('current',$item->freshness_status);
        $this->assertFalse(app(ResearchFreshnessGuard::class)->isCurrent($brand,$item));
        $this->assertSame('current',$item->fresh()->freshness_status); // Pure gate does not mutate.
        $this->artisan('aadi:maintenance')->assertExitCode(0);
        $this->assertSame('stale',$item->fresh()->freshness_status);
    }

    public function test_shorter_owner_freshness_window_is_respected(): void
    {
        $item=$this->record('custom',10,3);
        $this->assertSame(1,app(ResearchFreshnessGuard::class)->expire());
        $this->assertSame('stale',$item->fresh()->freshness_status);
    }
}
