<?php

namespace App\Domain\Publishing;

use App\Domain\Content\ContentPolicy;
use App\Models\ContentItem;
use App\Models\PublishingJob;
use App\Models\WorkflowEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublishService
{
    public function __construct(private ContentPolicy $policy, private PlatformRegistry $registry, private PublicationIntegrity $integrity) { }

    public function publish(int $id): array
    {
        // Mark sending before the external side effect. Uncertain outcomes require reconciliation.
        $state=DB::transaction(function() use ($id): array {
            // Always lock content BEFORE publication so revision and send paths
            // serialize on the same row. This prevents stale approval races.
            $peek=PublishingJob::query()->findOrFail($id);
            $content=ContentItem::query()->whereKey($peek->content_item_id)
                ->lockForUpdate()->firstOrFail();
            $job=PublishingJob::query()->lockForUpdate()->findOrFail($id);
            if ((int)$job->content_item_id!==(int)$content->id) abort(409,'Publication content changed');
            if ($job->status==='succeeded') return ['done'=>true,'job'=>$job];
            if ($job->status==='manual_ready') return ['manual_ready'=>true,'job'=>$job];
            if ($job->status!=='scheduled'||$job->scheduled_at->isFuture()) abort(409,'Publication not due or already attempted');
            if ((int)$content->brand_id !==(int)$job->brand_id) abort(409,'Cross-brand publishing denied');
            $this->policy->requirePublishable($content);
            $account=$job->social_account_id ? $job->socialAccount : null;
            if (!$account|| (int)$account->brand_id !== (int)$job->brand_id||!in_array($job->platform,['linkedin','facebook'],true)||$account->platform!==$job->platform|| !$account->api_access_approved||$account->connection_status!=='connected') {
                $job->status='manual_ready'; $job->save();
                $this->event('publication.manual_ready',$job);
                return ['manual_ready'=>true,'job'=>$job];
            }
            // A scheduled post is bound to the approved content and media snapshot.
            // No fingerprint means a legacy unsealed request: never trust it for an automatic send.
            $seal = ($job->request_metadata ?? [])['content_fingerprint'] ?? null;
            $current = $this->integrity->fingerprint($content, $job->platform);
            if (!is_string($seal) || !preg_match('/^[a-f0-9]{64}$/D', $seal) ||
                !hash_equals($seal, $current)) {
                $job->status = 'blocked_review';
                $job->last_error = 'CONTENT_APPROVAL_SNAPSHOT_STALE';
                $job->save();
                $this->event('publication.blocked_review', $job);
                return ['blocked_review' => true, 'job' => $job];
            }
            $job->status='sending'; $job->attempt_count++; $job->save();
            return ['send'=>true,'job'=>$job];
        },3);
        if (isset($state['done'])) return ['status'=>'succeeded','platform_post_id'=>$state['job']->platform_post_id];
        if (isset($state['manual_ready'])) return ['status'=>'manual_ready'];
        if (isset($state['blocked_review'])) return ['status'=>'blocked_review','reason'=>'CONTENT_APPROVAL_SNAPSHOT_STALE'];
        /** @var PublishingJob $job */
        $job=$state['job'];
        try {
            $response=$this->registry->forPlatform($job->platform)->publish($job->contentItem,$job->socialAccount);
            $job->status='succeeded'; $job->platform_post_id=$response['platform_post_id'];
            $job->permalink=$response['permalink']; $job->published_at=now();
            $job->response_metadata=['http_status'=>$response['http_status']]; $job->last_error=null;
            DB::transaction(function () use ($job):void {
                $job->save();
                $this->event('publication.succeeded',$job);
            });
            return ['status'=>'succeeded','platform_post_id'=>$job->platform_post_id];
        } catch (\Throwable $e) {
            // Provider may have published before HTTP timeout or malformed response.
            // No blind retries; operator must reconcile using approved read access.
            $job->status='needs_reconciliation';
            $job->last_error=substr(preg_replace('/[^A-Za-z0-9_.-]/','_', $e instanceof ValidationException ? 'PLATFORM_VALIDATION' : $e->getMessage()),0,96);
            DB::transaction(function () use ($job):void {
                $job->save();
                $this->event('publication.needs_reconciliation',$job);
            });
            return ['status'=>'needs_reconciliation','error_code'=>$job->last_error];
        }
    }

    private function event(string $type, PublishingJob $job): void
    {
        WorkflowEvent::query()->create(['event_type'=>$type,'aggregate_type'=>'publishing_job',
            'aggregate_id'=>$job->getKey(),'payload'=>['brand_id'=>$job->brand_id,'platform'=>$job->platform],
            'occurred_at'=>now(),'status'=>'pending']);
    }
}
