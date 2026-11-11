<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->string('website_url', 2048)->nullable();
            $table->text('description')->nullable();
            $table->string('logo_path', 1024)->nullable();
            $table->json('brand_config');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('brand_user', function (Blueprint $table): void {
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['brand_id','user_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('brand_user');
        Schema::dropIfExists('brands');
    }
};
