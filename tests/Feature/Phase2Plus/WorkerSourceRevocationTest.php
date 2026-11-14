<?php

namespace Tests\Feature\Phase2Plus;

use App\Domain\Workflow\JobEngine;
use App\Http\Controllers\WorkerJobsController;
use App\Models\Brand;
use App\Models\BrandSource;
use App\Models\WorkerCredential;
use App\Models\WorkerNode;
use App\Models\WorkflowJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class WorkerSourceRevocationTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $brand=Brand::query()->create([
            'name'=>'Tech','slug'=>'tech','brand_config'=>[],'is_active'=>true,
        ]);
        $source=BrandSource::query()->create([
            'brand_id'=>$brand->id,'source_url'=>'https://docs.example.org/tech',
            'canonical_hash'=>hash('sha256','worker-source'),
            'source_type'=>'public_page','trust_tier'=>'A','allowed'=>true,
        ]);
        $node=WorkerNode::query()->create([
            'node_name'=>'desktop','platform'=>'windows','worker_version'=>'0.3.0',
            'capabilities'=>['research.fetch'],'status'=>'online',
        ]);
        $credential=WorkerCredential::query()->create([
            'label'=>'test','token_hash'=>str_repeat('0',64),
            'worker_node_id'=>$node->id,'allowed_capabilities'=>['research.fetch'],
        ]);
        $job=app(JobEngine::class)->enqueue('research.fetch',[
            'source_id'=>$source->id,'url'=>$source->source_url,
            'type'=>$source->source_type,
        ],$brand->id,'revocation-source-test');
        return [$brand,$source,$node,$credential,$job];
    }

    private function request(WorkerCredential $credential, string $method='POST', array $data=[]): Request
    {
        $r=Request::create('/api/v1/worker/jobs/claim',$method,$data);
        $r->attributes->set('worker_credential',$credential);
        return $r;
    }

    public function test_revoked_source_is_never_returned_in_worker_claim(): void
    {
        [, $source, $node, $credential, $job]=$this->fixture();
        $source->allowed=false;$source->save();
        $response=app(WorkerJobsController::class)->claim($this->request($credential),app(JobEngine::class));
        $this->assertNull($response->getData(true)['job']);
        $this->assertDatabaseHas('workflow_jobs',[
            'id'=>$job->id,'status'=>'failed','last_error'=>'SOURCE_APPROVAL_REVOKED',
        ]);
        $this->assertSame(0,WorkflowJob::query()->where('status','leased')->count());
    }

    public function test_source_revoked_after_claim_is_refused_by_worker_context(): void
    {
        [, $source, $node, $credential, $job]=$this->fixture();
        $engine=app(JobEngine::class);
        $lease=$engine->claim($node->id,['research.fetch'],60);
        $engine->start($job->id,$node->id,$lease->attempt_count);
        $source->allowed=false;$source->save();
        try {
            app(WorkerJobsController::class)->context(
                $job->id,
                $this->request($credential,'GET',['attempt_count'=>$lease->attempt_count])
            );
            $this->fail('Revoked URL was allowed to reach a worker');
        } catch (HttpException $e) {
            $this->assertSame(403,$e->getStatusCode());
        }
        $this->assertSame('running',$job->fresh()->status);
    }
}
