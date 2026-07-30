<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('group_member_invitations')) {
            Schema::table('group_member_invitations', function (Blueprint $table): void {
                if (! Schema::hasColumn('group_member_invitations', 'member_signer_name')) {
                    $table->string('member_signer_name', 120)->nullable();
                }
                if (! Schema::hasColumn('group_member_invitations', 'member_signature_data')) {
                    $table->text('member_signature_data')->nullable();
                }
                if (! Schema::hasColumn('group_member_invitations', 'member_signed_at')) {
                    $table->timestamp('member_signed_at')->nullable();
                }
            });
        }

        if (Schema::hasTable('loan_group_members')) {
            Schema::table('loan_group_members', function (Blueprint $table): void {
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
        }

        if (Schema::hasTable('loan_groups')) {
            Schema::table('loan_groups', function (Blueprint $table): void {
                if (! Schema::hasColumn('loan_groups', 'leader_feedback')) {
                    $table->text('leader_feedback')->nullable();
                }
            });
        }

        if (Schema::hasTable('application_signatures')) {
            Schema::table('application_signatures', function (Blueprint $table): void {
                if (! Schema::hasColumn('application_signatures', 'group_member_invitation_id')) {
                    $table->unsignedBigInteger('group_member_invitation_id')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('application_signatures')) {
            Schema::table('application_signatures', function (Blueprint $table): void {
                if (Schema::hasColumn('application_signatures', 'group_member_invitation_id')) {
                    $table->dropColumn('group_member_invitation_id');
                }
            });
        }

        if (Schema::hasTable('loan_groups')) {
            Schema::table('loan_groups', function (Blueprint $table): void {
                if (Schema::hasColumn('loan_groups', 'leader_feedback')) {
                    $table->dropColumn('leader_feedback');
                }
            });
        }

        if (Schema::hasTable('loan_group_members')) {
            Schema::table('loan_group_members', function (Blueprint $table): void {
                foreach (['underwriting_status', 'underwriting_notes', 'leader_feedback', 'reviewed_at', 'reviewed_by_user_id'] as $column) {
                    if (Schema::hasColumn('loan_group_members', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('group_member_invitations')) {
            Schema::table('group_member_invitations', function (Blueprint $table): void {
                foreach (['member_signer_name', 'member_signature_data', 'member_signed_at'] as $column) {
                    if (Schema::hasColumn('group_member_invitations', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
