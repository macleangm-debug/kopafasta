<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('group_member_invitations')) {
            Schema::create('group_member_invitations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('leader_customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('loan_product_id')->nullable()->constrained('loan_products')->nullOnDelete();
                $table->unsignedBigInteger('loan_application_draft_id')->nullable();
                $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->string('invitee_first_name');
                $table->string('invitee_middle_name')->nullable();
                $table->string('invitee_last_name');
                $table->string('invitee_phone', 20);
                $table->string('invitee_email')->nullable();
                $table->string('token', 64)->unique();
                $table->string('short_code', 12)->nullable()->unique();
                $table->string('status', 30)->default('pending');
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('responded_at')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('loan_group_members')) {
            Schema::table('loan_group_members', function (Blueprint $table): void {
                if (! Schema::hasColumn('loan_group_members', 'onboarding_status')) {
                    $table->string('onboarding_status', 40)->nullable()->after('role');
                }
                if (! Schema::hasColumn('loan_group_members', 'group_member_invitation_id')) {
                    $table->unsignedBigInteger('group_member_invitation_id')->nullable()->after('customer_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('loan_group_members')) {
            Schema::table('loan_group_members', function (Blueprint $table): void {
                foreach (['onboarding_status', 'group_member_invitation_id'] as $column) {
                    if (Schema::hasColumn('loan_group_members', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('group_member_invitations');
    }
};
