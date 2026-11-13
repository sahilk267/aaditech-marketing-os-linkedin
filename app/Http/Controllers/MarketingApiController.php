<?php

namespace App\Http\Controllers;

use App\Domain\Analytics\WeeklyReport;
use App\Domain\Content\ContentPolicy;
use App\Domain\Research\SourcePolicy;
use App\Domain\Workflow\JobEngine;
use App\Models\AnalyticsSnapshot;
use App\Models\Brand;
use App\Models\BrandSource;
use App\Models\Campaign;
use App\Models\ContentItem;
use App\Models\ContentReview;
use App\Models\PublishingJob;
use App\Models\ResearchItem;
use App\Models\SocialAccount;
use App\Models\WorkflowJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketingApiController extends Controller
{
    private function access(Request $r, Brand $brand, bool $write = false): void
    {
        $user=$r->user();
        if (!$user||!$user->hasBrandAccess($brand)) abort(403);
        if ($write && !in_array($user->role,['admin','editor'],true)) abort(403);
    }
    private function admin(Request $r): void { if (!$r->user()?->isAdmin()) abort(403); }

    public function syncFreeCatalog(Request $r, JobEngine $engine): JsonResponse
    {
        $this->admin($r);
        $job=$engine->enqueue('ai.catalog_sync',[],null,'ai-catalog:'.now()->format('Y-m-d-H'));
        return response()->json(['job_id'=>$job->id,'status'=>$job->status],202);
    }
    public function overview(Request $r, Brand $brand): JsonResponse
    {
        $this->access($r,$brand);
        return response()->json([
            'brand'=>['id'=>$brand->id,'name'=>$brand->name],
            'queued'=>WorkflowJob::query()->where('brand_id',$brand->id)->whereIn('status',['queued','leased','running','retry_wait'])->count(),
            'review_queue'=>ContentItem::query()->where('brand_id',$brand->id)->where('status','needs_review')->count(),
            'scheduled'=>PublishingJob::query()->where('brand_id',$brand->id)->where('status','scheduled')->count(),
            // Every capability is a non-sensitive readiness signal, not a
            // promise that an external provider granted its API permissions.
            'capabilities'=>[
                'offline_drafts'=>true,
                'offline_svg'=>true,
                'manual_publish_package'=>true,
                'worker_connected'=>\App\Models\WorkerNode::query()
                    ->where('status','online')->where('last_heartbeat_at','>=',now()->subMinutes(5))->exists(),
                'free_ai_catalog_ready'=>\App\Models\AiModel::query()
                    ->where('provider','openrouter')->where('is_enabled',true)->where('is_free',true)
                    ->where('input_price',0)->where('output_price',0)
                    ->where('last_verified_at','>=',now()->subDay())->exists(),
                'approved_sources'=>BrandSource::query()->where('brand_id',$brand->id)->where('allowed',true)->count(),
                'official_publish_accounts'=>SocialAccount::query()->where('brand_id',$brand->id)
                    ->whereIn('platform',['linkedin','facebook'])
                    ->where('connection_status','connected')->where('api_access_approved',true)
                    ->get(['platform','token_expires_at','scopes'])->filter(fn ($account)=>
                        (!$account->token_expires_at || $account->token_expires_at->gt(now()->addMinutes(5))) &&
                        (($account->platform==='linkedin' &&
                          in_array('w_organization_social',$account->scopes??[],true)) ||
                         ($account->platform==='facebook' &&
                          count(array_diff(['pages_show_list','pages_read_engagement','pages_manage_posts'],$account->scopes??[]))===0)))
                    ->pluck('platform')->unique()->values()->all(),
            ],
        ]);
    }
    public function sources(Request $r, Brand $brand): JsonResponse
    { $this->access($r,$brand);return response()->json(BrandSource::query()->where('brand_id',$brand->id)->paginate(30)); }
    public function sourceCreate(Request $r, Brand $brand): JsonResponse
    {
        $this->access($r,$brand,true);
        $in=$r->validate(['url'=>['required','url','max:2048'],'source_type'=>['required','in:rss,official_blog,public_page,manual'],
            'trust_tier'=>['required','in:A,B,C,D']]);
        SourcePolicy::assertPublicHttps($in['url']);
        // Sources must be approved by an admin; self-declared trust is not autonomous evidence.
        $source=BrandSource::query()->firstOrCreate(['brand_id'=>$brand->id,'canonical_hash'=>hash('sha256',strtolower($in['url']))],[
            'source_url'=>$in['url'],'source_type'=>$in['source_type'],'trust_tier'=>$in['trust_tier'],'allowed'=>false,
        ]);
        return response()->json(['source'=>$source,'requires_admin_approval'=>!$source->allowed],201);
    }
    public function sourceApprove(Request $r,Brand $brand,int $sourceId): JsonResponse
    {
        $this->admin($r);$this->access($r,$brand);
        $source=BrandSource::query()->where('brand_id',$brand->id)->findOrFail($sourceId);
        SourcePolicy::assertPublicHttps($source->source_url);
        $source->allowed=true;$source->save();return response()->json(['source'=>$source]);
    }
    public function researchQueue(Request $r,Brand $brand,int $sourceId,JobEngine $engine): JsonResponse
    {
        $this->access($r,$brand,true);
        $source=BrandSource::query()->where('brand_id',$brand->id)->findOrFail($sourceId);
        if (!$source->allowed) abort(403,'Source is not approved');
        $job=$engine->enqueue('research.fetch',['source_id'=>$source->id,'url'=>$source->source_url,'type'=>$source->source_type],$brand->id,
            'research:'.$brand->id.':'.$source->id.':'.now()->toDateString());
        return response()->json(['job_id'=>$job->id,'status'=>$job->status],202);
    }
    public function research(Request $r,Brand $brand): JsonResponse
    { $this->access($r,$brand);return response()->json(ResearchItem::query()->where('brand_id',$brand->id)->latest()->paginate(30)); }
    public function researchVerify(Request $r,Brand $brand,int $itemId): JsonResponse
    {
        $this->access($r,$brand,true);
        if ($brand->slug==='overseas-job-placement') $this->admin($r);
        $item=ResearchItem::query()->where('brand_id',$brand->id)->findOrFail($itemId);
        $source=BrandSource::query()->where('brand_id',$brand->id)->findOrFail($item->source_id);
        if (!$source->allowed || ($brand->slug==='overseas-job-placement' && $source->trust_tier!=='A')) abort(422,'Official Tier A evidence required');
        $item->verification_status='verified'; $item->save();
        return response()->json(['id'=>$item->id,'verification_status'=>'verified']);
    }
    public function campaigns(Request $r,Brand $brand): JsonResponse
    { $this->access($r,$brand);return response()->json(Campaign::query()->where('brand_id',$brand->id)->latest()->paginate(30)); }
    public function campaignCreate(Request $r,Brand $brand): JsonResponse
    {
        $this->access($r,$brand,true);
        $in=$r->validate(['name'=>['required','string','max:255'],'objective'=>['required','string','max:255'],
            'audience'=>['required','string','max:255'],'content_pillars'=>['required','array','max:20'],
            'content_pillars.*'=>['string','max:100'],'start_date'=>['nullable','date'],
            'end_date'=>['nullable','date','after_or_equal:start_date']]);
        return response()->json(['campaign'=>Campaign::query()->create($in+['brand_id'=>$brand->id,'status'=>'draft'])],201);
    }
    public function content(Request $r,Brand $brand): JsonResponse
    { $this->access($r,$brand);return response()->json(ContentItem::query()->where('brand_id',$brand->id)->latest()->paginate(30)); }
    public function contentGenerate(Request $r,Brand $brand,JobEngine $engine): JsonResponse
    {
        $this->access($r,$brand,true);
        $in=$r->validate(['topic'=>['required','string','max:255'],'platform'=>['required','in:linkedin,facebook,instagram,youtube,pinterest,threads,tiktok'],
            'language'=>['required','string','max:12'],'content_type'=>['required','string','max:48'],
            'source_ids'=>['required','array','max:20'],'source_ids.*'=>['integer','distinct','min:1']]);
        $verified=ResearchItem::query()->where('brand_id',$brand->id)->where('verification_status','verified')->where('freshness_status','current')->whereIn('id',$in['source_ids'])->count();
        if ($verified!==count($in['source_ids'])) throw ValidationException::withMessages(['source_ids'=>'Only verified same-brand research may be supplied']);
        $job=$engine->enqueue('content.generate', $in+['brand_slug'=>$brand->slug],$brand->id);
        return response()->json(['job_id'=>$job->id],202);
    }
    public function contentApprove(Request $r,Brand $brand,int $contentId,ContentPolicy $policy): JsonResponse
    {
        $this->access($r,$brand,true);
        $item=DB::transaction(function () use ($policy,$brand,$contentId,$r): ContentItem {
            $item=ContentItem::query()->where('brand_id',$brand->id)->lockForUpdate()->findOrFail($contentId);
            if ($brand->slug==='overseas-job-placement'||$item->risk_level!=='low') $this->admin($r);
            $policy->requireApproval($item,$r->user()->id);
            ContentReview::query()->create(['content_item_id'=>$item->id,'review_type'=>'human',
                'result'=>'approved','findings'=>[],'reviewer_id'=>$r->user()->id,'reviewed_at'=>now()]);
            return $item;
        },3);
        return response()->json(['content_id'=>$item->id,'status'=>$item->status]);
    }
    public function render(Request $r,Brand $brand,int $contentId,JobEngine $engine): JsonResponse
    {
        $this->access($r,$brand,true);
        $item=ContentItem::query()->where('brand_id',$brand->id)->findOrFail($contentId);
        $job=$engine->enqueue('media.render',['content_item_id'=>$item->id],$brand->id,'render:'.$brand->id.':'.$item->id);
        return response()->json(['job_id'=>$job->id],202);
    }
    public function schedule(Request $r,Brand $brand,int $contentId,ContentPolicy $policy,JobEngine $engine, \App\Domain\Publishing\PublicationIntegrity $integrity): JsonResponse
    {
        $this->access($r,$brand,true);
        $in=$r->validate(['platform'=>['required','in:linkedin,facebook,instagram,youtube,pinterest,threads,tiktok'],
            'social_account_id'=>['nullable','integer','min:1'],'scheduled_at'=>['required','date','after:now']]);
        $item=ContentItem::query()->where('brand_id',$brand->id)->findOrFail($contentId);
        $policy->requirePublishable($item);
        if ($in['platform']==='linkedin' && in_array($item->content_type,['image_post','visual_post'],true) &&
            !\App\Models\MediaAsset::query()->where('brand_id',$brand->id)->where('content_item_id',$item->id)
                ->where('asset_type','png')->exists()) {
            throw ValidationException::withMessages(['media'=>'Render a verified PNG image before scheduling LinkedIn image content']);
        }
        $account=null;
        if (!empty($in['social_account_id'])) {
            $account=SocialAccount::query()->where('brand_id',$brand->id)->findOrFail($in['social_account_id']);
            if ($account->platform!==$in['platform']) abort(422,'Account platform mismatch');
        }
        $supported=$account && $account->connection_status==='connected' && $account->api_access_approved &&
            (($in['platform']==='linkedin') ||
             ($in['platform']==='facebook' &&
              in_array($item->content_type,['text','educational_post'],true) &&
              in_array('pages_manage_posts',$account->scopes??[],true) &&
              in_array('pages_show_list',$account->scopes??[],true) &&
              in_array('pages_read_engagement',$account->scopes??[],true)));
        $p=DB::transaction(function() use ($in,$brand,$item,$account,$supported,$engine,$policy,$integrity): PublishingJob {
            $current = ContentItem::query()->where('brand_id',$brand->id)->lockForUpdate()->findOrFail($item->id);
            $policy->requirePublishable($current);
            $seal = $integrity->fingerprint($current, $in['platform']);
            $key = hash('sha256',json_encode([
                $brand->id,$current->id,$in['platform'],$in['scheduled_at'],$account?->id,$seal,
            ],JSON_THROW_ON_ERROR));
            $publication=PublishingJob::query()->firstOrCreate(
                ['idempotency_key'=>$key],
                [
                    'brand_id'=>$brand->id,'content_item_id'=>$current->id,'social_account_id'=>$account?->id,
                    'platform'=>$in['platform'],'scheduled_at'=>$in['scheduled_at'],
                    'status'=>$supported?'scheduled':'manual_ready',
                    'request_metadata'=>['content_fingerprint'=>$seal,'snapshot_version'=>'publication-v1'],
                ]
            );
            if ((int)$publication->brand_id!==(int)$brand->id || (int)$publication->content_item_id!==(int)$current->id ||
                $publication->platform!==$in['platform']) abort(409,'Publishing key conflict');
            if ($supported && $publication->wasRecentlyCreated) {
                $engine->enqueue('publish.post',['publishing_job_id'=>$publication->id],$brand->id,
                    'publish:'.$publication->id,$in['scheduled_at']);
            }
            return $publication;
        });
        return response()->json(['publishing_job_id'=>$p->id,'status'=>$p->status],201);
    }
    public function calendar(Request $r,Brand $brand): JsonResponse
    {
        $this->access($r,$brand);
        return response()->json(PublishingJob::query()->where('brand_id',$brand->id)->orderBy('scheduled_at')->paginate(50,
            ['id','content_item_id','platform','scheduled_at','status','permalink']));
    }
    public function manualPackage(Request $r,Brand $brand,int $publicationId): JsonResponse
    {
        $this->access($r,$brand);
        $p=PublishingJob::query()->where('brand_id',$brand->id)->where('status','manual_ready')->findOrFail($publicationId);
        $item=ContentItem::query()->where('brand_id',$brand->id)->findOrFail($p->content_item_id);
        app(ContentPolicy::class)->requirePublishable($item);
        $expected = ($p->request_metadata ?? [])['content_fingerprint'] ?? null;
        if ($expected && !hash_equals((string)$expected, app(\App\Domain\Publishing\PublicationIntegrity::class)->fingerprint($item,$p->platform))) abort(409,'Content changed since package was prepared; create a new reviewed package');
        $variant=($item->platform_variants??[])[$p->platform]??[];
        $assets = \App\Models\MediaAsset::query()
            ->where('brand_id', $brand->id)
            ->where('content_item_id', $item->id)
            ->whereIn('asset_type', ['svg', 'png', 'carousel'])
            ->orderBy('id')->get(['id', 'asset_type', 'mime_type', 'width', 'height'])
            ->map(fn ($asset) => [
                'id' => $asset->id, 'type' => $asset->asset_type,
                'mime_type' => $asset->mime_type,
                'width' => $asset->width, 'height' => $asset->height,
                'download_url' => '/brands/'.$brand->id.'/assets/'.$asset->id.'/download',
            ])->all();
        return response()->json([
            'platform' => $p->platform,
            'caption' => is_array($variant) ? ($variant['body'] ?? $item->body) : $item->body,
            'hashtags' => $item->hashtags,
            'cta' => $item->cta,
            'status' => 'manual_ready',
            'media' => $assets,
        ]);
    }
    public function analyticsImport(Request $r,Brand $brand): JsonResponse
    {
        $this->access($r,$brand,true);
        $in=$r->validate(['publishing_job_id'=>['required','integer','min:1'],'metric_name'=>['required','string','max:70'],
            'metric_value'=>['nullable','numeric'],'metric_definition'=>['required','string','max:1000'],
            'captured_at'=>['required','date']]);
        $p=PublishingJob::query()->where('brand_id',$brand->id)->findOrFail($in['publishing_job_id']);
        $m=AnalyticsSnapshot::query()->create($in+['platform'=>$p->platform,'source'=>'manual']);
        return response()->json(['metric_id'=>$m->id],201);
    }
    public function report(Request $r,Brand $brand,WeeklyReport $report): JsonResponse
    { $this->access($r,$brand);return response()->json($report->forBrand($brand->id)); }
}
