<?php

namespace Tests\Feature\Phase2Plus;

use App\Models\Brand;
use App\Models\ContentItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OfflineCreativeTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_brand_post_creates_safe_svg_without_worker_and_reuses_asset(): void
    {
        Storage::fake('local');
        $brand=Brand::query()->create(['name'=>'Aadi & Tech','slug'=>'tech',
            'brand_config'=>['colors'=>['primary'=>'#123456','accent'=>'invalid']]]);
        $admin=User::factory()->create(['role'=>'admin','is_active'=>true]);
        $post=ContentItem::query()->create([
            'brand_id'=>$brand->id,'title'=>'Checklist <script>alert(1)</script>',
            'topic'=>'IT','content_type'=>'text','language'=>'en',
            'body'=>'Review your documentation and confirm current network settings before a planned infrastructure change.',
            'hashtags'=>[],'source_refs'=>[],'claim_evidence'=>[],
            'platform_variants'=>['linkedin'=>['body'=>'Review network settings.']],
            'status'=>'approved','approved_by'=>$admin->id,'approved_at'=>now(),
            'content_hash'=>hash('sha256','offline-svg-test'),
        ]);
        $url="/api/v1/brands/{$brand->id}/content/{$post->id}/render-offline";
        $first=$this->actingAs($admin)->postJson($url,[]);
        $first->assertCreated()->assertJsonPath('worker_required',false);
        $this->postJson($url,[])->assertOk();
        $this->assertDatabaseCount('media_assets',1);
        $asset=\App\Models\MediaAsset::query()->firstOrFail();
        $svg=Storage::disk('local')->get($asset->storage_path);
        $this->assertStringContainsString('Aadi &amp; Tech',$svg);
        $this->assertStringNotContainsString('<script>',$svg);
        $this->assertStringContainsString('#123456',$svg);
        $this->assertStringContainsString('#20C997',$svg); // safe fallback color
        $this->get($first->json('download_url'))->assertOk()->assertDownload();
    }

    public function test_unapproved_post_cannot_render_and_outsider_cannot_render(): void
    {
        Storage::fake('local');
        $brand=Brand::query()->create(['name'=>'Tech','slug'=>'tech','brand_config'=>[]]);
        $admin=User::factory()->create(['role'=>'admin','is_active'=>true]);
        $item=ContentItem::query()->create([
            'brand_id'=>$brand->id,'title'=>'Checklist','topic'=>'IT',
            'content_type'=>'text','language'=>'en',
            'body'=>'Review the checklist before implementing a planned network change.',
            'hashtags'=>[],'source_refs'=>[],'claim_evidence'=>[],
            'platform_variants'=>[],'status'=>'needs_review',
            'content_hash'=>hash('sha256','offline-svg-unapproved'),
        ]);
        $url="/api/v1/brands/{$brand->id}/content/{$item->id}/render-offline";
        $this->actingAs($admin)->postJson($url,[])->assertUnprocessable();
        $analyst=User::factory()->create(['role'=>'analyst','is_active'=>true]);
        $this->actingAs($analyst)->postJson($url,[])->assertForbidden();
        $this->assertDatabaseCount('media_assets',0);
    }
}
