<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table): void {
            $table->boolean('auto_apply_enabled')->default(false)->after('pricing_mode');
            $table->decimal('auto_apply_max_drop_percent', 5, 2)->default(15.00)->after('auto_apply_enabled');
            $table->boolean('auto_apply_only_fresh_rivals')->default(true)->after('auto_apply_max_drop_percent');
            $table->boolean('auto_apply_notify_daily')->default(true)->after('auto_apply_only_fresh_rivals');
            $table->timestamp('last_auto_apply_at')->nullable()->after('auto_apply_notify_daily');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('auto_apply_excluded')->default(false)->index()->after('pending_price');
            $table->timestamp('last_auto_applied_at')->nullable()->after('auto_apply_excluded');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['auto_apply_excluded']);
            $table->dropColumn(['auto_apply_excluded', 'last_auto_applied_at']);
        });

        Schema::table('shops', function (Blueprint $table): void {
            $table->dropColumn([
                'auto_apply_enabled',
                'auto_apply_max_drop_percent',
                'auto_apply_only_fresh_rivals',
                'auto_apply_notify_daily',
                'last_auto_apply_at',
            ]);
        });
    }
};
