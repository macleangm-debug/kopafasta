<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table): void {
            if (! Schema::hasColumn('vendors', 'recovery_fee_type')) {
                $table->string('recovery_fee_type', 20)->nullable()->after('recovery_markup_percent');
            }
            if (! Schema::hasColumn('vendors', 'recovery_fixed_amount')) {
                $table->decimal('recovery_fixed_amount', 15, 2)->nullable()->after('recovery_fee_type');
            }
            if (! Schema::hasColumn('vendors', 'activation_token')) {
                $table->string('activation_token', 64)->nullable()->unique()->after('status');
            }
            if (! Schema::hasColumn('vendors', 'activation_sent_at')) {
                $table->timestamp('activation_sent_at')->nullable()->after('activation_token');
            }
            if (! Schema::hasColumn('vendors', 'activated_at')) {
                $table->timestamp('activated_at')->nullable()->after('activation_sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table): void {
            foreach (['recovery_fee_type', 'recovery_fixed_amount', 'activation_token', 'activation_sent_at', 'activated_at'] as $col) {
                if (Schema::hasColumn('vendors', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
