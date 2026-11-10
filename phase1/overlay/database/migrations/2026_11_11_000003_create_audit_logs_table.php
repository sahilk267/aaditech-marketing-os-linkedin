<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_type', 32);
            $table->string('action', 128)->index();
            $table->string('resource_type', 64);
            $table->string('resource_id', 64);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['resource_type', 'resource_id', 'created_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('audit_logs'); }
};
