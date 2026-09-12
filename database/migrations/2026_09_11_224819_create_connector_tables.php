<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('label');
            $table->string('status');
            $table->timestamps();
        });

        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->unique();
            $table->string('name');
            $table->string('status');
            $table->foreignId('plan_id')->constrained('plans');
            $table->timestamps();
        });

        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('prefix')->index();
            $table->string('key_hash');
            $table->string('name')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('external_id');
            $table->string('sku')->nullable();
            $table->string('name');
            $table->string('price');
            $table->string('currency');
            $table->string('recommended_price')->nullable();
            $table->boolean('below_floor')->default(false);
            $table->string('floor_price')->nullable();
            $table->timestamp('recommendation_updated_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_applied_price')->nullable();
            $table->timestamps();

            $table->unique(['shop_id', 'external_id']);
        });

        Schema::create('applied_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('applied_price');
            $table->string('currency');
            $table->string('source');
            $table->timestamp('applied_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applied_prices');
        Schema::dropIfExists('products');
        Schema::dropIfExists('api_keys');
        Schema::dropIfExists('shops');
        Schema::dropIfExists('plans');
    }
};
