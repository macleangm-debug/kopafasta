<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_member_invitations', function (Blueprint $table): void {
            if (! Schema::hasColumn('group_member_invitations', 'draft_reference')) {
                $table->string('draft_reference', 40)->nullable();
            }
            if (! Schema::hasColumn('group_member_invitations', 'invitation_reason')) {
                $table->text('invitation_reason')->nullable();
            }
            if (! Schema::hasColumn('group_member_invitations', 'group_name')) {
                $table->string('group_name', 150)->nullable();
            }
            if (! Schema::hasColumn('group_member_invitations', 'group_purpose')) {
                $table->string('group_purpose', 80)->nullable();
            }
            if (! Schema::hasColumn('group_member_invitations', 'amount_per_member')) {
                $table->decimal('amount_per_member', 15, 2)->nullable();
            }
            if (! Schema::hasColumn('group_member_invitations', 'requested_tenure_months')) {
                $table->unsignedSmallInteger('requested_tenure_months')->nullable();
            }
            if (! Schema::hasColumn('group_member_invitations', 'repayment_cadence')) {
                $table->string('repayment_cadence', 20)->nullable();
            }
        });

        if (! Schema::hasTable('partner_payout_requests')) {
            Schema::create('partner_payout_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
                $table->string('wallet_type', 40);
                $table->decimal('amount', 15, 2);
                $table->string('status', 30)->default('pending');
                $table->text('notes')->nullable();
                $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_payout_requests');

        Schema::table('group_member_invitations', function (Blueprint $table): void {
            foreach ([
                'draft_reference',
                'invitation_reason',
                'group_name',
                'group_purpose',
                'amount_per_member',
                'requested_tenure_months',
                'repayment_cadence',
            ] as $column) {
                if (Schema::hasColumn('group_member_invitations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
