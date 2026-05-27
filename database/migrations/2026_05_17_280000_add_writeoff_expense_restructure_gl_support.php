<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            if (!Schema::hasColumn('loans', 'written_off_at')) {
                $table->timestamp('written_off_at')->nullable();
            }
            if (!Schema::hasColumn('loans', 'written_off_amount')) {
                $table->decimal('written_off_amount', 16, 2)->nullable();
            }
            if (!Schema::hasColumn('loans', 'write_off_reason')) {
                $table->text('write_off_reason')->nullable();
            }
        });

        Schema::table('expenses', function (Blueprint $table) {
            if (!Schema::hasColumn('expenses', 'gl_account_id')) {
                $table->unsignedBigInteger('gl_account_id')->nullable()->after('vendor_id');
            }
            if (!Schema::hasColumn('expenses', 'journal_posted_at')) {
                $table->timestamp('journal_posted_at')->nullable();
            }
        });

        Schema::table('restructure_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('restructure_requests', 'fee_amount')) {
                $table->decimal('fee_amount', 14, 2)->default(0);
            }
            if (!Schema::hasColumn('restructure_requests', 'new_principal')) {
                $table->decimal('new_principal', 16, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            foreach (['written_off_at', 'written_off_amount', 'write_off_reason'] as $col) {
                if (Schema::hasColumn('loans', $col)) $table->dropColumn($col);
            }
        });
        Schema::table('expenses', function (Blueprint $table) {
            foreach (['gl_account_id', 'journal_posted_at'] as $col) {
                if (Schema::hasColumn('expenses', $col)) $table->dropColumn($col);
            }
        });
        Schema::table('restructure_requests', function (Blueprint $table) {
            foreach (['fee_amount', 'new_principal'] as $col) {
                if (Schema::hasColumn('restructure_requests', $col)) $table->dropColumn($col);
            }
        });
    }
};
