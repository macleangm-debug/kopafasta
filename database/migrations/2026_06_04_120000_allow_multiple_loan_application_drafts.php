<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_application_drafts')) {
            return;
        }

        Schema::table('loan_application_drafts', function (Blueprint $table): void {
            $table->dropUnique(['customer_id']);
            $table->unique(['customer_id', 'loan_product_id']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('loan_application_drafts')) {
            return;
        }

        Schema::table('loan_application_drafts', function (Blueprint $table): void {
            $table->dropUnique(['customer_id', 'loan_product_id']);
            $table->unique('customer_id');
        });
    }
};
