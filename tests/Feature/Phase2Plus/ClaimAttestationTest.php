<?php

namespace Tests\Feature\Phase2Plus;

use App\Domain\Content\ContentPolicy;
use App\Domain\Publishing\PublicationIntegrity;
use App\Domain\Publishing\PublishService;
use App\Models\Brand;
use App\Models\BrandSource;
use App\Models\ContentItem;
use App\Models\PublishingJob;
use App\Models\ResearchItem;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClaimAttestationTest extends TestCase
{
    use RefreshDatabase;

    private const CLAIM = 'The advertised annual salary for Role A is GBP 30,000.';
    private const QUOTE = 'annual salary for Role A is GBP 30,000';

    private function scenario(string $slug = 'overseas-job-placement'): array
    {
        $brand = Brand::query()->create(['name'=>'Employer Guidance', 'slug'=>$slug, 'brand_config'=>[]]);
        $admin = User::factory()->create(['role'=>'admin','is_active'=>true]);
        $source = BrandSource::query()->create([
            'brand_id'=>$brand->id,
            'source_url'=>'https://employer.example.org/careers/role-a',
            'canonical_hash'=>hash('sha256','official-employer-role-a'),
            'source_type'=>'official_blog','trust_tier'=>'A','allowed'=>true,
        ]);
        $research = ResearchItem::query()->create([
            'brand_id'=>$brand->id,'source_id'=>$source->id,
            'title'=>'Official vacancy for Role A',
            'summary'=>'The employer listing confirms an annual salary for Role A is GBP 30,000, subject to eligibility.',
            'extracted_facts'=>['employer'=>'Example Employer','salary'=>'GBP 30,000'],
            'published_at'=>now()->subDay(),'fetched_at'=>now(),
            'content_hash'=>hash('sha256','employer-role-a-page-v1'),
            'freshness_status'=>'current','verification_status'=>'verified',
        ]);
        $content = ContentItem::query()->create([
            'brand_id'=>$brand->id,'title'=>'Role A official employer listing',
            'topic'=>'Verified vacancy','content_type'=>'educational_post',
            'language'=>'en', 'body'=>self::CLAIM.' Check official eligibility before applying.',
            'hashtags'=>[], 'source_refs'=>[],
            'claim_evidence'=>[], 'platform_variants'=>['linkedin'=>['body'=>self::CLAIM]],
            'status'=>'needs_review','risk_level'=>'high',
            'content_hash'=>hash('sha256','example-role-a-post-v1'),
        ]);
        return [$brand,$admin,$source,$research,$content];
    }

    private function attach(Brand $brand, ContentItem $item, ResearchItem $research, User $actor): void
    {
        $this->actingAs($actor)->postJson(
            "/api/v1/brands/{$brand->id}/content/{$item->id}/claims",
            ['claim'=>self::CLAIM, 'research_item_ids'=>[$research->id]]
        )->assertCreated()->assertJsonPath('status','needs_attestation');
    }

    private function attest(Brand $brand, ContentItem $item, ResearchItem $research, User $actor, string $decision): void
    {
        $this->actingAs($actor)->postJson(
            "/api/v1/brands/{$brand->id}/content/{$item->id}/claims/attest",
            [
                'claim'=>self::CLAIM, 'research_item_id'=>$research->id,
                'source_excerpt'=>self::QUOTE,
                'rationale'=>'I independently compared this employer listing and its salary statement to the claim.',
                'decision'=>$decision, 'acknowledged'=>true,
            ]
        )->assertCreated()->assertJsonPath('decision',$decision);
    }

    public function test_high_risk_salary_claim_requires_citations_and_admin_attestation(): void
    {
        [$brand,$admin,$source,$research,$item]=$this->scenario();
        $base="/api/v1/brands/{$brand->id}/content/{$item->id}";
        $this->actingAs($admin)->postJson($base.'/approve',[])
            ->assertUnprocessable()->assertJsonValidationErrors('content');
        $this->attach($brand,$item,$research,$admin);
        $this->postJson($base.'/approve',[])->assertUnprocessable();
        $this->attest($brand,$item,$research,$admin,'supported');
        $this->postJson($base.'/approve',[])->assertOk()->assertJsonPath('status','approved');
        $manual=$this->postJson($base.'/manual-ready',['platform'=>'linkedin'])->assertCreated();
        $this->getJson($manual->json('manual_package_url'))->assertOk()
            ->assertJsonPath('platform','linkedin');
        $this->assertDatabaseHas('content_reviews',[
            'content_item_id'=>$item->id,'review_type'=>'claim_attestation',
            'result'=>'approved','reviewer_id'=>$admin->id,
        ]);
        $this->assertDatabaseHas('audit_logs',[
            'resource_type'=>'content_item', 'resource_id'=>(string)$item->id,
            'action'=>'content.claim_attested',
        ]);
    }

    public function test_reviewed_rejection_overrides_older_support_and_blocks_approval(): void
    {
        [$brand,$admin,$source,$research,$item]=$this->scenario();
        $this->attach($brand,$item,$research,$admin);
        $this->attest($brand,$item,$research,$admin,'supported');
        $this->attest($brand,$item,$research,$admin,'rejected');
        $errors=app(ContentPolicy::class)->inspect($brand,$item->fresh()->toArray());
        $this->assertContains('CLAIM_REJECTED',$errors);
        $this->actingAs($admin)->postJson("/api/v1/brands/{$brand->id}/content/{$item->id}/approve",[])
            ->assertUnprocessable();
    }

    public function test_unrelated_excerpt_foreign_brand_and_non_admin_attestation_fail_closed(): void
    {
        [$brand,$admin,$source,$research,$item]=$this->scenario();
        $other=Brand::query()->create(['name'=>'Other','slug'=>'other','brand_config'=>[]]);
        $outsider=User::factory()->create(['role'=>'editor','is_active'=>true]);
        $outsider->brands()->attach($other->id);
        $endpoint="/api/v1/brands/{$brand->id}/content/{$item->id}/claims";
        $this->actingAs($outsider)->postJson($endpoint,[
            'claim'=>self::CLAIM,'research_item_ids'=>[$research->id],
        ])->assertForbidden();
        $this->attach($brand,$item,$research,$admin);
        $editor=User::factory()->create(['role'=>'editor','is_active'=>true]);
        $editor->brands()->attach($brand->id);
        $attest="/api/v1/brands/{$brand->id}/content/{$item->id}/claims/attest";
        $payload=[
            'claim'=>self::CLAIM,'research_item_id'=>$research->id,
            'source_excerpt'=>self::QUOTE,
            'rationale'=>'The source supports this precise claim based on its published facts.',
            'decision'=>'supported','acknowledged'=>true,
        ];
        $this->actingAs($editor)->postJson($attest,$payload)->assertForbidden();
        $this->actingAs($admin)->postJson($attest,array_merge($payload,[
            'source_excerpt'=>'A completely invented sentence that is absent from the employer source.'
        ]))->assertUnprocessable()->assertJsonValidationErrors('source_excerpt');
        $this->assertDatabaseMissing('content_reviews',[
            'content_item_id'=>$item->id,'review_type'=>'claim_attestation',
        ]);
    }

    public function test_changed_source_invalidates_old_review_and_blocks_scheduled_post_without_network(): void
    {
        [$brand,$admin,$source,$research,$item]=$this->scenario();
        $this->attach($brand,$item,$research,$admin);
        $this->attest($brand,$item,$research,$admin,'supported');
        $this->actingAs($admin)->postJson("/api/v1/brands/{$brand->id}/content/{$item->id}/approve",[])
            ->assertOk();
        $account=SocialAccount::query()->create([
            'brand_id'=>$brand->id,'platform'=>'linkedin','account_name'=>'Approved Org',
            'external_account_id'=>'urn:li:organization:12121',
            'access_token_encrypted'=>encrypt('test-provider-token'),
            'scopes'=>['w_organization_social'],
            'connection_status'=>'connected','api_access_approved'=>true,
            'token_expires_at'=>now()->addDay(),
        ]);
        $post=PublishingJob::query()->create([
            'brand_id'=>$brand->id,'content_item_id'=>$item->id,
            'social_account_id'=>$account->id,'platform'=>'linkedin',
            'scheduled_at'=>now()->subMinute(),'status'=>'scheduled',
            'idempotency_key'=>'attested-research-then-changed',
            'request_metadata'=>['content_fingerprint'=>app(PublicationIntegrity::class)->fingerprint($item->fresh(),'linkedin')],
        ]);
        $research->content_hash=hash('sha256','employer-role-a-page-v2-edited');
        $research->save();
        Http::fake();
        $result=app(PublishService::class)->publish($post->id);
        $this->assertSame('blocked_review',$result['status']);
        $this->assertDatabaseHas('publishing_jobs',[
            'id'=>$post->id,'status'=>'blocked_review',
            'last_error'=>'CONTENT_EVIDENCE_REVALIDATION_FAILED',
        ]);
        Http::assertNothingSent();
        $this->assertContains('CLAIM_ATTESTATION_STALE',
            app(ContentPolicy::class)->inspect($brand,$item->fresh()->toArray()));
    }

    public function test_revoked_source_or_stale_research_blocks_approved_package(): void
    {
        [$brand,$admin,$source,$research,$item]=$this->scenario('tech');
        $this->attach($brand,$item,$research,$admin);
        $this->attest($brand,$item,$research,$admin,'supported');
        $this->actingAs($admin)->postJson("/api/v1/brands/{$brand->id}/content/{$item->id}/approve",[])
            ->assertOk();
        $base="/api/v1/brands/{$brand->id}/content/{$item->id}";
        $package=$this->postJson($base.'/manual-ready',['platform'=>'linkedin'])->assertCreated();
        $source->allowed=false;$source->save();
        $this->getJson($package->json('manual_package_url'))->assertUnprocessable();
        $source->allowed=true;$source->save();
        $research->freshness_status='stale';$research->save();
        $this->getJson($package->json('manual_package_url'))->assertUnprocessable();
    }
}
