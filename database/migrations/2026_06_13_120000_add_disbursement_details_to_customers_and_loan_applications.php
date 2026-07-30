<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('preferred_disbursement_method', 20)->nullable();
            $table->string('disbursement_mobile_provider', 20)->nullable();
            $table->string('disbursement_mobile_number', 20)->nullable();
            $table->string('disbursement_mobile_account_name', 120)->nullable();
            $table->string('disbursement_bank_name', 120)->nullable();
            $table->string('disbursement_bank_account_name', 120)->nullable();
            $table->string('disbursement_bank_account_number', 40)->nullable();
            $table->string('disbursement_bank_branch', 120)->nullable();
        });

        Schema::table('loan_applications', function (Blueprint $table) {
            $table->timestamp('disbursement_details_confirmed_at')->nullable();
            $table->json('disbursement_details_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'preferred_disbursement_method',
                'disbursement_mobile_provider',
                'disbursement_mobile_number',
                'disbursement_mobile_account_name',
                'disbursement_bank_name',
                'disbursement_bank_account_name',
                'disbursement_bank_account_number',
                'disbursement_bank_branch',
            ]);
        });

        Schema::table('loan_applications', function (Blueprint $table) {
            $table->dropColumn(['disbursement_details_confirmed_at', 'disbursement_details_snapshot']);
        });
    }
};
