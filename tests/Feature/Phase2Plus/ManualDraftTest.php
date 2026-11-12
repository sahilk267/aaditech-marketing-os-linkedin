<?php

namespace Tests\Feature\Phase2Plus;

use App\Models\Brand;
use App\Models\ContentItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualDraftTest extends TestCase
{
    use RefreshDatabase;

    private function input(): array
    {
        return [
            'title' => 'Original networking checklist',
            'topic' => 'IT infrastructure',
            'body' => 'Check your network documentation before planning an infrastructure change. Always verify the exact requirements.',
            'platform' => 'linkedin',
            'content_type' => 'educational_post',
            'language' => 'en',
            'hashtags' => ['Networking', 'ITTips'],
        ];
    }

    public function test_editor_can_make_offline_draft_but_not_auto_approve(): void
    {
        $brand = Brand::query()->create(['name' => 'Aadi Tech', 'slug' => 'aadi-tech', 'brand_config' => []]);
        $editor = User::factory()->create(['role' => 'editor', 'is_active' => true]);
        $editor->brands()->attach($brand->id);
        $this->actingAs($editor)->postJson("/api/v1/brands/{$brand->id}/content/manual", $this->input())
            ->assertCreated()->assertJsonPath('status', 'needs_review')
            ->assertJsonPath('requires_admin_review', true);
        $item = ContentItem::query()->firstOrFail();
        $this->assertSame('manual', $item->ai_provider);
        $this->assertSame('high', $item->risk_level);
        $this->assertNull($item->approved_at);
        $this->assertDatabaseHas('content_reviews', ['content_item_id' => $item->id, 'result' => 'pending']);
    }

    public function test_cross_brand_editor_and_analyst_cannot_create_manual_drafts(): void
    {
        $brand = Brand::query()->create(['name' => 'Restricted', 'slug' => 'restricted', 'brand_config' => []]);
        $editor = User::factory()->create(['role' => 'editor', 'is_active' => true]);
        $this->actingAs($editor)->postJson("/api/v1/brands/{$brand->id}/content/manual", $this->input())
            ->assertForbidden();
        $analyst = User::factory()->create(['role' => 'analyst', 'is_active' => true]);
        $analyst->brands()->attach($brand->id);
        $this->actingAs($analyst)->postJson("/api/v1/brands/{$brand->id}/content/manual", $this->input())
            ->assertForbidden();
        $this->assertDatabaseCount('content_items', 0);
    }

    public function test_duplicate_drafts_and_invalid_short_body_are_rejected(): void
    {
        $brand = Brand::query()->create(['name' => 'Aadi Tech', 'slug' => 'aadi-tech', 'brand_config' => []]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $endpoint = "/api/v1/brands/{$brand->id}/content/manual";
        $this->actingAs($admin)->postJson($endpoint, $this->input())->assertCreated();
        $this->postJson($endpoint, $this->input())->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->postJson($endpoint, array_merge($this->input(), ['body' => 'Too short']))
            ->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->assertDatabaseCount('content_items', 1);
    }
}
