<?php

namespace Tests\Feature\Phase2Plus;

use App\Models\Brand;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaAssetDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_png_download_requires_integrity_and_brand_access(): void
    {
        Storage::fake('local');
        $brand = Brand::query()->create(['name' => 'Tech', 'slug' => 'tech', 'brand_config' => []]);
        // A valid one-pixel PNG. No external image generation service required.
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/eH0AAAAASUVORK5CYII=', true);
        $this->assertIsString($png);
        $checksum = hash('sha256', $png);
        $path = 'brand-assets/' . $brand->id . '/' . $checksum . '.png';
        Storage::disk('local')->put($path, $png);
        $asset = MediaAsset::query()->create([
            'brand_id' => $brand->id,
            'asset_type' => 'png',
            'storage_path' => $path,
            'mime_type' => 'image/png',
            'file_size' => strlen($png),
            'width' => 1,
            'height' => 1,
            'checksum' => $checksum,
            'metadata' => [],
        ]);
        $url = "/brands/{$brand->id}/assets/{$asset->id}/download";
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)->get($url)->assertOk()->assertDownload();
        Storage::disk('local')->put($path, 'corrupted-file');
        $this->get($url)->assertStatus(409);
    }

    public function test_unassigned_user_cannot_download_other_brand_assets(): void
    {
        $brand = Brand::query()->create(['name' => 'Tech', 'slug' => 'tech', 'brand_config' => []]);
        $user = User::factory()->create(['role' => 'analyst', 'is_active' => true]);
        $this->actingAs($user)->get("/brands/{$brand->id}/assets/999/download")->assertForbidden();
    }
}
