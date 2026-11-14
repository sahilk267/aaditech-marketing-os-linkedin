<?php

namespace App\Domain\Workflow;

use App\Models\WorkflowEvent;
use App\Models\WorkflowJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Durable MySQL jobs; all state transitions are bounded DB transactions. */
class JobEngine
{
    public const TYPES = [
        'research.fetch','content.generate','media.render','publish.post',
        'analytics.collect','report.weekly','ai.catalog_sync',
    ];

    public function enqueue(string $type, array $payload, ?int $brandId, ?string $key = null, ?string $due = null): WorkflowJob
    {
        if (!in_array($type, self::TYPES, true)) throw ValidationException::withMessages(['job_type'=>'Job type not approved']);
        return DB::transaction(function () use ($type,$payload,$brandId,$key,$due): WorkflowJob {
            if ($key) {
                $existing = WorkflowJob::query()->where('idempotency_key', $key)->first();
                if ($existing) {
                    if ($existing->job_type !== $type || (int)$existing->brand_id !== (int)$brandId) {
                        throw ValidationException::withMessages(['idempotency_key'=>'Key already used for another job']);
                    }
                    return $existing;
                }
            }
            $job = WorkflowJob::query()->create([
                'job_type'=>$type,'payload'=>$payload,'brand_id'=>$brandId,'status'=>'queued',
                'priority'=>5,'scheduled_at'=>$due ? \Carbon\Carbon::parse($due) : now(),
                'max_attempts'=>5,'idempotency_key'=>$key,
            ]);
            $this->event('workflow.queued','workflow_job',$job->id,['type'=>$type,'brand_id'=>$brandId]);
            return $job;
        });
    }

    public function claim(int $nodeId, array $capabilities, int $leaseSeconds = 180): ?WorkflowJob
    {
        $allowed = array_values(array_intersect(self::TYPES, $capabilities));
        if ($allowed === []) return null;
        $leaseSeconds = max(45, min(900, $leaseSeconds));
        return DB::transaction(function () use ($nodeId,$allowed,$leaseSeconds): ?WorkflowJob {
            $job = WorkflowJob::query()->whereIn('job_type', $allowed)
                ->where('scheduled_at','<=',now())
                ->where(function ($q): void {
                    $q->whereIn('status',['queued','retry_wait'])
                      ->orWhere(fn ($z)=>$z->whereIn('status',['leased','running'])->where('lease_expires_at','<',now()));
                })
                ->whereColumn('attempt_count','<','max_attempts')
                ->orderBy('priority')->orderBy('scheduled_at')->orderBy('id')
                ->lockForUpdate()->first();
            if (!$job) return null;
            $job->status='leased'; $job->lease_owner=$nodeId;
            $job->lease_expires_at=now()->addSeconds($leaseSeconds);
            $job->attempt_count++; $job->save();
            $this->event('workflow.leased','workflow_job',$job->id,['worker_node_id'=>$nodeId]);
            return $job;
        });
    }

    public function start(int $id, int $nodeId, int $attempt): WorkflowJob
    {
        return $this->transition($id,$nodeId,$attempt,function(WorkflowJob $job): void {
            if ($job->status === 'running') return;
            if ($job->status !== 'leased') $this->conflict();
            $job->status='running'; $job->started_at=now();
        }, 'workflow.started');
    }

    public function renew(int $id, int $nodeId, int $attempt, int $seconds = 180): WorkflowJob
    {
        return $this->transition($id,$nodeId,$attempt,function(WorkflowJob $job) use ($seconds): void {
            if (!in_array($job->status,['leased','running'],true)) $this->conflict();
            $job->lease_expires_at=now()->addSeconds(max(45,min(900,$seconds)));
        }, 'workflow.renewed');
    }

    public function complete(int $id, int $nodeId, int $attempt, array $result): WorkflowJob
    {
        return DB::transaction(function () use ($id,$nodeId,$attempt,$result): WorkflowJob {
            $job=WorkflowJob::query()->lockForUpdate()->findOrFail($id);
            if ((int)$job->attempt_count !== $attempt) $this->conflict();
            if ($job->status==='succeeded' && (int)$job->lease_owner===$nodeId) return $job;
            $this->checkLease($job,$nodeId,$attempt);
            if ($job->status!=='running') $this->conflict();
            $job->status='succeeded'; $job->result=$result; $job->completed_at=now();
            $job->lease_expires_at=null; $job->last_error=null; $job->save();
            $this->event('workflow.succeeded','workflow_job',$job->id,['type'=>$job->job_type]);
            return $job;
        });
    }

    public function fail(int $id, int $nodeId, int $attempt, string $code, bool $retryable = true): WorkflowJob
    {
        return DB::transaction(function () use ($id,$nodeId,$attempt,$code,$retryable): WorkflowJob {
            $job=WorkflowJob::query()->lockForUpdate()->findOrFail($id);
            if ((int)$job->attempt_count !== $attempt) $this->conflict();
            if (in_array($job->status,['dead_letter','failed'],true) && (int)$job->lease_owner===$nodeId) return $job;
            $this->checkLease($job,$nodeId,$attempt);
            if (!in_array($job->status,['leased','running'],true)) $this->conflict();
            // Only a bounded machine-readable error code is retained, never raw model/provider output.
            $job->last_error=substr(preg_replace('/[^A-Za-z0-9_.-]/','_', $code),0,96);
            if (!$retryable) $job->status='failed';
            elseif ($job->attempt_count >= $job->max_attempts) $job->status='dead_letter';
            else {
                $job->status='retry_wait';
                $delay=min(3600, 15*(2 ** min(8,$job->attempt_count-1)))+random_int(0,15);
                $job->scheduled_at=now()->addSeconds($delay);
            }
            if ($job->status!=='retry_wait') $job->completed_at=now();
            $job->lease_expires_at=null; $job->save();
            $this->event('workflow.'.$job->status,'workflow_job',$job->id,['error_code'=>$job->last_error]);
            return $job;
        });
    }

    private function transition(int $id,int $nodeId,int $attempt,\Closure $callback,string $event): WorkflowJob
    {
        return DB::transaction(function () use ($id,$nodeId,$attempt,$callback,$event): WorkflowJob {
            $job=WorkflowJob::query()->lockForUpdate()->findOrFail($id);
            $this->checkLease($job,$nodeId,$attempt);
            $before=$job->status;
            $callback($job); $job->save();
            // Repeated start by the same lease does not duplicate lifecycle events.
            if ($before !== $job->status || $event !== 'workflow.started') {
                $this->event($event,'workflow_job',$job->id,[]);
            }
            return $job;
        },3);
    }

    private function checkLease(WorkflowJob $job,int $nodeId,int $attempt): void
    {
        if ((int)$job->attempt_count !== $attempt ||
            (int)$job->lease_owner !== $nodeId || !$job->lease_expires_at ||
            $job->lease_expires_at->lte(now())) $this->conflict();
    }

    private function conflict(): never { abort(409,'Invalid workflow state or expired lease'); }

    public function event(string $type,string $aggregate,int $id,array $data): void
    {
        WorkflowEvent::query()->create(['event_type'=>$type,'aggregate_type'=>$aggregate,'aggregate_id'=>$id,
            'payload'=>$data,'occurred_at'=>now(),'status'=>'pending']);
    }
}
