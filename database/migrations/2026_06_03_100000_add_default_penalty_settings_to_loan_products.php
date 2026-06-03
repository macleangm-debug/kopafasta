<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_products', function (Blueprint $table): void {
            if (! Schema::hasColumn('loan_products', 'default_grace_days')) {
                $table->unsignedSmallInteger('default_grace_days')->default(7)->after('max_amount');
            }
            if (! Schema::hasColumn('loan_products', 'penalty_rate_percent')) {
                $table->decimal('penalty_rate_percent', 5, 2)->default(1)->after('default_grace_days');
            }
            if (! Schema::hasColumn('loan_products', 'penalty_basis')) {
                $table->string('penalty_basis', 20)->default('per_day')->after('penalty_rate_percent');
            }
        });

        Schema::table('loans', function (Blueprint $table): void {
            if (! Schema::hasColumn('loans', 'default_grace_days')) {
                $table->unsignedSmallInteger('default_grace_days')->nullable()->after('interest_rate');
            }
            if (! Schema::hasColumn('loans', 'penalty_rate_percent')) {
                $table->decimal('penalty_rate_percent', 5, 2)->nullable()->after('default_grace_days');
            }
            if (! Schema::hasColumn('loans', 'penalty_basis')) {
                $table->string('penalty_basis', 20)->nullable()->after('penalty_rate_percent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('loan_products', function (Blueprint $table): void {
            foreach (['default_grace_days', 'penalty_rate_percent', 'penalty_basis'] as $column) {
                if (Schema::hasColumn('loan_products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('loans', function (Blueprint $table): void {
            foreach (['default_grace_days', 'penalty_rate_percent', 'penalty_basis'] as $column) {
                if (Schema::hasColumn('loans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
