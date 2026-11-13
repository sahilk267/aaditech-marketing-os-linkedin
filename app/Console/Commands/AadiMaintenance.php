<?php

namespace App\Console\Commands;

use App\Models\WorkerNode;
use App\Models\SocialAccount;
use App\Models\WorkflowEvent;
use App\Models\WorkflowJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AadiMaintenance extends Command
{
    protected $signature='aadi:maintenance';
    protected $description='Mark disconnected workers, clean expired nonces and inspect retry backlog';
    public function handle(\App\Domain\Research\ResearchFreshnessGuard $freshness): int
    {
        $staleResearch=$freshness->expire();
        $offline=WorkerNode::query()->where('status','online')->where('last_heartbeat_at','<',now()->subMinutes(4))->get();
        foreach ($offline as $node) {
            DB::transaction(function() use ($node):void {
                $node->status='offline';$node->save();
                WorkflowEvent::query()->create(['event_type'=>'worker.heartbeat_missed','aggregate_type'=>'worker_node',
                    'aggregate_id'=>$node->id,'payload'=>['node_name'=>$node->node_name], 'occurred_at'=>now(),'status'=>'pending']);
            });
        }
        // Exhausted expired leases cannot be claimed again; dead-letter them for operator review.
        $exhausted=WorkflowJob::query()->whereIn('status',['leased','running'])
            ->where('lease_expires_at','<',now())->whereColumn('attempt_count','>=','max_attempts')->get();
        foreach ($exhausted as $job) {
            DB::transaction(function() use ($job):void {
                $locked=WorkflowJob::query()->lockForUpdate()->find($job->id);
                if (!$locked || !in_array($locked->status,['leased','running'],true) || !$locked->lease_expires_at || $locked->lease_expires_at->isFuture()) return;
                $locked->status='dead_letter'; $locked->last_error='LEASE_EXPIRED_MAX_ATTEMPTS';
                $locked->lease_expires_at=null;$locked->completed_at=now();$locked->save();
                WorkflowEvent::query()->create(['event_type'=>'workflow.dead_letter','aggregate_type'=>'workflow_job',
                    'aggregate_id'=>$locked->id,'payload'=>['error_code'=>'LEASE_EXPIRED_MAX_ATTEMPTS'],
                    'occurred_at'=>now(),'status'=>'pending']);
            });
        }
        // Provider request could have succeeded before the response was lost.
        // A stalled `sending` publication is never automatically re-posted.
        \App\Models\PublishingJob::query()->where('status','sending')
            ->where('updated_at','<',now()->subMinutes(10))
            ->update(['status'=>'needs_reconciliation','last_error'=>'UNCONFIRMED_PROVIDER_OUTCOME']);
        // Crash recovery for an alert claimed before the process stopped. Telegram
        // has no send idempotency key, so this is deliberately at-least-once.
        WorkflowEvent::query()->where('status','sending')
            ->where('updated_at','<',now()->subMinutes(15))->update(['status'=>'pending']);
        $threshold=max(1,(int)config('aadi.alerts.queue_backlog_threshold',50));
        $backlog=WorkflowJob::query()->whereIn('status',['queued','retry_wait'])->count();
        if ($backlog >= $threshold) {
            $this->eventOnceToday('workflow.backlog_high','workflow_job',0,['queued'=>$backlog]);
        }
        foreach (SocialAccount::query()->where('connection_status','connected')
            ->whereNotNull('token_expires_at')->where('token_expires_at','<=',now()->addDay())
            ->get(['id']) as $account) {
            $this->eventOnceToday('oauth.expiring','social_account',$account->id,[]);
        }
        DB::table('worker_request_nonces')->where('created_at','<',now()->subMinutes(10))->delete();
        // An expired lease is reclaimed transactionally by the next compatible worker.
        $stalled=WorkflowJob::query()->whereIn('status',['leased','running'])
            ->where('lease_expires_at','<',now())->count();
        $this->info('Offline workers: '.$offline->count().'; newly stale research: '.$staleResearch.'; expired jobs available for reclaim: '.$stalled);
        return self::SUCCESS;
    }

    private function eventOnceToday(string $type,string $aggregate,int $id,array $payload):void
    {
        $exists=WorkflowEvent::query()->where('event_type',$type)
            ->where('aggregate_type',$aggregate)->where('aggregate_id',$id)
            ->where('occurred_at','>=',now('UTC')->startOfDay())->exists();
        if (!$exists) WorkflowEvent::query()->create(['event_type'=>$type,
            'aggregate_type'=>$aggregate,'aggregate_id'=>$id,'payload'=>$payload,
            'occurred_at'=>now(),'status'=>'pending']);
    }
}
