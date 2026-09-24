<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'merged_into_customer_id')) {
                $table->unsignedBigInteger('merged_into_customer_id')->nullable()->after('status');
            }
            if (! Schema::hasColumn('customers', 'merged_at')) {
                $table->timestamp('merged_at')->nullable();
            }
            if (! Schema::hasColumn('customers', 'merged_by_user_id')) {
                $table->unsignedBigInteger('merged_by_user_id')->nullable();
            }
            if (! Schema::hasColumn('customers', 'merge_reason')) {
                $table->string('merge_reason', 180)->nullable();
            }
            if (! Schema::hasColumn('customers', 'merge_snapshot')) {
                $table->json('merge_snapshot')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            foreach (['merged_into_customer_id', 'merged_at', 'merged_by_user_id', 'merge_reason', 'merge_snapshot'] as $column) {
                if (Schema::hasColumn('customers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
