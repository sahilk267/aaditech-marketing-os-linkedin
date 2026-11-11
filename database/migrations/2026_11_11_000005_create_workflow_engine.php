<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workflow_jobs', function (Blueprint $t): void {
            $t->id(); $t->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $t->string('job_type', 64); $t->json('payload');
            $t->string('status', 32)->default('queued'); $t->unsignedTinyInteger('priority')->default(5);
            $t->timestamp('scheduled_at')->useCurrent(); $t->timestamp('started_at')->nullable();
            $t->timestamp('completed_at')->nullable(); $t->foreignId('lease_owner')->nullable()->constrained('worker_nodes')->nullOnDelete();
            $t->timestamp('lease_expires_at')->nullable(); $t->unsignedInteger('attempt_count')->default(0);
            $t->unsignedInteger('max_attempts')->default(5); $t->string('idempotency_key', 191)->nullable()->unique();
            $t->text('last_error')->nullable(); $t->json('result')->nullable(); $t->timestamps();
            $t->index(['status','priority','scheduled_at']); $t->index(['lease_expires_at']);
        });
        Schema::create('workflow_events', function (Blueprint $t): void {
            $t->id(); $t->string('event_type', 96); $t->string('aggregate_type', 64);
            $t->unsignedBigInteger('aggregate_id'); $t->json('payload');
            $t->timestamp('occurred_at')->useCurrent(); $t->timestamp('processed_at')->nullable();
            $t->string('status', 20)->default('pending'); $t->timestamps();
            $t->index(['status','occurred_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('workflow_events'); Schema::dropIfExists('workflow_jobs'); }
};
