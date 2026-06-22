<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_group_members', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_group_members', 'member_status')) {
                $table->string('member_status', 20)->default('active')->after('role');
            }
            if (! Schema::hasColumn('loan_group_members', 'replaced_at')) {
                $table->timestamp('replaced_at')->nullable()->after('member_status');
            }
        });

        Schema::table('group_member_invitations', function (Blueprint $table) {
            if (! Schema::hasColumn('group_member_invitations', 'replaces_loan_group_member_id')) {
                $table->foreignId('replaces_loan_group_member_id')
                    ->nullable()
                    ->after('loan_application_draft_id')
                    ->constrained('loan_group_members')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('group_member_invitations', function (Blueprint $table) {
            if (Schema::hasColumn('group_member_invitations', 'replaces_loan_group_member_id')) {
                $table->dropConstrainedForeignId('replaces_loan_group_member_id');
            }
        });

        Schema::table('loan_group_members', function (Blueprint $table) {
            foreach (['replaced_at', 'member_status'] as $column) {
                if (Schema::hasColumn('loan_group_members', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
