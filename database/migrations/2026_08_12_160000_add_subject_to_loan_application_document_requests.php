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

        $hasSubjectKind = Schema::hasColumn('loan_application_document_requests', 'subject_kind');
        $hasSubjectCustomer = Schema::hasColumn('loan_application_document_requests', 'subject_customer_id');
        $hasMemberId = Schema::hasColumn('loan_application_document_requests', 'loan_group_member_id');
        $hasUploadedBy = Schema::hasColumn('loan_application_document_requests', 'uploaded_by_customer_id');

        if ($hasSubjectKind && $hasSubjectCustomer && $hasMemberId && $hasUploadedBy) {
            return;
        }

        Schema::table('loan_application_document_requests', function (Blueprint $table) use (
            $hasSubjectKind,
            $hasSubjectCustomer,
            $hasMemberId,
            $hasUploadedBy,
        ): void {
            if (! $hasSubjectKind) {
                $table->string('subject_kind', 20)->default('borrower')->after('loan_application_id');
            }
            if (! $hasSubjectCustomer) {
                $table->unsignedBigInteger('subject_customer_id')->nullable()->after('subject_kind');
                $table->foreign('subject_customer_id', 'ladr_subject_customer_fk')
                    ->references('id')->on('customers')->nullOnDelete();
            }
            if (! $hasMemberId) {
                $table->unsignedBigInteger('loan_group_member_id')->nullable()->after('subject_customer_id');
            }
            if (! $hasUploadedBy) {
                $table->unsignedBigInteger('uploaded_by_customer_id')->nullable()->after('loan_group_member_id');
                $table->foreign('uploaded_by_customer_id', 'ladr_uploaded_by_customer_fk')
                    ->references('id')->on('customers')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        // Columns may pre-exist from earlier environments — leave intact.
    }
};
