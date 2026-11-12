<?php

namespace Tests\Feature\Phase2Plus;

use App\Models\Brand;
use App\Models\ContentItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualPublishingTest extends TestCase
{
    use RefreshDatabase;

    private function item(Brand $brand, bool $approved): ContentItem
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        return ContentItem::query()->create([
            'brand_id' => $brand->id, 'title' => 'Checklist', 'topic' => 'IT',
            'content_type' => 'educational_post', 'language' => 'en',
            'body' => 'A careful checklist that avoids unverified factual marketing claims.',
            'hashtags' => [], 'source_refs' => [], 'claim_evidence' => [],
            'platform_variants' => ['linkedin' => ['body' => 'A careful checklist.']],
            'status' => $approved ? 'approved' : 'needs_review',
            'approved_by' => $approved ? $user->id : null,
            'approved_at' => $approved ? now() : null,
            'content_hash' => hash('sha256', uniqid('test-', true)),
            'risk_level' => 'high',
        ]);
    }

    public function test_approved_content_gets_idempotent_manual_package_without_social_credentials(): void
    {
        $brand = Brand::query()->create(['name' => 'Tech', 'slug' => 'tech', 'brand_config' => []]);
        $item = $this->item($brand, true);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $url = "/api/v1/brands/{$brand->id}/content/{$item->id}/manual-ready";
        $first = $this->actingAs($admin)->postJson($url, ['platform' => 'linkedin']);
        $first->assertCreated()->assertJsonPath('status', 'manual_ready');
        $this->postJson($url, ['platform' => 'linkedin'])->assertOk();
        $this->assertDatabaseCount('publishing_jobs', 1);
        $this->assertDatabaseCount('workflow_events', 1);
        $this->getJson($first->json('manual_package_url'))->assertOk()->assertJsonPath('platform', 'linkedin');
    }

    public function test_unapproved_content_cannot_prepare_package(): void
    {
        $brand = Brand::query()->create(['name' => 'Tech', 'slug' => 'tech', 'brand_config' => []]);
        $item = $this->item($brand, false);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)->postJson("/api/v1/brands/{$brand->id}/content/{$item->id}/manual-ready",
            ['platform' => 'pinterest'])->assertUnprocessable();
        $this->assertDatabaseCount('publishing_jobs', 0);
    }

    public function test_analyst_and_out_of_brand_editor_cannot_prepare_package(): void
    {
        $brand = Brand::query()->create(['name' => 'Tech', 'slug' => 'tech', 'brand_config' => []]);
        $item = $this->item($brand, true);
        $url = "/api/v1/brands/{$brand->id}/content/{$item->id}/manual-ready";
        $analyst = User::factory()->create(['role' => 'analyst', 'is_active' => true]);
        $analyst->brands()->attach($brand->id);
        $this->actingAs($analyst)->postJson($url, ['platform' => 'linkedin'])->assertForbidden();
        $editor = User::factory()->create(['role' => 'editor', 'is_active' => true]);
        $this->actingAs($editor)->postJson($url, ['platform' => 'linkedin'])->assertForbidden();
    }
}
