<?php

namespace Tests\Feature\Phase2Plus;

use App\Models\Brand;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_reports_safe_core_capabilities_and_not_other_brand_secrets(): void
    {
        $brand=Brand::query()->create(['name'=>'Tech','slug'=>'tech','brand_config'=>[]]);
        $another=Brand::query()->create(['name'=>'Jobs','slug'=>'jobs','brand_config'=>[]]);
        $editor=User::factory()->create(['role'=>'editor','is_active'=>true]);
        $editor->brands()->attach($brand->id);
        SocialAccount::query()->create([
            'brand_id'=>$another->id,'platform'=>'linkedin','account_name'=>'Foreign Org',
            'external_account_id'=>'urn:li:organization:67890',
            'access_token_encrypted'=>encrypt('a-secret-that-must-never-leak'),
            'scopes'=>['w_organization_social'],'connection_status'=>'connected',
            'api_access_approved'=>true,'token_expires_at'=>now()->addDay(),
        ]);
        $resp=$this->actingAs($editor)->getJson('/api/v1/brands/'.$brand->id.'/overview');
        $resp->assertOk()
            ->assertJsonPath('capabilities.offline_drafts',true)
            ->assertJsonPath('capabilities.offline_svg',true)
            ->assertJsonPath('capabilities.manual_publish_package',true)
            ->assertJsonPath('capabilities.worker_connected',false)
            ->assertJsonCount(0,'capabilities.official_publish_accounts');
        $this->assertStringNotContainsString('a-secret-that-must-never-leak',$resp->getContent());
        $this->getJson('/api/v1/brands/'.$another->id.'/overview')->assertForbidden();
    }
}
