<?php

namespace Tests\Feature\Phase2Plus;

use App\Models\Brand;
use App\Models\BrandSource;
use App\Models\ResearchItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResearchReviewAuditTest extends TestCase
{
    use RefreshDatabase;

    private const EXCERPT = 'official eligibility requirements for this role';

    private function fixture(string $slug='tech', string $tier='A'): array
    {
        $brand=Brand::query()->create(['name'=>'Sample','slug'=>$slug,'brand_config'=>[]]);
        $source=BrandSource::query()->create([
            'brand_id'=>$brand->id,'source_url'=>'https://employer.example.org/roles',
            'canonical_hash'=>hash('sha256','source-'.$slug),
            'source_type'=>'official_blog','trust_tier'=>$tier,'allowed'=>true,
        ]);
        $item=ResearchItem::query()->create([
            'brand_id'=>$brand->id,'source_id'=>$source->id,
            'title'=>'Official employer eligibility',
            'summary'=>'The employer publishes official eligibility requirements for this role on its owned website.',
            'extracted_facts'=>['eligibility'=>'Applicants must review official eligibility requirements for this role.'],
            'fetched_at'=>now(),'published_at'=>now()->subDay(),
            'content_hash'=>hash('sha256','research-'.$slug),
            'freshness_status'=>'current','verification_status'=>'unverified',
        ]);
        $admin=User::factory()->create(['role'=>'admin','is_active'=>true]);
        $url="/api/v1/brands/{$brand->id}/research/{$item->id}/verify";
        $payload=[
            'decision'=>'verified',
            'reviewed_excerpt'=>self::EXCERPT,
            'verification_note'=>'Reviewed the current employer website and confirmed this research excerpt is genuine.',
            'acknowledged'=>true,
        ];
        return [$brand,$source,$item,$admin,$url,$payload];
    }

    public function test_admin_review_verifies_with_audited_rationale_then_can_revoke(): void
    {
        [$brand,$source,$item,$admin,$url,$payload]=$this->fixture();
        $this->actingAs($admin)->postJson($url,$payload)
            ->assertOk()->assertJsonPath('verification_status','verified')
            ->assertJsonPath('requires_separate_claim_attestation',true);
        $this->assertDatabaseHas('audit_logs',[
            'action'=>'research.verification_decision',
            'resource_type'=>'research_item',
            'resource_id'=>(string)$item->id,'user_id'=>$admin->id,
        ]);
        $this->postJson($url,array_merge($payload,['decision'=>'rejected']))
            ->assertOk()->assertJsonPath('verification_status','rejected');
        $this->assertSame('rejected',$item->fresh()->verification_status);
        $this->assertDatabaseCount('audit_logs',2);
    }

    public function test_editor_cannot_verify_and_admin_must_supply_actual_excerpt(): void
    {
        [$brand,$source,$item,$admin,$url,$payload]=$this->fixture();
        $editor=User::factory()->create(['role'=>'editor','is_active'=>true]);
        $editor->brands()->attach($brand->id);
        $this->actingAs($editor)->postJson($url,$payload)->assertForbidden();
        $this->actingAs($admin)->postJson($url,array_merge($payload,[
            'reviewed_excerpt'=>'An invented employer statement nowhere in the stored source',
        ]))->assertUnprocessable()->assertJsonValidationErrors('reviewed_excerpt');
        $this->postJson($url,array_merge($payload,['verification_note'=>'too short']))
            ->assertUnprocessable()->assertJsonValidationErrors('verification_note');
        $this->assertDatabaseCount('audit_logs',0);
        $this->assertSame('unverified',$item->fresh()->verification_status);
    }

    public function test_stale_disabled_or_tier_d_research_cannot_be_verified(): void
    {
        [$brand,$source,$item,$admin,$url,$payload]=$this->fixture();
        $this->actingAs($admin);
        $source->allowed=false;$source->save();
        $this->postJson($url,$payload)->assertUnprocessable();
        $source->allowed=true;$source->trust_tier='D';$source->save();
        $this->postJson($url,$payload)->assertUnprocessable();
        $source->trust_tier='A';$source->save();
        $item->freshness_status='stale';$item->save();
        $this->postJson($url,$payload)->assertUnprocessable();
        $this->assertSame('unverified',$item->fresh()->verification_status);
    }

    public function test_overseas_brand_requires_tier_a_and_cross_brand_isolation(): void
    {
        [$brand,$source,$item,$admin,$url,$payload]=$this->fixture('overseas-job-placement','B');
        $this->actingAs($admin)->postJson($url,$payload)->assertUnprocessable();
        $source->trust_tier='A';$source->save();
        $this->postJson($url,$payload)->assertOk();
        $other=Brand::query()->create(['name'=>'Other','slug'=>'other','brand_config'=>[]]);
        $editor=User::factory()->create(['role'=>'editor','is_active'=>true]);
        $editor->brands()->attach($other->id);
        $this->actingAs($editor)->postJson($url,$payload)->assertForbidden();
    }
}
