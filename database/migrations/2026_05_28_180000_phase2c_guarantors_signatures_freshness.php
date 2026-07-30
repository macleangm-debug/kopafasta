<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->timestamp('kyc_reconfirmed_at')->nullable();
        });

        Schema::create('guarantor_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_application_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_guarantor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('guarantor_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('type');
            $table->string('channel')->nullable();
            $table->string('contact')->nullable();
            $table->string('membership_id')->nullable();
            $table->string('invitee_name')->nullable();
            $table->string('token', 64)->unique();
            $table->string('status')->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->text('response_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('application_signatures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('loan_application_id')->constrained()->cascadeOnDelete();
            $table->string('signer_type');
            $table->string('signer_name');
            $table->text('signature_data');
            $table->timestamp('signed_at');
            $table->foreignId('guarantor_invitation_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_signatures');
        Schema::dropIfExists('guarantor_invitations');

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('kyc_reconfirmed_at');
        });
    }
};
