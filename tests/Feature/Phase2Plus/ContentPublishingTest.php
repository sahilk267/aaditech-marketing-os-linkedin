<?php

namespace Tests\Feature\Phase2Plus;

use App\Domain\Content\ContentPolicy;
use App\Domain\Publishing\PublishService;
use App\Models\Brand;
use App\Models\ContentItem;
use App\Models\PublishingJob;
use App\Models\SocialAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ContentPublishingTest extends TestCase
{
    use RefreshDatabase;

    private function content(Brand $brand): ContentItem
    {
        return ContentItem::create(['brand_id'=>$brand->id,'title'=>'Title','topic'=>'Topic','content_type'=>'text',
            'language'=>'en','body'=>'This is a cautious tutorial without fabricated claims.','hashtags'=>[],
            'source_refs'=>[],'claim_evidence'=>[],'platform_variants'=>['linkedin'=>['body'=>'One helpful IT tip.']],
            'status'=>'draft','content_hash'=>hash('sha256','test-body')]);
    }

    public function test_high_risk_brand_requires_admin_review_and_catalog_is_not_fabricated(): void
    {
        $brand=Brand::create(['name'=>'Overseas Job Placement','slug'=>'overseas-job-placement','brand_config'=>[]]);
        $item=$this->content($brand);
        $this->assertContains('HUMAN_REVIEW_REQUIRED',app(ContentPolicy::class)->inspect($brand,$item->toArray()));
        $aadiPro=Brand::create(['name'=>'AadiPro','slug'=>'aadipro','brand_config'=>[]]);
        $this->assertContains('BRAND_CATALOG_MISSING',app(ContentPolicy::class)->inspect($aadiPro,$this->content($aadiPro)->toArray()));
    }

    public function test_unsupported_channel_becomes_manual_ready_without_network_request(): void
    {
        $user=\App\Models\User::factory()->create(['role'=>'admin','is_active'=>true]);
        $brand=Brand::create(['name'=>'Tech','slug'=>'tech','brand_config'=>[]]);
        $item=$this->content($brand);
        $item->status='approved';$item->approved_by=$user->id;$item->approved_at=now();$item->save();
        $publication=PublishingJob::create([
            'brand_id'=>$brand->id,'content_item_id'=>$item->id,
            'social_account_id'=>null,'platform'=>'pinterest','scheduled_at'=>now()->subMinute(),
            'status'=>'scheduled','idempotency_key'=>'manual-test',
        ]);
        Http::fake();
        $result=app(PublishService::class)->publish($publication->id);
        $this->assertSame('manual_ready',$result['status']);
        $this->assertDatabaseHas('publishing_jobs',['id'=>$publication->id,'status'=>'manual_ready']);
        Http::assertNothingSent();
    }

    public function test_linkedin_confirmation_failure_holds_for_reconciliation(): void
    {
        $user=\App\Models\User::factory()->create(['role'=>'admin','is_active'=>true]);
        $brand=Brand::create(['name'=>'Tech','slug'=>'tech','brand_config'=>[]]);
        $item=$this->content($brand);
        $item->status='approved';$item->approved_by=$user->id;$item->approved_at=now();$item->save();
        $account=SocialAccount::create([
            'brand_id'=>$brand->id,'platform'=>'linkedin','account_name'=>'Test Org',
            'external_account_id'=>'urn:li:organization:123456','access_token_encrypted'=>encrypt('fake-token-1234567'),
            'scopes'=>['w_organization_social'],'connection_status'=>'connected','api_access_approved'=>true,
            'token_expires_at'=>now()->addDay(),
        ]);
        $publication=PublishingJob::create([
            'brand_id'=>$brand->id,'content_item_id'=>$item->id,
            'social_account_id'=>$account->id,'platform'=>'linkedin','scheduled_at'=>now()->subMinute(),
            'status'=>'scheduled','idempotency_key'=>'linkedin-test',
            'request_metadata'=>['content_fingerprint'=>app(\App\Domain\Publishing\PublicationIntegrity::class)->fingerprint($item,'linkedin')],
        ]);
        config()->set('aadi.linkedin.version','202603');
        Http::fake(['*'=>Http::response([],201)]); // No x-restli-id confirmation
        $result=app(PublishService::class)->publish($publication->id);
        $this->assertSame('needs_reconciliation',$result['status']);
        $this->assertDatabaseHas('publishing_jobs',['id'=>$publication->id,'status'=>'needs_reconciliation']);
    }
}
