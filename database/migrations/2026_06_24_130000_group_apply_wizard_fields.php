<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('loan_groups', function (Blueprint $table): void {
            if (! Schema::hasColumn('loan_groups', 'purpose')) {
                $table->string('purpose', 100)->nullable()->after('name');
            }
        });

        Schema::table('loan_group_members', function (Blueprint $table): void {
            if (! Schema::hasColumn('loan_group_members', 'requested_amount')) {
                $table->decimal('requested_amount', 14, 2)->nullable()->after('role');
            }
        });
    }

    public function down(): void
    {
        Schema::table('loan_group_members', function (Blueprint $table): void {
            if (Schema::hasColumn('loan_group_members', 'requested_amount')) {
                $table->dropColumn('requested_amount');
            }
        });

        Schema::table('loan_groups', function (Blueprint $table): void {
            if (Schema::hasColumn('loan_groups', 'purpose')) {
                $table->dropColumn('purpose');
            }
        });
    }
};
