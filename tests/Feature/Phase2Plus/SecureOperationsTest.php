<?php

namespace Tests\Feature\Phase2Plus;

use App\Domain\AI\ModelCatalog;
use App\Models\AiModel;
use App\Models\Brand;
use App\Models\ContentItem;
use App\Models\PublishingJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SecureOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_is_server_verified_and_rejects_forged_paid_model():void
    {
        Http::fake(['https://openrouter.ai/api/v1/models'=>Http::response(['data'=>[
            ['id'=>'paid/model','pricing'=>['prompt'=>'1','completion'=>'0']],
            ['id'=>'safe:free','pricing'=>['prompt'=>'0','completion'=>'0']],
        ]],200)]);
        $report=app(ModelCatalog::class)->syncFromProvider();
        $this->assertSame(1,$report['verified_free']);
        $this->assertSame(1,$report['rejected_or_paid']);
        $this->assertDatabaseHas('ai_models',['model_id'=>'paid/model','is_enabled'=>false]);
        $this->assertDatabaseHas('ai_models',['model_id'=>'safe:free','is_enabled'=>true]);
        Http::assertSentCount(1);
    }

    public function test_catalog_failure_never_reenables_paid_models():void
    {
        Http::fake(['https://openrouter.ai/api/v1/models'=>Http::response([],503)]);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(ModelCatalog::class)->syncFromProvider();
    }

    private function aPublication():array
    {
        $brand=Brand::create(['name'=>'Tech','slug'=>'tech','brand_config'=>[]]);
        $content=ContentItem::create(['brand_id'=>$brand->id,'title'=>'Tip','topic'=>'Tip',
            'content_type'=>'text','language'=>'en','body'=>'One tutorial','hashtags'=>[],
            'source_refs'=>[],'claim_evidence'=>[],'platform_variants'=>[],'status'=>'approved',
            'content_hash'=>hash('sha256','reconciliation-content')]);
        $publication=PublishingJob::create(['brand_id'=>$brand->id,'content_item_id'=>$content->id,
            'platform'=>'linkedin','scheduled_at'=>now()->subMinute(),
            'status'=>'needs_reconciliation','idempotency_key'=>'safe-reconciliation']);
        return [$brand,$publication];
    }

    public function test_ambiguous_publication_requires_admin_evidence_and_never_auto_retries():void
    {
        [$brand,$pub]=$this->aPublication();
        $editor=User::factory()->create(['role'=>'editor']);$editor->brands()->attach($brand);
        $base='/api/v1/brands/'.$brand->id.'/publishing/'.$pub->id.'/reconcile';
        $this->actingAs($editor)->postJson($base,[
            'resolution'=>'confirmed_published',
            'verification_note'=>'Verified in provider account manually at 12:00',
            'platform_post_id'=>'urn:li:share:1234',
        ])->assertForbidden();
        $admin=User::factory()->create(['role'=>'admin']);
        $this->actingAs($admin)->postJson($base,[
            'resolution'=>'confirmed_published',
            'verification_note'=>'Verified in provider account manually at 12:00',
        ])->assertUnprocessable();
        $this->postJson($base,[
            'resolution'=>'confirmed_not_published',
            'verification_note'=>'Checked official provider account activity and found no published post.',
        ])->assertOk()->assertJsonPath('status','manual_ready');
        $this->assertDatabaseHas('audit_logs',['action'=>'publication.reconciled',
            'resource_type'=>'publishing_job','resource_id'=>(string)$pub->id]);
        $this->assertDatabaseMissing('workflow_jobs',['job_type'=>'publish.post']);
        $this->postJson($base,[
            'resolution'=>'confirmed_not_published',
            'verification_note'=>'Checked provider again and found no published post.',
        ])->assertStatus(409);
    }

    public function test_admin_can_record_confirmed_provider_post_id():void
    {
        [$brand,$pub]=$this->aPublication();
        $admin=User::factory()->create(['role'=>'admin']);
        $this->actingAs($admin)->postJson('/api/v1/brands/'.$brand->id.'/publishing/'.$pub->id.'/reconcile',[
            'resolution'=>'confirmed_published',
            'verification_note'=>'Confirmed in official organization activity feed with matching content.',
            'platform_post_id'=>'urn:li:share:123456789',
        ])->assertOk()->assertJsonPath('status','succeeded');
        $this->assertDatabaseHas('publishing_jobs',['id'=>$pub->id,'platform_post_id'=>'urn:li:share:123456789']);
    }
}
