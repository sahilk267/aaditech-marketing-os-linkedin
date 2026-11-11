<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_models', function (Blueprint $t): void {
            $t->id(); $t->string('provider',32); $t->string('model_id',180);
            $t->boolean('is_free')->default(false); $t->decimal('input_price',18,10)->nullable();
            $t->decimal('output_price',18,10)->nullable(); $t->json('capabilities');
            $t->unsignedInteger('context_limit')->nullable(); $t->string('health_status',24)->default('unknown');
            $t->boolean('is_enabled')->default(false); $t->unsignedTinyInteger('priority')->default(50);
            $t->timestamp('last_verified_at')->nullable(); $t->timestamps();
            $t->unique(['provider','model_id']); $t->index(['is_enabled','is_free','priority']);
        });
        Schema::create('ai_usage', function (Blueprint $t): void {
            $t->id(); $t->string('provider',32); $t->string('model_id',180);
            $t->string('task_type',64); $t->unsignedInteger('request_count')->default(1);
            $t->unsignedBigInteger('input_tokens')->nullable(); $t->unsignedBigInteger('output_tokens')->nullable();
            $t->decimal('estimated_cost',18,10)->default(0);
            $t->string('status',24); $t->timestamps(); $t->index(['provider','created_at']);
        });
        Schema::create('analytics_snapshots', function (Blueprint $t): void {
            $t->id(); $t->foreignId('publishing_job_id')->constrained()->cascadeOnDelete();
            $t->string('platform',32); $t->string('metric_name',70);
            $t->decimal('metric_value',22,6)->nullable(); $t->text('metric_definition');
            $t->timestamp('captured_at'); $t->string('source',40); $t->timestamps();
            $t->index(['publishing_job_id','captured_at']);
        });
        Schema::create('notification_logs', function (Blueprint $t): void {
            $t->id(); $t->string('channel',32); $t->string('event_type',96);
            $t->string('destination_masked')->nullable(); $t->string('status',24);
            $t->timestamp('sent_at')->nullable(); $t->text('error')->nullable(); $t->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('notification_logs'); Schema::dropIfExists('analytics_snapshots');
        Schema::dropIfExists('ai_usage'); Schema::dropIfExists('ai_models');
    }
};
