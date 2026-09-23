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
        Schema::create('shop_cost_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->unique()->constrained('shops')->cascadeOnDelete();
            $table->string('staff_cost')->default('0');
            $table->string('rent_cost')->default('0');
            $table->string('utilities_cost')->default('0');
            $table->string('other_overhead')->default('0');
            $table->string('min_margin_percent')->default('0');
            $table->string('allocation_method')->default('equal_split');
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('direct_cost')->nullable()->after('last_applied_price');
            $table->string('min_margin_percent')->nullable()->after('direct_cost');
            $table->string('max_price')->nullable()->after('min_margin_percent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['direct_cost', 'min_margin_percent', 'max_price']);
        });

        Schema::dropIfExists('shop_cost_profiles');
    }
};
