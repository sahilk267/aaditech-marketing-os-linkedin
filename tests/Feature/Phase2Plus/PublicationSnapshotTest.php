<?php

namespace Tests\Feature\Phase2Plus;

use App\Domain\Publishing\PublicationIntegrity;
use App\Domain\Publishing\PublishService;
use App\Models\Brand;
use App\Models\ContentItem;
use App\Models\PublishingJob;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicationSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        $brand=Brand::create(['name'=>'Tech','slug'=>'tech','brand_config'=>[]]);
        $admin=User::factory()->create(['role'=>'admin','is_active'=>true]);
        $content=ContentItem::create([
            'brand_id'=>$brand->id,'title'=>'Approved title','topic'=>'IT',
            'content_type'=>'text','language'=>'en',
            'body'=>'Check operational readiness before deploying any business system.',
            'hashtags'=>[],'source_refs'=>[],'claim_evidence'=>[],
            'platform_variants'=>['linkedin'=>['body'=>'A practical IT checklist.']],
            'status'=>'approved','approved_by'=>$admin->id,'approved_at'=>now(),
            'content_hash'=>hash('sha256','snapshot-test'),
        ]);
        $account=SocialAccount::create([
            'brand_id'=>$brand->id,'platform'=>'linkedin','account_name'=>'Test org',
            'external_account_id'=>'urn:li:organization:1234','access_token_encrypted'=>encrypt('test-token'),
            'scopes'=>['w_organization_social'],'connection_status'=>'connected',
            'api_access_approved'=>true,'token_expires_at'=>now()->addDay(),
        ]);
        $fingerprint=app(PublicationIntegrity::class)->fingerprint($content,'linkedin');
        $post=PublishingJob::create([
            'brand_id'=>$brand->id,'content_item_id'=>$content->id,
            'social_account_id'=>$account->id,'platform'=>'linkedin',
            'scheduled_at'=>now()->subMinute(),'status'=>'scheduled',
            'idempotency_key'=>'snapshot-test-pub',
            'request_metadata'=>['content_fingerprint'=>$fingerprint],
        ]);
        return [$content,$post];
    }

    public function test_modified_approved_post_is_blocked_without_any_provider_call(): void
    {
        [$content,$post]=$this->scenario();
        $content->body='This copy was changed after approval.';$content->save();
        Http::fake();
        $result=app(PublishService::class)->publish($post->id);
        $this->assertSame('blocked_review',$result['status']);
        $this->assertDatabaseHas('publishing_jobs',['id'=>$post->id,'status'=>'blocked_review','attempt_count'=>0]);
        Http::assertNothingSent();
    }

    public function test_missing_snapshot_cannot_auto_publish(): void
    {
        [, $post]=$this->scenario();
        $post->request_metadata=null;$post->save();
        Http::fake();
        $this->assertSame('blocked_review',app(PublishService::class)->publish($post->id)['status']);
        Http::assertNothingSent();
    }

    public function test_schedule_api_seals_approved_content_and_deduplicates_same_request(): void
    {
        [$content,$post]=$this->scenario();
        $post->delete();
        $owner=User::factory()->create(['role'=>'admin','is_active'=>true]);
        $account=SocialAccount::firstOrFail();
        $endpoint='/api/v1/brands/'.$content->brand_id.'/content/'.$content->id.'/schedule';
        $payload=['platform'=>'linkedin','social_account_id'=>$account->id,
            'scheduled_at'=>now()->addHour()->toIso8601String()];
        $first=$this->actingAs($owner)->postJson($endpoint,$payload)->assertCreated();
        $second=$this->postJson($endpoint,$payload)->assertCreated();
        $this->assertSame($first->json('publishing_job_id'),$second->json('publishing_job_id'));
        $this->assertDatabaseCount('publishing_jobs',1);
    }
}
