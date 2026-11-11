<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('worker_nodes', function (Blueprint $table): void {
            $table->id();
            $table->string('node_name', 120);
            $table->string('platform', 64);
            $table->string('worker_version', 48);
            $table->json('capabilities');
            $table->timestamp('last_heartbeat_at')->nullable()->index();
            $table->string('status', 24)->default('registered');
            $table->timestamps();
        });
        Schema::create('worker_credentials', function (Blueprint $table): void {
            $table->id();
            $table->string('label', 120);
            $table->char('token_hash', 64);
            $table->json('allowed_capabilities');
            $table->foreignId('worker_node_id')->nullable()->unique()->constrained('worker_nodes')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
        Schema::create('worker_request_nonces', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('worker_credential_id')->constrained('worker_credentials')->cascadeOnDelete();
            $table->string('nonce', 80);
            $table->timestamp('created_at')->useCurrent()->index();
            $table->unique(['worker_credential_id','nonce']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('worker_request_nonces');
        Schema::dropIfExists('worker_credentials');
        Schema::dropIfExists('worker_nodes');
    }
};
