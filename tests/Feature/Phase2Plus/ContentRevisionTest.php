<?php

namespace Tests\Feature\Phase2Plus;

use App\Domain\Publishing\PublicationIntegrity;
use App\Domain\Publishing\PublishService;
use App\Models\Brand;
use App\Models\ContentItem;
use App\Models\MediaAsset;
use App\Models\PublishingJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContentRevisionTest extends TestCase
{
    use RefreshDatabase;

    private function approvedPost(Brand $brand): ContentItem
    {
        $approver = User::factory()->create(['role'=>'admin','is_active'=>true]);
        return ContentItem::query()->create([
            'brand_id'=>$brand->id, 'title'=>'Original tip', 'topic'=>'Infrastructure',
            'content_type'=>'educational_post', 'language'=>'en',
            'body'=>'Review the complete technical checklist before changing production infrastructure.',
            'hashtags'=>['Networking'], 'source_refs'=>[], 'claim_evidence'=>[],
            'platform_variants'=>['linkedin'=>['body'=>'Original tip for professionals']],
            'status'=>'approved','approved_by'=>$approver->id,'approved_at'=>now(),
            'risk_level'=>'high','content_hash'=>hash('sha256','original-tip'),
        ]);
    }

    public function test_edit_revokes_approval_packages_and_creatives_with_audit(): void
    {
        Storage::fake('local');
        $brand=Brand::query()->create(['name'=>'Tech','slug'=>'tech','brand_config'=>[]]);
        $content=$this->approvedPost($brand);
        $admin=User::factory()->create(['role'=>'admin','is_active'=>true]);
        $base='/api/v1/brands/'.$brand->id.'/content/'.$content->id;
        $this->actingAs($admin)->postJson($base.'/render-offline')->assertCreated();
        $this->postJson($base.'/manual-ready',['platform'=>'linkedin'])->assertCreated();
        $this->assertDatabaseCount('media_assets',1);
        $this->assertDatabaseHas('publishing_jobs',['status'=>'manual_ready']);

        $this->patchJson($base,['body'=>'Updated infrastructure checklist. Validate the approved change window and record an owner review.'])
            ->assertOk()->assertJsonPath('status','needs_review')
            ->assertJsonPath('requires_admin_review',true)
            ->assertJsonPath('revoked_publications',1)
            ->assertJsonPath('detached_assets',1);

        $content->refresh();
        $this->assertNull($content->approved_at);
        $this->assertNull($content->approved_by);
        $this->assertSame('high',$content->risk_level);
        $this->assertSame([], $content->platform_variants);
        $this->assertDatabaseHas('publishing_jobs',['status'=>'blocked_review','last_error'=>'CONTENT_REVISION_REQUIRES_APPROVAL']);
        $this->assertDatabaseHas('audit_logs',['action'=>'content.revised','resource_id'=>(string)$content->id]);
        $this->assertDatabaseHas('content_reviews',['content_item_id'=>$content->id,'review_type'=>'human_revision','result'=>'pending']);
        $this->assertDatabaseHas('media_assets',['brand_id'=>$brand->id,'content_item_id'=>null]);
        $this->postJson($base.'/manual-ready',['platform'=>'linkedin'])->assertUnprocessable();
        $this->postJson($base.'/approve',[])->assertOk()->assertJsonPath('status','approved');
        $this->assertDatabaseCount('publishing_jobs',1); // Old export is never silently reactivated.
        $new=$this->postJson($base.'/manual-ready',['platform'=>'linkedin'])->assertCreated();
        $this->assertNotEquals(1,$new->json('publication_id'));
        $this->assertDatabaseCount('publishing_jobs',2);
    }

    public function test_schedule_revocation_blocks_unreviewed_external_publish(): void
    {
        $brand=Brand::query()->create(['name'=>'Tech','slug'=>'tech','brand_config'=>[]]);
        $content=$this->approvedPost($brand);
        $admin=User::factory()->create(['role'=>'admin','is_active'=>true]);
        $post=PublishingJob::query()->create([
            'brand_id'=>$brand->id,'content_item_id'=>$content->id,
            'platform'=>'linkedin','scheduled_at'=>now()->subMinute(),'status'=>'scheduled',
            'idempotency_key'=>'revoked-scheduled-content',
            'request_metadata'=>['content_fingerprint'=>app(PublicationIntegrity::class)->fingerprint($content,'linkedin')],
        ]);
        $this->actingAs($admin)->patchJson('/api/v1/brands/'.$brand->id.'/content/'.$content->id,
            ['title'=>'Revised infrastructure guidance'])->assertOk();
        $post->refresh();
        $this->assertSame('blocked_review',$post->status);
        Http::fake();
        $this->postJson('/api/v1/brands/'.$brand->id.'/content/'.$content->id.'/manual-ready',
            ['platform'=>'linkedin'])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_sent_or_ambiguous_publication_content_cannot_be_rewritten(): void
    {
        $brand=Brand::query()->create(['name'=>'Tech','slug'=>'tech','brand_config'=>[]]);
        $content=$this->approvedPost($brand);
        $admin=User::factory()->create(['role'=>'admin','is_active'=>true]);
        foreach (['sending','succeeded','needs_reconciliation'] as $status) {
            $job=PublishingJob::query()->create([
                'brand_id'=>$brand->id,'content_item_id'=>$content->id,
                'platform'=>'linkedin','scheduled_at'=>now(),'status'=>$status,
                'idempotency_key'=>'status-'.$status,
            ]);
            $this->actingAs($admin)->patchJson('/api/v1/brands/'.$brand->id.'/content/'.$content->id,
                ['body'=>'Updated version of a long infrastructure advisory and original recommendations.'])
                ->assertStatus(409);
            $this->assertSame('approved',$content->fresh()->status);
            $job->delete();
        }
    }

    public function test_reapproved_identical_copy_can_relink_existing_svg(): void
    {
        Storage::fake('local');
        $brand=Brand::query()->create(['name'=>'Tech','slug'=>'tech','brand_config'=>[]]);
        $content=$this->approvedPost($brand);
        $originalBody=$content->body;
        $admin=User::factory()->create(['role'=>'admin','is_active'=>true]);
        $base='/api/v1/brands/'.$brand->id.'/content/'.$content->id;
        $this->actingAs($admin)->postJson($base.'/render-offline')->assertCreated();
        $this->patchJson($base,['body'=>'A different infrastructure checklist that needs a fresh explicit approval.'])->assertOk();
        $this->postJson($base.'/approve',[])->assertOk();
        $this->patchJson($base,['body'=>$originalBody])->assertOk();
        $this->postJson($base.'/approve',[])->assertOk();
        $result=$this->postJson($base.'/render-offline')->assertOk()->assertJsonPath('status','ready');
        $this->assertDatabaseCount('media_assets',1);
        $this->assertDatabaseHas('media_assets',[
            'id'=>$result->json('media_asset_id'),'brand_id'=>$brand->id,
            'content_item_id'=>$content->id,
        ]);
    }

    public function test_cross_brand_and_read_only_users_cannot_edit(): void
    {
        $brand=Brand::query()->create(['name'=>'Tech','slug'=>'tech','brand_config'=>[]]);
        $content=$this->approvedPost($brand);
        $url='/api/v1/brands/'.$brand->id.'/content/'.$content->id;
        $editor=User::factory()->create(['role'=>'editor','is_active'=>true]);
        $this->actingAs($editor)->patchJson($url,['title'=>'Changed'])->assertForbidden();
        $analyst=User::factory()->create(['role'=>'analyst','is_active'=>true]);
        $analyst->brands()->attach($brand->id);
        $this->actingAs($analyst)->patchJson($url,['title'=>'Changed'])->assertForbidden();
        $this->assertSame('approved',$content->fresh()->status);
    }
}
