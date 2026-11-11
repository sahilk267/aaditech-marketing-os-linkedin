<?php

namespace App\Domain\Publishing;

use App\Domain\Content\ContentPolicy;
use App\Models\ContentItem;
use App\Models\PublishingJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublishService
{
    public function __construct(private ContentPolicy $policy, private PlatformRegistry $registry) { }

    public function publish(int $id): array
    {
        // Mark sending before the external side effect. Uncertain outcomes require reconciliation.
        $state=DB::transaction(function() use ($id): array {
            $job=PublishingJob::query()->lockForUpdate()->findOrFail($id);
            if ($job->status==='succeeded') return ['done'=>true,'job'=>$job];
            if ($job->status==='manual_ready') return ['manual_ready'=>true,'job'=>$job];
            if ($job->status!=='scheduled'||$job->scheduled_at->isFuture()) abort(409,'Publication not due or already attempted');
            $content=ContentItem::query()->findOrFail($job->content_item_id);
            if ((int)$content->brand_id !==(int)$job->brand_id) abort(409,'Cross-brand publishing denied');
            $this->policy->requirePublishable($content);
            $account=$job->social_account_id ? $job->socialAccount : null;
            if (!$account|| (int)$account->brand_id !== (int)$job->brand_id||$job->platform!=='linkedin'|| !$account->api_access_approved||$account->connection_status!=='connected') {
                $job->status='manual_ready'; $job->save();
                return ['manual_ready'=>true,'job'=>$job];
            }
            $job->status='sending'; $job->attempt_count++; $job->save();
            return ['send'=>true,'job'=>$job];
        });
        if (isset($state['done'])) return ['status'=>'succeeded','platform_post_id'=>$state['job']->platform_post_id];
        if (isset($state['manual_ready'])) return ['status'=>'manual_ready'];
        /** @var PublishingJob $job */
        $job=$state['job'];
        try {
            $response=$this->registry->forPlatform($job->platform)->publish($job->contentItem,$job->socialAccount);
            $job->status='succeeded'; $job->platform_post_id=$response['platform_post_id'];
            $job->permalink=$response['permalink']; $job->published_at=now();
            $job->response_metadata=['http_status'=>$response['http_status']]; $job->last_error=null;
            $job->save();
            return ['status'=>'succeeded','platform_post_id'=>$job->platform_post_id];
        } catch (\Throwable $e) {
            // Provider may have published before HTTP timeout or malformed response.
            // No blind retries; operator must reconcile using approved read access.
            $job->status='needs_reconciliation';
            $job->last_error=substr(preg_replace('/[^A-Za-z0-9_.-]/','_', $e instanceof ValidationException ? 'LINKEDIN_VALIDATION' : $e->getMessage()),0,96);
            $job->save();
            return ['status'=>'needs_reconciliation','error_code'=>$job->last_error];
        }
    }
}
