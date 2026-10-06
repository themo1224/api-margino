<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('max_rival_products')->default(50)->after('status');
            $table->unsignedInteger('rival_refresh_hours')->default(24)->after('max_rival_products');
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->string('pricing_mode')->default('alert')->after('plan_id');
            $table->boolean('alerts_enabled')->default(true)->after('pricing_mode');
            $table->string('rival_undercut_threshold_percent')->default('5')->after('alerts_enabled');
            $table->unsignedInteger('cost_stale_days')->default(30)->after('rival_undercut_threshold_percent');
            $table->string('pricing_strategy')->default('match_cheapest')->after('cost_stale_days');
            $table->string('undercut_percent')->default('1')->after('pricing_strategy');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('brand')->nullable()->after('name');
            $table->string('barcode')->nullable()->after('brand');
            $table->boolean('rivals_stale')->default(true)->after('max_price');
            $table->boolean('cannot_match_profitably')->default(false)->after('rivals_stale');
        });

        Schema::create('product_rival_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('source');
            $table->string('listing_identity');
            $table->string('listing_title')->nullable();
            $table->string('listing_url')->nullable();
            $table->string('search_query')->nullable();
            $table->decimal('confidence', 5, 4)->default(0);
            $table->string('status');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'source']);
            $table->index(['source', 'listing_identity']);
        });

        Schema::create('rival_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('source');
            $table->string('listing_identity');
            $table->string('listing_title')->nullable();
            $table->string('cheapest_price');
            $table->string('median_price')->nullable();
            $table->unsignedInteger('competitor_count')->default(0);
            $table->string('currency')->default('IRR');
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->index(['product_id', 'source', 'captured_at']);
        });

        Schema::create('shop_alert_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('alert_type');
            $table->string('dedupe_key');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['shop_id', 'dedupe_key']);
            $table->index(['shop_id', 'alert_type', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_alert_dispatches');
        Schema::dropIfExists('rival_snapshots');
        Schema::dropIfExists('product_rival_matches');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['brand', 'barcode', 'rivals_stale', 'cannot_match_profitably']);
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn([
                'pricing_mode',
                'alerts_enabled',
                'rival_undercut_threshold_percent',
                'cost_stale_days',
                'pricing_strategy',
                'undercut_percent',
            ]);
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['max_rival_products', 'rival_refresh_hours']);
        });
    }
};
