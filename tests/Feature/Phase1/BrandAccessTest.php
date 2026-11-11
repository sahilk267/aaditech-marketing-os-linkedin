<?php

namespace Tests\Feature\Phase1;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandAccessTest extends TestCase
{
    use RefreshDatabase;

    private function brand(string $slug): Brand
    {
        return Brand::create(['slug'=>$slug,'name'=>$slug,
            'brand_config'=>['objective'=>'Sample', 'cta'=>'Contact', 'topics'=>[], 'audience'=>[],
                'prohibited_claims'=>['Do not make false claims'],'requires_official_evidence'=>true]]);
    }

    private function data(Brand $brand): array
    {
        return ['name'=>$brand->name,'slug'=>$brand->slug,'objective'=>'Revised objective','cta'=>'Contact',
            'topics_text'=>'One topic','audience_text'=>'One audience','prohibited_claims_text'=>'',
            'website_url'=>'','description'=>'','is_active'=>'1'];
    }

    public function test_brand_isolation_applies_to_list_read_and_mutation(): void
    {
        $mine=$this->brand('mine'); $other=$this->brand('other');
        $editor=User::factory()->create(['role'=>'editor','is_active'=>true]);
        $editor->brands()->attach($mine);
        $this->actingAs($editor)->get('/brands')->assertOk()->assertSee('mine')->assertDontSee('other');
        $this->get('/brands/'.$mine->id)->assertOk();
        $this->get('/brands/'.$other->id)->assertForbidden();
        $this->put('/brands/'.$other->id, $this->data($other))->assertForbidden();
        $this->put('/brands/'.$mine->id, $this->data($mine))->assertForbidden();
    }

    public function test_admin_can_edit_without_dropping_high_risk_policy_or_audit(): void
    {
        $brand=$this->brand('overseas');
        $admin=User::factory()->create(['role'=>'admin','is_active'=>true]);
        $this->actingAs($admin)->put('/brands/'.$brand->id, $this->data($brand))
            ->assertRedirect('/brands/'.$brand->id);
        $brand->refresh();
        $this->assertTrue($brand->brand_config['requires_official_evidence']);
        $this->assertContains('Do not make false claims', $brand->brand_config['prohibited_claims']);
        $this->assertDatabaseHas('audit_logs',['action'=>'brand.updated','resource_id'=>(string)$brand->id]);
    }

    public function test_inactive_user_cannot_read_brand_even_with_membership(): void
    {
        $brand=$this->brand('restricted');
        $user=User::factory()->create(['role'=>'analyst','is_active'=>false]);
        $user->brands()->attach($brand);
        $this->actingAs($user)->get('/brands/'.$brand->id)->assertForbidden();
    }
}
