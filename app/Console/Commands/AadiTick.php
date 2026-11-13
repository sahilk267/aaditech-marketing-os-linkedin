<?php
namespace App\Console\Commands;

use App\Domain\Workflow\JobEngine;
use App\Models\Brand;
use App\Models\BrandSource;
use App\Models\ResearchItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/** Low-frequency deterministic seed jobs; no autonomous publication. */
class AadiTick extends Command
{
    protected $signature='aadi:tick';
    protected $description='Schedule allowed source refresh and evidence-backed drafts for approved brands';
    public function handle(JobEngine $jobs, \App\Domain\Research\ResearchFreshnessGuard $freshness):int
    {
        $freshness->expire(); // Never schedule drafts from aged evidence.
        // Idempotency keys in database are authoritative; cache is only an optimization.
        $today=now('UTC')->format('Y-m-d');
        $enqueued=0;
        foreach (Brand::query()->where('is_active',true)->get() as $brand) {
            $sources=BrandSource::query()->where('brand_id',$brand->id)->where('allowed',true)->limit(10)->get();
            foreach ($sources as $source) {
                if ($source->last_fetched_at && $source->last_fetched_at->gte(now()->subDay())) continue;
                $jobs->enqueue('research.fetch',['source_id'=>$source->id,'url'=>$source->source_url,'type'=>$source->source_type],
                    $brand->id,'refresh:'.$brand->id.':'.$source->id.':'.$today);
                $enqueued++;
            }
            // Never use unverified evidence or claim that a job/employer/salary exists.
            $facts=ResearchItem::query()->where('brand_id',$brand->id)->where('verification_status','verified')
                ->where('freshness_status','current')->latest('fetched_at')->limit(2)->pluck('id')->all();
            if ($facts) {
                $config=$brand->brand_config??[];
                $pillar=(string)($config['content_pillars'][0]??'Helpful industry tips');
                $jobs->enqueue('content.generate',[
                    'topic'=>$pillar, 'platform'=>'linkedin','language'=>'en','content_type'=>'educational_post',
                    'source_ids'=>$facts,'brand_slug'=>$brand->slug,
                ],$brand->id,'daily-draft:'.$brand->id.':linkedin:'.$today);
                $enqueued++;
            }
        }
        $jobs->enqueue('ai.catalog_sync',[],null,'ai-catalog:'.$today);
        $this->info('Tick evaluated; source/draft scheduling candidates: '.$enqueued.'; publications require explicit approval.');
        return self::SUCCESS;
    }
}
