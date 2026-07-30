<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('loan_groups', function (Blueprint $table): void {
            if (! Schema::hasColumn('loan_groups', 'purpose')) {
                $table->string('purpose', 100)->nullable();
            }
        });

        Schema::table('loan_group_members', function (Blueprint $table): void {
            if (! Schema::hasColumn('loan_group_members', 'requested_amount')) {
                $table->decimal('requested_amount', 14, 2)->nullable();
            }
            if (! Schema::hasColumn('loan_group_members', 'onboarding_status')) {
                $table->string('onboarding_status', 40)->nullable();
            }
            if (! Schema::hasColumn('loan_group_members', 'group_member_invitation_id')) {
                $table->unsignedBigInteger('group_member_invitation_id')->nullable();
            }
            if (! Schema::hasColumn('loan_group_members', 'underwriting_status')) {
                $table->string('underwriting_status', 40)->default('pending');
            }
            if (! Schema::hasColumn('loan_group_members', 'underwriting_notes')) {
                $table->text('underwriting_notes')->nullable();
            }
            if (! Schema::hasColumn('loan_group_members', 'leader_feedback')) {
                $table->text('leader_feedback')->nullable();
            }
            if (! Schema::hasColumn('loan_group_members', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable();
            }
            if (! Schema::hasColumn('loan_group_members', 'reviewed_by_user_id')) {
                $table->unsignedBigInteger('reviewed_by_user_id')->nullable();
            }
        });

        Schema::table('loan_groups', function (Blueprint $table): void {
            if (! Schema::hasColumn('loan_groups', 'leader_feedback')) {
                $table->text('leader_feedback')->nullable();
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
