<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_application_document_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_application_document_requests', 'lifecycle')) {
                $table->json('lifecycle')->nullable()->after('request_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('loan_application_document_requests', function (Blueprint $table) {
            if (Schema::hasColumn('loan_application_document_requests', 'lifecycle')) {
                $table->dropColumn('lifecycle');
            }
        });
    }
};
