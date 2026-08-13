<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_application_document_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_document_id')->constrained('customer_documents')->cascadeOnDelete();
            $table->string('subject_kind', 20)->default('borrower'); // borrower|guarantor|member
            $table->foreignId('subject_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->unsignedBigInteger('loan_group_member_id')->nullable();
            $table->string('status', 30)->default('pending_review'); // pending_review|verified|rejected
            $table->string('fail_reason_code', 80)->nullable();
            $table->text('fail_reason_custom')->nullable();
            $table->string('remedy', 40)->nullable(); // request_again|none
            $table->text('notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['loan_application_id', 'customer_document_id'], 'lad_reviews_app_doc_unique');
            $table->index(['loan_application_id', 'status'], 'lad_reviews_app_status_idx');
            $table->index(['subject_customer_id'], 'lad_reviews_subject_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_application_document_reviews');
    }
};
