<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_agreements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('loan_application_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('loan_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->string('document_type', 40)->default('offer_letter'); // offer_letter | agreement | restructure_agreement
            $t->string('reference', 60)->unique();
            $t->string('file_path')->nullable();          // stored on disk 'public'
            $t->json('snapshot')->nullable();             // amounts/terms captured at generation time
            $t->string('status', 20)->default('draft');   // draft | sent | signed | expired | cancelled
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('signed_at')->nullable();
            $t->ipAddress('signed_ip')->nullable();
            $t->string('signed_user_agent', 255)->nullable();
            $t->string('signature_method', 20)->nullable(); // otp | manual
            $t->string('otp_code', 10)->nullable();
            $t->timestamp('otp_sent_at')->nullable();
            $t->timestamp('otp_expires_at')->nullable();
            $t->unsignedTinyInteger('otp_attempts')->default(0);
            $t->foreignId('generated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['loan_application_id', 'document_type']);
            $t->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_agreements');
    }
};
