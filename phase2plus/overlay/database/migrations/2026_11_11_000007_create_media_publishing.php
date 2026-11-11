<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $t): void {
            $t->id(); $t->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $t->foreignId('content_item_id')->nullable()->constrained()->nullOnDelete();
            $t->string('asset_type',32); $t->string('storage_path',700); $t->string('mime_type',100);
            $t->unsignedBigInteger('file_size'); $t->unsignedInteger('width')->nullable();
            $t->unsignedInteger('height')->nullable(); $t->unsignedInteger('duration_seconds')->nullable();
            $t->string('license_source')->nullable(); $t->char('checksum',64);
            $t->json('metadata'); $t->timestamps();
        });
        Schema::create('social_accounts', function (Blueprint $t): void {
            $t->id(); $t->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $t->string('platform',32); $t->string('account_name',200);
            $t->string('external_account_id',300); $t->text('access_token_encrypted')->nullable();
            $t->text('refresh_token_encrypted')->nullable(); $t->json('scopes');
            $t->timestamp('token_expires_at')->nullable(); $t->string('connection_status',32)->default('disconnected'); $t->boolean('api_access_approved')->default(false);
            $t->timestamp('last_health_check_at')->nullable(); $t->timestamps();
            $t->unique(['platform','external_account_id']); $t->index(['brand_id','platform']);
        });
        Schema::create('publishing_jobs', function (Blueprint $t): void {
            $t->id(); $t->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $t->foreignId('content_item_id')->constrained()->restrictOnDelete();
            $t->foreignId('social_account_id')->nullable()->constrained()->nullOnDelete();
            $t->string('platform',32); $t->timestamp('scheduled_at');
            $t->string('status',32)->default('scheduled'); $t->string('idempotency_key',191)->unique();
            $t->unsignedInteger('attempt_count')->default(0);
            $t->string('platform_post_id',255)->nullable(); $t->text('permalink')->nullable();
            $t->timestamp('published_at')->nullable(); $t->text('last_error')->nullable();
            $t->json('request_metadata')->nullable(); $t->json('response_metadata')->nullable(); $t->timestamps();
            $t->index(['status','scheduled_at']); $t->index(['social_account_id','status']);
        });
        Schema::create('oauth_states', function (Blueprint $t): void {
            $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $t->string('platform',32); $t->string('external_account_id',255); $t->char('state_hash',64)->unique();
            $t->timestamp('expires_at'); $t->timestamp('consumed_at')->nullable(); $t->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('oauth_states'); Schema::dropIfExists('publishing_jobs');
        Schema::dropIfExists('social_accounts'); Schema::dropIfExists('media_assets');
    }
};
