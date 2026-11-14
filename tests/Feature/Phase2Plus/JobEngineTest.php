<?php

namespace Tests\Feature\Phase2Plus;

use App\Domain\Workflow\JobEngine;
use App\Models\Brand;
use App\Models\WorkerNode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_worker_claim_requires_explicit_capabilities_and_lease_owner(): void
    {
        $brand=Brand::create(['name'=>'Example','slug'=>'example','brand_config'=>[],'is_active'=>true]);
        $node=WorkerNode::create(['node_name'=>'node','platform'=>'windows','worker_version'=>'test','capabilities'=>['research.fetch'],'status'=>'online']);
        $other=WorkerNode::create(['node_name'=>'other','platform'=>'windows','worker_version'=>'test','capabilities'=>['research.fetch'],'status'=>'online']);
        $engine=app(JobEngine::class);
        $job=$engine->enqueue('research.fetch',['source_id'=>1],$brand->id,'dedupe-test');
        $this->assertEquals($job->id,$engine->enqueue('research.fetch',['source_id'=>1],$brand->id,'dedupe-test')->id);
        $this->assertNull($engine->claim($node->id,['media.render']));
        $leased=$engine->claim($node->id,['research.fetch']);
        $this->assertEquals($job->id,$leased->id);
        $this->assertNull($engine->claim($other->id,['research.fetch']));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $engine->complete($job->id,$other->id,$leased->attempt_count,['ok'=>true]);
    }

    public function test_retry_moves_to_dead_letter_with_bounded_attempts(): void
    {
        $brand=Brand::create(['name'=>'Example','slug'=>'example','brand_config'=>[],'is_active'=>true]);
        $node=WorkerNode::create(['node_name'=>'node','platform'=>'windows','worker_version'=>'test','capabilities'=>['research.fetch'],'status'=>'online']);
        $engine=app(JobEngine::class);
        $job=$engine->enqueue('research.fetch',['source_id'=>1],$brand->id);
        $job->max_attempts=1;$job->save();
        $lease=$engine->claim($node->id,['research.fetch']);
        $engine->start($job->id,$node->id,$lease->attempt_count);
        $failed=$engine->fail($job->id,$node->id,$lease->attempt_count,'HTTP_500',true);
        $this->assertEquals('dead_letter',$failed->status);
    }
}
