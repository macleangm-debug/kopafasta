<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customer_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('loan_application_id')->nullable()->after('customer_id');
            $table->unsignedBigInteger('loan_product_requirement_id')->nullable()->after('document_type_id');
            $table->index('loan_application_id');
            $table->index('loan_product_requirement_id');
        });
    }

    public function down(): void
    {
        Schema::table('customer_documents', function (Blueprint $table) {
            $table->dropIndex(['loan_application_id']);
            $table->dropIndex(['loan_product_requirement_id']);
            $table->dropColumn(['loan_application_id', 'loan_product_requirement_id']);
        });
    }
};
