<?php

namespace Tests\Feature\Phase2Plus;

use App\Domain\Workflow\JobEngine;
use App\Models\Brand;
use App\Models\BrandSource;
use App\Models\User;
use App\Models\WorkflowJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowOperationsTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        $brand=Brand::query()->create([
            'name'=>'Tech','slug'=>'tech','brand_config'=>[],'is_active'=>true,
        ]);
        $source=BrandSource::query()->create([
            'brand_id'=>$brand->id,
            'source_url'=>'https://docs.example.org/tech-notes',
            'canonical_hash'=>hash('sha256','ops-source'),
            'source_type'=>'public_page','trust_tier'=>'A','allowed'=>true,
        ]);
        $job=app(JobEngine::class)->enqueue('research.fetch',[
            'source_id'=>$source->id,
            'url'=>$source->source_url,
            'type'=>$source->source_type,
            'private_payload'=>'REDACT_BEFORE_OPERATOR_VIEW',
        ],$brand->id,'original-research-key');
        $job->status='dead_letter';$job->last_error='PROVIDER_UNAVAILABLE';
        $job->attempt_count=5;$job->max_attempts=5;$job->save();
        $admin=User::factory()->create(['role'=>'admin','is_active'=>true]);
        return [$brand,$source,$job,$admin];
    }

    public function test_brand_operator_visibility_hides_job_payload_and_other_brands(): void
    {
        [$brand,$source,$job,$admin]=$this->scenario();
        $analyst=User::factory()->create(['role'=>'analyst','is_active'=>true]);
        $analyst->brands()->attach($brand->id);
        $r=$this->actingAs($analyst)->getJson("/api/v1/brands/{$brand->id}/operations/jobs?status=dead_letter");
        $r->assertOk()->assertJsonPath('total',1)
            ->assertJsonPath('data.0.id',$job->id)
            ->assertJsonPath('data.0.status','dead_letter');
        $this->assertStringNotContainsString('REDACT_BEFORE_OPERATOR_VIEW',$r->getContent());
        $this->assertStringNotContainsString('tech-notes',$r->getContent());
        $another=Brand::query()->create(['name'=>'Other','slug'=>'other','brand_config'=>[]]);
        $this->getJson("/api/v1/brands/{$another->id}/operations/jobs")->assertForbidden();
    }

    public function test_admin_requeue_is_audited_and_idempotent_without_external_post(): void
    {
        [$brand,$source,$job,$admin]=$this->scenario();
        $url="/api/v1/brands/{$brand->id}/operations/jobs/{$job->id}/requeue-research";
        $input=[
            'reason'=>'The approved source is now accessible and I verified a controlled retry.',
            'acknowledged'=>true,
        ];
        $first=$this->actingAs($admin)->postJson($url,$input);
        $first->assertCreated()->assertJsonPath('status','queued')
            ->assertJsonPath('external_publishing_retried',false)
            ->assertJsonPath('already_requested',false);
        $childId=$first->json('requeue_job_id');
        $this->postJson($url,$input)->assertOk()
            ->assertJsonPath('requeue_job_id',$childId)
            ->assertJsonPath('already_requested',true);
        $this->assertDatabaseCount('workflow_jobs',2);
        $this->assertSame([
            'source_id'=>$source->id,'url'=>$source->source_url,'type'=>$source->source_type,
        ],WorkflowJob::query()->findOrFail($childId)->payload);
        $this->assertDatabaseHas('workflow_jobs',[
            'id'=>$childId,'job_type'=>'research.fetch','status'=>'queued',
            'idempotency_key'=>'operator-requeue:'.$job->id,
        ]);
        $this->assertDatabaseHas('audit_logs',[
            'action'=>'workflow.research_requeued',
            'resource_type'=>'workflow_job',
            'resource_id'=>(string)$job->id,
            'user_id'=>$admin->id,
        ]);
        $this->assertDatabaseCount('audit_logs',1);
        $this->assertSame('dead_letter',$job->fresh()->status);
    }

    public function test_recovery_fails_closed_for_foreign_role_unsafe_source_and_publishing(): void
    {
        [$brand,$source,$job,$admin]=$this->scenario();
        $url="/api/v1/brands/{$brand->id}/operations/jobs/{$job->id}/requeue-research";
        $input=[
            'reason'=>'The source appears safe and permitted but I need a deliberate manual retry.',
            'acknowledged'=>true,
        ];
        $editor=User::factory()->create(['role'=>'editor','is_active'=>true]);
        $editor->brands()->attach($brand->id);
        $this->actingAs($editor)->postJson($url,$input)->assertForbidden();
        $this->actingAs($admin);
        $this->postJson($url,array_merge($input,['reason'=>'too short']))
            ->assertUnprocessable();
        $source->allowed=false;$source->save();
        $this->postJson($url,$input)->assertUnprocessable();
        $source->allowed=true;$source->save();
        $job->status='failed';$job->save();
        $this->postJson($url,$input)->assertUnprocessable();
        $job->status='dead_letter';$job->save();
        $brand->is_active=false;$brand->save();
        $this->postJson($url,$input)->assertUnprocessable();
        $brand->is_active=true;$brand->save();
        $job->status='dead_letter';$job->job_type='publish.post';$job->save();
        $this->postJson($url,$input)->assertUnprocessable();
        $this->assertDatabaseCount('workflow_jobs',1);
        $this->assertDatabaseCount('audit_logs',0);
    }
}
