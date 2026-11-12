<?php

namespace Tests\Feature\Phase2Plus;

use App\Domain\Publishing\LinkedInImageUploader;
use App\Domain\Publishing\PublishService;
use App\Models\Brand;
use App\Models\ContentItem;
use App\Models\MediaAsset;
use App\Models\PublishingJob;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LinkedInImagePublishingTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_image_upload_to_linkedin_and_post_confirmation():void
    {
        [$post,$png]=$this->postWithAsset();
        config()->set('aadi.linkedin.version','202607');
        Http::fake(function ($request) {
            $url=$request->url();
            if (str_contains($url,'/images?action=initializeUpload')) return Http::response([
                'value'=>['image'=>'urn:li:image:IMAGEABC123',
                    'uploadUrl'=>'https://www.linkedin.com/dms-uploads/signed-asset/0?signature=abc'],
            ],200);
            if (str_contains($url,'/dms-uploads/')) return Http::response([],201);
            if (str_contains($url,'/rest/images/') && !str_contains($url,'?action=')) return Http::response(['status'=>'AVAILABLE'],200);
            if (str_contains($url,'/posts')) return Http::response([],201,['x-restli-id'=>'urn:li:share:123456']);
            return Http::response(['unknown'=>'url'],404);
        });
        $result=app(PublishService::class)->publish($post->id);
        $this->assertSame('succeeded',$result['status']);
        $this->assertSame('urn:li:share:123456',$result['platform_post_id']);
        Http::assertSentCount(4);
        Http::assertSent(fn ($r) => str_contains($r->url(),'/posts')
            && ($r['content']['media']['id']??null)==='urn:li:image:IMAGEABC123');
        $this->assertDatabaseHas('workflow_events',['event_type'=>'publication.succeeded','aggregate_id'=>$post->id]);
    }

    public function test_untrusted_upload_url_is_never_requested():void
    {
        [$post]=$this->postWithAsset();
        config()->set('aadi.linkedin.version','202607');
        Http::fake(function ($request) {
            if (str_contains($request->url(),'/images?action=initializeUpload')) {
                return Http::response(['value'=>[
                    'image'=>'urn:li:image:IMAGEABC123','uploadUrl'=>'https://127.0.0.1/private']],200);
            }
            return Http::response([],500);
        });
        $result=app(PublishService::class)->publish($post->id);
        $this->assertSame('needs_reconciliation',$result['status']);
        Http::assertSentCount(1);
    }

    public function test_allowlist_requires_exact_secure_linkedin_upload_host():void
    {
        $this->assertTrue(LinkedInImageUploader::isApprovedUploadUrl(
            'https://www.linkedin.com/dms-uploads/asset/0?signature=signed'));
        foreach (['http://www.linkedin.com/dms-uploads/x',
            'https://www.linkedin.com.evil.example/dms-uploads/x',
            'https://user@www.linkedin.com/dms-uploads/x',
            'https://www.linkedin.com:8080/dms-uploads/x',
            'https://www.linkedin.com/redirect-to-evil',
        ] as $url) $this->assertFalse(LinkedInImageUploader::isApprovedUploadUrl($url));
    }

    private function postWithAsset():array
    {
        Storage::fake('local');
        $owner=User::factory()->create(['role'=>'admin','is_active'=>true]);
        $brand=Brand::create(['name'=>'Tech','slug'=>'tech','brand_config'=>[],'is_active'=>true]);
        $content=ContentItem::create(['brand_id'=>$brand->id,'title'=>'Helpful visual tips',
            'topic'=>'Visual tips','content_type'=>'image_post','language'=>'en',
            'body'=>'Safe informational content without unsupported claims.',
            'hashtags'=>[],'source_refs'=>[],'claim_evidence'=>[],
            'platform_variants'=>['linkedin'=>['body'=>'A visual IT tip']],
            'status'=>'approved','approved_by'=>$owner->id,'approved_at'=>now(),
            'content_hash'=>hash('sha256','imagepost')]);
        $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/B0kAAAAASUVORK5CYII=',true);
        $checksum=hash('sha256',$png);
        $path='brand-assets/'.$brand->id.'/'.$checksum.'.png';
        Storage::disk('local')->put($path,$png);
        MediaAsset::create(['brand_id'=>$brand->id,'content_item_id'=>$content->id,
            'asset_type'=>'png','storage_path'=>$path,'mime_type'=>'image/png',
            'file_size'=>strlen($png),'width'=>1,'height'=>1,
            'license_source'=>'owner_generated','checksum'=>$checksum,'metadata'=>[]]);
        $account=SocialAccount::create(['brand_id'=>$brand->id,'platform'=>'linkedin',
            'account_name'=>'Test Company','external_account_id'=>'urn:li:organization:123456',
            'access_token_encrypted'=>encrypt('disposable-test-token'),
            'scopes'=>['w_organization_social'],'connection_status'=>'connected',
            'api_access_approved'=>true,'token_expires_at'=>now()->addDay()]);
        $post=PublishingJob::create(['brand_id'=>$brand->id,'content_item_id'=>$content->id,
            'social_account_id'=>$account->id,'platform'=>'linkedin','scheduled_at'=>now()->subMinute(),
            'status'=>'scheduled','idempotency_key'=>'image-'.str()->random(12),
            'request_metadata'=>['content_fingerprint'=>app(\App\Domain\Publishing\PublicationIntegrity::class)->fingerprint($content,'linkedin')]]);
        return [$post,$png];
    }
}
