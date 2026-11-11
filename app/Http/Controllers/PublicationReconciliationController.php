<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\PublishingJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Recovery for ambiguous provider outcomes. Never automatically retries a request
 * which might have succeeded before the HTTP response was lost.
 */
class PublicationReconciliationController extends Controller
{
    private function authorizeAdmin(Request $request, Brand $brand): void
    {
        if (!$request->user()?->isAdmin() || !$request->user()->hasBrandAccess($brand)) abort(403);
    }

    public function index(Request $request, Brand $brand): JsonResponse
    {
        $this->authorizeAdmin($request,$brand);
        return response()->json(PublishingJob::query()->where('brand_id',$brand->id)
            ->where('status','needs_reconciliation')
            ->orderBy('updated_at')->paginate(30,['id','platform','content_item_id',
                'social_account_id','attempt_count','status','last_error','updated_at']));
    }

    public function resolve(Request $request, Brand $brand, int $id): JsonResponse
    {
        $this->authorizeAdmin($request,$brand);
        $input=$request->validate([
            'resolution'=>['required','in:confirmed_published,confirmed_not_published'],
            'verification_note'=>['required','string','min:25','max:1800'],
            'platform_post_id'=>['required_if:resolution,confirmed_published','nullable','string',
                'max:255','regex:/^[A-Za-z0-9:_-]+$/D'],
            'permalink'=>['nullable','url','max:1000','starts_with:https://'],
        ]);
        $publication=DB::transaction(function () use ($request,$brand,$id,$input):PublishingJob {
            $publication=PublishingJob::query()->where('brand_id',$brand->id)->lockForUpdate()->findOrFail($id);
            if ($publication->status!=='needs_reconciliation') abort(409,'Publication is not awaiting reconciliation');
            $published=$input['resolution']==='confirmed_published';
            $old=$publication->status;
            if ($published) {
                // Operator has independently checked the authoritative provider account/history.
                $publication->status='succeeded';
                $publication->platform_post_id=$input['platform_post_id'];
                $publication->permalink=$input['permalink']??null;
                $publication->published_at=now();
                $publication->last_error=null;
            } else {
                // No automatic retry, even when operator reports not published.
                $publication->status='manual_ready';
                $publication->last_error='RECONCILED_NOT_PUBLISHED';
            }
            $publication->response_metadata=array_merge($publication->response_metadata??[],[
                'reconciled_by'=>$request->user()->id,
                'reconciled_at'=>now()->toIso8601String(),
                'resolution'=>$input['resolution'],
            ]);
            $publication->save();
            AuditLog::query()->forceCreate([
                'user_id'=>$request->user()->id,'actor_type'=>'user',
                'action'=>'publication.reconciled','resource_type'=>'publishing_job',
                'resource_id'=>(string)$publication->id,
                'metadata'=>['brand_id'=>$brand->id,'from'=>$old,'to'=>$publication->status,
                    'provider_post_id'=>$published?$publication->platform_post_id:null,
                    'verification_note'=>$input['verification_note']],
            ]);
            return $publication;
        });
        return response()->json(['id'=>$publication->id,'status'=>$publication->status,
            'platform_post_id'=>$publication->platform_post_id]);
    }
}
