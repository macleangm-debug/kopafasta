<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_application_document_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('document'); // document | clarification
            $table->string('label');
            $table->text('instructions')->nullable();
            $table->string('status')->default('pending'); // pending, uploaded, satisfied, rejected
            $table->timestamp('due_at')->nullable();
            $table->text('borrower_response')->nullable();
            $table->text('admin_notes')->nullable();
            $table->foreignId('satisfied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('satisfied_at')->nullable();
            $table->timestamps();

            $table->index(['loan_application_id', 'status']);
        });

        Schema::table('customer_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('loan_application_document_request_id')
                ->nullable()
                ->after('loan_product_requirement_id');
            $table->index('loan_application_document_request_id');
        });
    }

    public function down(): void
    {
        Schema::table('customer_documents', function (Blueprint $table) {
            $table->dropIndex(['loan_application_document_request_id']);
            $table->dropColumn('loan_application_document_request_id');
        });

        Schema::dropIfExists('loan_application_document_requests');
    }
};
