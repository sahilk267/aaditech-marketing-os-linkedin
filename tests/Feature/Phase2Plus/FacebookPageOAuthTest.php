<?php

namespace Tests\Feature\Phase2Plus;

use App\Models\Brand;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FacebookPageOAuthTest extends TestCase
{
    use RefreshDatabase;

    private function setupMeta(): void
    {
        config()->set('aadi.meta', [
            'app_id' => 'example-app-id',
            'app_secret' => 'example-app-secret',
            'redirect_uri' => 'https://marketing.example.invalid/auth/facebook/callback',
            'graph_version' => 'v26.0',
        ]);
    }

    public function test_authorized_page_token_is_encrypted_and_not_auto_approved(): void
    {
        $this->setupMeta();
        $brand = Brand::query()->create(['name' => 'Tech', 'slug' => 'tech', 'brand_config' => []]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $start = $this->actingAs($admin)->post("/brands/{$brand->id}/facebook/connect", ['page_id' => '1234567890']);
        $start->assertRedirect();
        parse_str((string) parse_url($start->headers->get('Location'), PHP_URL_QUERY), $params);
        $this->assertNotEmpty($params['state'] ?? null);
        Http::fake([
            'https://graph.facebook.com/v26.0/oauth/access_token*' =>
                Http::response(['access_token' => 'fb-user-access-token-abcdef'], 200),
            'https://graph.facebook.com/v26.0/me/permissions*' =>
                Http::response(['data' => [
                    ['permission' => 'pages_show_list', 'status' => 'granted'],
                    ['permission' => 'pages_read_engagement', 'status' => 'granted'],
                    ['permission' => 'pages_manage_posts', 'status' => 'granted'],
                ]], 200),
            'https://graph.facebook.com/v26.0/me/accounts*' =>
                Http::response(['data' => [[
                    'id' => '1234567890', 'name' => 'Owned Page',
                    'access_token' => 'fb-page-access-token-abcdef',
                ]]], 200),
        ]);
        $callback='/auth/facebook/callback?state='.rawurlencode($params['state']).'&code=test-code';
        $this->get($callback)->assertRedirect('/brands/'.$brand->id);
        $account=SocialAccount::query()->where('platform','facebook')->firstOrFail();
        $this->assertSame('fb-page-access-token-abcdef',$account->accessToken());
        $this->assertFalse($account->api_access_approved);
        $this->assertNotSame('fb-page-access-token-abcdef',$account->access_token_encrypted);
        $this->get($callback)->assertForbidden(); // OAuth state cannot be replayed
    }

    public function test_page_connection_denied_without_admin_access(): void
    {
        $this->setupMeta();
        $brand=Brand::query()->create(['name'=>'Tech','slug'=>'tech','brand_config'=>[]]);
        $editor=User::factory()->create(['role'=>'editor','is_active'=>true]);
        $editor->brands()->attach($brand->id);
        $this->actingAs($editor)->post("/brands/{$brand->id}/facebook/connect",['page_id'=>'1234567890'])
            ->assertForbidden();
        Http::assertNothingSent();
    }
}
