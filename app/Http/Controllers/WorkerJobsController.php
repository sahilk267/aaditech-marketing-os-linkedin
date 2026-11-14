<?php

namespace App\Http\Controllers;

use App\Domain\Workflow\JobEngine;
use App\Domain\Workflow\JobResultProcessor;
use Illuminate\Support\Facades\DB;
use App\Domain\Publishing\PublishService;
use App\Models\AiModel;
use App\Models\AiUsage;
use App\Models\WorkerCredential;
use App\Models\WorkflowJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkerJobsController extends Controller
{
    private function attempt(Request $r): int
    {
        return (int)$r->validate(['attempt_count'=>['required','integer','min:1']])['attempt_count'];
    }

    private function identity(Request $r): array
    {
        /** @var WorkerCredential $credential */
        $credential=$r->attributes->get('worker_credential');
        if (!$credential?->node) abort(409,'Register worker first');
        return [(int)$credential->node->id, $credential->allowed_capabilities ?? []];
    }

    public function claim(Request $r, JobEngine $engine): JsonResponse
    {
        [$node,$caps]=$this->identity($r);
        $data=$r->validate(['lease_seconds'=>['nullable','integer','between:45,900']]);
        $job=$engine->claim($node,$caps,$data['lease_seconds']??180);
        return response()->json(['job'=>$job?->only(['id','job_type','brand_id','payload','status','lease_expires_at','attempt_count']), 'server_time'=>now()->toIso8601String()]);
    }
    public function start(int $id, Request $r, JobEngine $engine): JsonResponse
    { [$node]=$this->identity($r);return response()->json(['job'=>$engine->start($id,$node,$this->attempt($r))]); }
    public function renew(int $id, Request $r, JobEngine $engine): JsonResponse
    { [$node]=$this->identity($r);$in=$r->validate(['attempt_count'=>['required','integer','min:1'],'lease_seconds'=>['nullable','integer','between:45,900']]);return response()->json(['job'=>$engine->renew($id,$node,(int)$in['attempt_count'],$in['lease_seconds']??180)]); }
    public function complete(int $id, Request $r, JobEngine $engine, JobResultProcessor $processor): JsonResponse
    {
        [$node]=$this->identity($r);$in=$r->validate(['attempt_count'=>['required','integer','min:1'],'result'=>['required','array','max:30']]);
        return response()->json(['job'=>DB::transaction(function() use ($engine,$processor,$id,$node,$in) {
            $job=WorkflowJob::query()->lockForUpdate()->findOrFail($id);
            if ((int)$job->attempt_count!==(int)$in['attempt_count']) abort(409,'Stale job attempt');
            if ($job->status!=='succeeded') {
                if ($job->status!=='running'||(int)$job->lease_owner!==$node||!$job->lease_expires_at||$job->lease_expires_at->lte(now())) abort(409);
                $processor->accept($job,$in['result']);
            }
            return $engine->complete($id,$node,(int)$in['attempt_count'],$in['result']);
        })]);
    }
    public function fail(int $id, Request $r, JobEngine $engine): JsonResponse
    {
        [$node]=$this->identity($r);$in=$r->validate(['error_code'=>['required','string','regex:/^[A-Za-z0-9_.-]{1,96}$/'], 'retryable'=>['required','boolean'], 'attempt_count'=>['required','integer','min:1']]);
        return response()->json(['job'=>$engine->fail($id,$node,(int)$in['attempt_count'],$in['error_code'],$in['retryable'])]);
    }
    public function config(Request $r): JsonResponse
    {
        [, $caps]=$this->identity($r);
        return response()->json(['capabilities'=>$caps,'poll_seconds'=>10,'server_time'=>now()->toIso8601String(),
            'strict_zero_cost'=>true, 'max_parallel_generations'=>1]);
    }
    public function context(int $id, Request $r): JsonResponse
    {
        [$node, $caps]=$this->identity($r);
        $job=WorkflowJob::query()->findOrFail($id);
        if (!in_array($job->job_type,$caps,true)||$job->status!=='running'||(int)$job->lease_owner!==$node||
            (int)$job->attempt_count!==$this->attempt($r)||
            !$job->lease_expires_at||$job->lease_expires_at->lte(now())) abort(403);
        $brand=\App\Models\Brand::query()->findOrFail($job->brand_id);
        $config=$brand->brand_config??[];
        $safeBrand=[
            'name'=>$brand->name,'slug'=>$brand->slug,
            'voice'=>$config['voice']??'', 'audience'=>$config['audience']??'',
            'approved_cta'=>$config['cta']??'',
            'colors'=>$config['colors']??[],
        ];
        $data=['brand'=>$safeBrand];
        if ($job->job_type==='content.generate') {
            $ids=$job->payload['source_ids']??[];
            $items=\App\Models\ResearchItem::query()->where('brand_id',$brand->id)
                ->whereIn('id',is_array($ids)?array_slice($ids,0,20):[])
                ->where('verification_status','verified')->where('freshness_status','current')->get();
            $data['facts']=$items->map(fn($item)=>[
                'research_item_id'=>$item->id,'title'=>$item->title,
                'summary'=>mb_substr($item->summary??'',0,1200),
                'facts'=>array_slice($item->extracted_facts??[],0,6),
            ])->all();
        }
        if ($job->job_type==='media.render') {
            $item=\App\Models\ContentItem::query()->where('brand_id',$brand->id)
                ->findOrFail((int)($job->payload['content_item_id']??0));
            $data['content']=['title'=>$item->title,'body'=>mb_substr($item->body,0,2000)];
        }
        return response()->json($data);
    }
    public function models(Request $r): JsonResponse
    {
        $this->identity($r);
        $models=AiModel::query()->where('is_enabled',true)->where('is_free',true)
            ->whereNotNull('input_price')->whereNotNull('output_price')
            ->where('input_price',0)->where('output_price',0)
            ->where('provider','openrouter')->orderBy('priority')->get(['model_id','capabilities','last_verified_at']);
        return response()->json(['models'=>$models]);
    }
    public function syncCatalog(Request $r, \App\Domain\AI\ModelCatalog $catalog): JsonResponse
    {
        [, $caps]=$this->identity($r);
        if (!in_array('ai.catalog_sync',$caps,true)) abort(403);
        // Ignore worker-provided model pricing: the server independently queries the official catalog.
        // Existing worker clients can still submit 'models' for backward compatibility.
        return response()->json($catalog->syncFromProvider());
    }
    public function usage(Request $r): JsonResponse
    {
        [, $caps]=$this->identity($r);
        if (!in_array('content.generate',$caps,true)) abort(403);
        $in=$r->validate([
            'provider'=>['required','in:openrouter,ollama'], 'model_id'=>['required','string','max:180'],
            'task_type'=>['required','string','max:64'],'status'=>['required','in:succeeded,failed,rate_limited'],
            'input_tokens'=>['nullable','integer','min:0'], 'output_tokens'=>['nullable','integer','min:0'],
        ]);
        AiUsage::query()->create($in+['estimated_cost'=>0,'request_count'=>1]);
        return response()->json(['recorded'=>true],201);
    }
    public function executePublication(int $id, Request $r, PublishService $publisher): JsonResponse
    {
        [$node,$caps]=$this->identity($r);
        if (!in_array('publish.post',$caps,true)) abort(403);
        $job=WorkflowJob::query()->findOrFail($id);
        if ($job->job_type!=='publish.post'||$job->status!=='running'||(int)$job->lease_owner!==$node||
            (int)$job->attempt_count!==$this->attempt($r)||!$job->lease_expires_at||$job->lease_expires_at->lte(now())) abort(409);
        $id=(int)($job->payload['publishing_job_id']??0);
        if ($id<1) abort(422);
        return response()->json($publisher->publish($id));
    }
}
