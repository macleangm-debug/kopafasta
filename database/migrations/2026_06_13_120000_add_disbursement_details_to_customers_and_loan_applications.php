<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('preferred_disbursement_method', 20)->nullable()->after('street');
            $table->string('disbursement_mobile_provider', 20)->nullable()->after('preferred_disbursement_method');
            $table->string('disbursement_mobile_number', 20)->nullable()->after('disbursement_mobile_provider');
            $table->string('disbursement_mobile_account_name', 120)->nullable()->after('disbursement_mobile_number');
            $table->string('disbursement_bank_name', 120)->nullable()->after('disbursement_mobile_account_name');
            $table->string('disbursement_bank_account_name', 120)->nullable()->after('disbursement_bank_name');
            $table->string('disbursement_bank_account_number', 40)->nullable()->after('disbursement_bank_account_name');
            $table->string('disbursement_bank_branch', 120)->nullable()->after('disbursement_bank_account_number');
        });

        Schema::table('loan_applications', function (Blueprint $table) {
            $table->timestamp('disbursement_details_confirmed_at')->nullable()->after('disbursed_at');
            $table->json('disbursement_details_snapshot')->nullable()->after('disbursement_details_confirmed_at');
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
