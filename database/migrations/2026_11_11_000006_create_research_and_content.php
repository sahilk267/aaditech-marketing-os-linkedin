<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('brand_sources', function (Blueprint $t): void {
            $t->id(); $t->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $t->text('source_url'); $t->string('canonical_hash', 64);
            $t->string('source_type',32); $t->string('trust_tier',1)->default('C');
            $t->boolean('allowed')->default(false); $t->timestamp('robots_checked_at')->nullable();
            $t->timestamp('last_fetched_at')->nullable(); $t->string('last_status',32)->default('registered');
            $t->timestamps(); $t->unique(['brand_id','canonical_hash']);
        });
        Schema::create('research_items', function (Blueprint $t): void {
            $t->id(); $t->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $t->foreignId('source_id')->constrained('brand_sources')->restrictOnDelete();
            $t->string('title',300); $t->text('summary')->nullable(); $t->json('extracted_facts');
            $t->timestamp('published_at')->nullable(); $t->timestamp('fetched_at');
            $t->char('content_hash',64); $t->string('freshness_status',24)->default('current');
            $t->string('verification_status',24)->default('unverified'); $t->timestamps();
            $t->unique(['brand_id','source_id','content_hash']); $t->index(['brand_id','fetched_at']);
        });
        Schema::create('campaigns', function (Blueprint $t): void {
            $t->id(); $t->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $t->string('name'); $t->string('objective'); $t->string('audience');
            $t->json('content_pillars'); $t->date('start_date')->nullable(); $t->date('end_date')->nullable();
            $t->string('status',24)->default('draft'); $t->timestamps();
        });
        Schema::create('content_items', function (Blueprint $t): void {
            $t->id(); $t->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $t->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $t->string('title'); $t->string('topic'); $t->string('content_type',48);
            $t->string('language',12)->default('en'); $t->text('hook')->nullable(); $t->longText('body');
            $t->text('cta')->nullable(); $t->json('hashtags'); $t->json('source_refs');
            $t->json('claim_evidence'); $t->json('platform_variants'); $t->string('status',32)->default('draft');
            $t->unsignedTinyInteger('quality_score')->nullable(); $t->char('content_hash',64);
            $t->string('ai_provider',40)->nullable(); $t->string('ai_model',160)->nullable();
            $t->string('prompt_version',40)->nullable(); $t->string('risk_level',20)->default('low');
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable(); $t->timestamps();
            $t->index(['brand_id','status','created_at']); $t->index(['brand_id','content_hash']);
        });
        Schema::create('content_reviews', function (Blueprint $t): void {
            $t->id(); $t->foreignId('content_item_id')->constrained()->cascadeOnDelete();
            $t->string('review_type',40); $t->string('result',24); $t->json('findings');
            $t->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at'); $t->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('content_reviews'); Schema::dropIfExists('content_items');
        Schema::dropIfExists('campaigns'); Schema::dropIfExists('research_items'); Schema::dropIfExists('brand_sources');
    }
};
