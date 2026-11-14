<?php

namespace Tests\Feature\Phase2Plus;

use App\Domain\Workflow\JobEngine;
use App\Models\Brand;
use App\Models\WorkerNode;
use App\Models\WorkflowEvent;
use App\Models\WorkflowJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Simulates an ABA lease race: the *same worker node* reclaims the job,
 * but stale requests from the earlier execution must not mutate it.
 */
class JobLeaseFencingTest extends TestCase
{
    use RefreshDatabase;

    private function setupWorker(): array
    {
        $brand=Brand::query()->create([
            'name'=>'Example','slug'=>'example','brand_config'=>[],'is_active'=>true,
        ]);
        $worker=WorkerNode::query()->create([
            'node_name'=>'desktop-1','platform'=>'windows','worker_version'=>'0.3.0',
            'capabilities'=>['research.fetch'],'status'=>'online',
        ]);
        return [$brand,$worker,app(JobEngine::class)];
    }

    private function conflict(callable $action): void
    {
        try {
            $action();
            $this->fail('Expired/stale job generation unexpectedly succeeded');
        } catch (HttpException $e) {
            $this->assertSame(409,$e->getStatusCode());
        }
    }

    public function test_same_node_reclaim_rejects_old_generation_and_keeps_single_result(): void
    {
        [$brand,$worker,$engine]=$this->setupWorker();
        $job=$engine->enqueue('research.fetch',['source_id'=>2],$brand->id,'aba-job');
        $first=$engine->claim($worker->id,['research.fetch'],60);
        $this->assertSame(1,$first->attempt_count);
        $engine->start($job->id,$worker->id,$first->attempt_count);
        // A prior process stopped renewing; a replacement process using the
        // same node credential legitimately receives the next generation.
        WorkflowJob::query()->whereKey($job->id)
            ->update(['lease_expires_at'=>now()->subSecond()]);
        $second=$engine->claim($worker->id,['research.fetch'],60);
        $this->assertSame($job->id,$second->id);
        $this->assertSame(2,$second->attempt_count);
        $this->conflict(fn ()=>$engine->start($job->id,$worker->id,1));
        $this->conflict(fn ()=>$engine->renew($job->id,$worker->id,1,60));
        $this->conflict(fn ()=>$engine->complete($job->id,$worker->id,1,['stale'=>true]));
        $this->conflict(fn ()=>$engine->fail($job->id,$worker->id,1,'STALE_REQUEST',true));
        $this->assertDatabaseHas('workflow_jobs',[
            'id'=>$job->id,'attempt_count'=>2,'status'=>'leased',
        ]);
        $engine->start($job->id,$worker->id,2);
        $engine->renew($job->id,$worker->id,2,60);
        $done=$engine->complete($job->id,$worker->id,2,['ok'=>true]);
        $this->assertSame('succeeded',$done->status);
        $this->assertSame(['ok'=>true],$done->result);
        $this->assertSame('succeeded',$engine->complete($job->id,$worker->id,2,['ok'=>false])->status);
        $this->assertSame(['ok'=>true],$job->fresh()->result);
        $this->assertSame(1,WorkflowEvent::query()->where('event_type','workflow.succeeded')
            ->where('aggregate_id',$job->id)->count());
    }

    public function test_lease_expiry_cannot_be_extended_by_old_process(): void
    {
        [$brand,$worker,$engine]=$this->setupWorker();
        $job=$engine->enqueue('research.fetch',['source_id'=>3],$brand->id);
        $lease=$engine->claim($worker->id,['research.fetch'],45);
        WorkflowJob::query()->whereKey($job->id)->update(['lease_expires_at'=>now()->subSecond()]);
        $this->conflict(fn ()=>$engine->renew($job->id,$worker->id,$lease->attempt_count,900));
        $this->conflict(fn ()=>$engine->complete($job->id,$worker->id,$lease->attempt_count,['ok'=>true]));
        $this->assertSame('leased',$job->fresh()->status);
    }

    public function test_idempotent_enqueue_has_one_event_and_rejects_mismatched_payload(): void
    {
        [$brand,$worker,$engine]=$this->setupWorker();
        $one=$engine->enqueue('research.fetch',['source_id'=>2],$brand->id,'stable-key');
        $two=$engine->enqueue('research.fetch',['source_id'=>2],$brand->id,'stable-key');
        $this->assertSame($one->id,$two->id);
        $this->assertSame(1,WorkflowEvent::query()->where('event_type','workflow.queued')
            ->where('aggregate_id',$one->id)->count());
        try {
            $engine->enqueue('research.fetch',['source_id'=>999],$brand->id,'stable-key');
            $this->fail('Idempotency key reused for changed job payload');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('idempotency_key',$e->errors());
        }
        $this->assertDatabaseCount('workflow_jobs',1);
    }
}
