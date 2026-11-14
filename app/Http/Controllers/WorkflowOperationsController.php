<?php

namespace App\Http\Controllers;

use App\Domain\Research\SourcePolicy;
use App\Domain\Workflow\JobEngine;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\BrandSource;
use App\Models\WorkflowJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Minimal brand-safe job visibility and conservative operator recovery.
 * Never exposes job payloads, credentials or remote publish retry controls.
 */
class WorkflowOperationsController extends Controller
{
    public function index(Request $request, Brand $brand): JsonResponse
    {
        if (!$request->user()?->hasBrandAccess($brand)) abort(403);
        $input = $request->validate([
            'status'=>['nullable','in:queued,leased,running,retry_wait,failed,dead_letter,succeeded'],
        ]);
        $query = WorkflowJob::query()->where('brand_id',$brand->id);
        if (!empty($input['status'])) $query->where('status',$input['status']);
        return response()->json($query->orderByDesc('id')->paginate(30,[
            'id','brand_id','job_type','status','attempt_count','max_attempts',
            'scheduled_at','started_at','completed_at','lease_expires_at',
            'last_error',
        ]));
    }

    public function requeueResearch(Request $request, Brand $brand, int $id, JobEngine $engine): JsonResponse
    {
        if (!$request->user()?->isAdmin() || !$request->user()->hasBrandAccess($brand)) abort(403);
        $input=$request->validate([
            'reason'=>['required','string','min:30','max:1000'],
            'acknowledged'=>['required','accepted'],
        ]);
        $new=DB::transaction(function () use ($brand,$id,$request,$input,$engine): WorkflowJob {
            $job=WorkflowJob::query()->where('brand_id',$brand->id)
                ->lockForUpdate()->findOrFail($id);
            // A permanent safety failure or uncertain social publish may have
            // performed remote effects. Never requeue these blindly.
            if ($job->status!=='dead_letter' || $job->job_type!=='research.fetch') {
                throw ValidationException::withMessages(['job'=>'Only dead-letter public research fetches may be requeued']);
            }
            $payload=$job->payload??[];
            $sourceId=$payload['source_id']??null;
            if (!is_int($sourceId) || $sourceId<1 ||
                !is_string($payload['url']??null) || !is_string($payload['type']??null)) {
                throw ValidationException::withMessages(['job'=>'Malformed archived research job']);
            }
            $source=BrandSource::query()->where('brand_id',$brand->id)->findOrFail($sourceId);
            if (!$source->allowed || $source->source_url!==$payload['url'] ||
                $source->source_type!==$payload['type']) {
                throw ValidationException::withMessages(['source'=>'Archived source is no longer approved or unchanged']);
            }
            SourcePolicy::assertPublicHttps($source->source_url);
            // One child per exhausted parent, even under duplicate clicks.
            // Later failures are separate child jobs and require new admin review.
            $child=$engine->enqueue('research.fetch',$payload,$brand->id,'operator-requeue:'.$job->id);
            if ($child->wasRecentlyCreated) {
                AuditLog::query()->forceCreate([
                    'user_id'=>$request->user()->id,'actor_type'=>'user',
                    'action'=>'workflow.research_requeued',
                    'resource_type'=>'workflow_job','resource_id'=>(string)$job->id,
                    'metadata'=>[
                        'brand_id'=>$brand->id,
                        'new_job_id'=>$child->id,
                        'reason'=>trim($input['reason']),
                        'old_error'=>$job->last_error,
                    ],
                ]);
            }
            return $child;
        },3);
        return response()->json([
            'requeue_job_id'=>$new->id,'status'=>$new->status,
            'already_requested'=>!$new->wasRecentlyCreated,
            'external_publishing_retried'=>false,
        ],$new->wasRecentlyCreated?201:200);
    }
}
