<?php

namespace Tests\Feature\Phase2Plus;

use App\Domain\Publishing\PublishService;
use App\Models\Brand;
use App\Models\ContentItem;
use App\Models\PublishingJob;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FacebookPagePublishingTest extends TestCase
{
    use RefreshDatabase;

    public function test_official_page_text_publish_requires_scopes_and_records_post_id(): void
    {
        $brand=Brand::query()->create(['name'=>'Tech','slug'=>'tech','brand_config'=>[]]);
        $admin=User::factory()->create(['role'=>'admin','is_active'=>true]);
        $item=ContentItem::query()->create([
            'brand_id'=>$brand->id, 'title'=>'Checklist', 'topic'=>'IT', 'content_type'=>'text',
            'language'=>'en', 'body'=>'Check network backups before a planned change.',
            'hashtags'=>[], 'source_refs'=>[], 'claim_evidence'=>[],
            'platform_variants'=>['facebook'=>['body'=>'A helpful network readiness tip.']],
            'status'=>'approved','approved_by'=>$admin->id,'approved_at'=>now(),
            'content_hash'=>hash('sha256','facebook-page-text-test')
        ]);
        $account=SocialAccount::query()->create([
            'brand_id'=>$brand->id, 'platform'=>'facebook', 'account_name'=>'Test Page',
            'external_account_id'=>'1234567890', 'access_token_encrypted'=>encrypt('test-page-token-1234'),
            'scopes'=>['pages_manage_posts','pages_show_list','pages_read_engagement'],
            'connection_status'=>'connected','api_access_approved'=>true,
        ]);
        $job=PublishingJob::query()->create([
            'brand_id'=>$brand->id,'content_item_id'=>$item->id,
            'social_account_id'=>$account->id,'platform'=>'facebook',
            'scheduled_at'=>now()->subMinute(),'status'=>'scheduled','idempotency_key'=>'fb-page-text-test',
        ]);
        config()->set('aadi.meta.graph_version','v26.0');
        Http::fake(['graph.facebook.com/*'=>Http::response(['id'=>'1234567890_55555'],200)]);
        $result=app(PublishService::class)->publish($job->id);
        $this->assertSame('succeeded',$result['status']);
        $this->assertDatabaseHas('publishing_jobs',['id'=>$job->id,'platform_post_id'=>'1234567890_55555']);
        Http::assertSent(fn ($r)=>str_contains($r->url(),'/v26.0/1234567890/feed') &&
            $r->method()==='POST' && ($r['message']??null)==='A helpful network readiness tip.');
    }

    public function test_unverified_page_scopes_cannot_enable_automatic_posting(): void
    {
        $brand=Brand::query()->create(['name'=>'Tech','slug'=>'tech','brand_config'=>[]]);
        $admin=User::factory()->create(['role'=>'admin','is_active'=>true]);
        $account=SocialAccount::query()->create([
            'brand_id'=>$brand->id,'platform'=>'facebook','account_name'=>'Test Page',
            'external_account_id'=>'2233445566','scopes'=>['pages_show_list'],
            'connection_status'=>'connected','api_access_approved'=>false,
        ]);
        $this->actingAs($admin)->postJson("/api/v1/brands/{$brand->id}/social-accounts/{$account->id}/approval",[
            'approved'=>true,'verification_note'=>'This account is not permitted to publish Page posts yet.',
        ])->assertStatus(422);
        $this->assertDatabaseHas('social_accounts',['id'=>$account->id,'api_access_approved'=>false]);
        Http::assertNothingSent();
    }
}
