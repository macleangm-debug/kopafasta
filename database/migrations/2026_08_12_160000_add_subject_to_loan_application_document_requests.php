<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_application_document_requests')) {
            return;
        }

        Schema::table('loan_application_document_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('loan_application_document_requests', 'subject_kind')) {
                $table->string('subject_kind', 20)->default('borrower')->after('loan_application_id');
            }
            if (! Schema::hasColumn('loan_application_document_requests', 'subject_customer_id')) {
                $table->foreignId('subject_customer_id')->nullable()->after('subject_kind')
                    ->constrained('customers')->nullOnDelete();
            }
            if (! Schema::hasColumn('loan_application_document_requests', 'loan_group_member_id')) {
                $table->unsignedBigInteger('loan_group_member_id')->nullable()->after('subject_customer_id');
            }
            if (! Schema::hasColumn('loan_application_document_requests', 'uploaded_by_customer_id')) {
                $table->foreignId('uploaded_by_customer_id')->nullable()->after('loan_group_member_id')
                    ->constrained('customers')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('loan_application_document_requests')) {
            return;
        }

        Schema::table('loan_application_document_requests', function (Blueprint $table): void {
            foreach (['uploaded_by_customer_id', 'subject_customer_id'] as $column) {
                if (Schema::hasColumn('loan_application_document_requests', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
            foreach (['loan_group_member_id', 'subject_kind'] as $column) {
                if (Schema::hasColumn('loan_application_document_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
