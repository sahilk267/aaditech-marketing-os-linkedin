<?php

namespace Tests\Feature\Phase2Plus;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OfflineJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_ai_no_worker_no_oauth_full_editorial_journey(): void
    {
        Storage::fake('local');
        Http::fake(); // Any accidental external API request is visible.
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $brand = Brand::query()->create([
            'name' => 'Aadi Tech Solutions',
            'slug' => 'aadi-tech-solutions',
            'brand_config' => ['colors' => ['primary' => '#17324D', 'accent' => '#20C997']],
        ]);
        $base = '/api/v1/brands/' . $brand->id;
        $draft = $this->actingAs($admin)->postJson($base . '/content/manual', [
            'title' => 'A helpful technical checklist',
            'topic' => 'Infrastructure planning',
            'body' => 'Review your infrastructure documentation before making changes and verify every environment separately.',
            'platform' => 'linkedin',
            'content_type' => 'educational_post',
            'language' => 'en',
            'hashtags' => ['Infrastructure', 'IT'],
        ])->assertCreated()->assertJsonPath('status', 'needs_review');

        $id = $draft->json('content_id');
        $this->postJson($base . '/content/' . $id . '/approve', [])
            ->assertOk()->assertJsonPath('status', 'approved');
        $creative = $this->postJson($base . '/content/' . $id . '/render-offline', [])
            ->assertCreated()->assertJsonPath('worker_required', false);
        $assetUrl = $creative->json('download_url');
        $this->get($assetUrl)->assertOk()->assertDownload();

        $prepared = $this->postJson($base . '/content/' . $id . '/manual-ready', ['platform' => 'linkedin'])
            ->assertCreated()->assertJsonPath('status', 'manual_ready');
        $package = $this->getJson($prepared->json('manual_package_url'))
            ->assertOk()->assertJsonPath('platform', 'linkedin')
            ->assertJsonPath('status', 'manual_ready')
            ->assertJsonCount(1, 'media');
        $this->assertSame($assetUrl, $package->json('media.0.download_url'));
        $this->assertSame(
            'Review your infrastructure documentation before making changes and verify every environment separately.',
            $package->json('caption')
        );
        Http::assertNothingSent();
    }
}
