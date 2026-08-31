<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_application_document_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_application_document_requests', 'checklist_item')) {
                $table->string('checklist_item', 80)->nullable()->after('label');
            }
            if (! Schema::hasColumn('loan_application_document_requests', 'gate')) {
                $table->string('gate', 40)->nullable()->after('checklist_item');
            }
            if (! Schema::hasColumn('loan_application_document_requests', 'request_reason')) {
                $table->string('request_reason', 500)->nullable()->after('gate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('loan_application_document_requests', function (Blueprint $table) {
            foreach (['request_reason', 'gate', 'checklist_item'] as $column) {
                if (Schema::hasColumn('loan_application_document_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
