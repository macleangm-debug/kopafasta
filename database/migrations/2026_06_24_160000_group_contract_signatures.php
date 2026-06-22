<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_group_members', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_group_members', 'contract_signature_status')) {
                $table->string('contract_signature_status', 20)->default('pending')->after('underwriting_status');
            }
            if (! Schema::hasColumn('loan_group_members', 'contract_signer_name')) {
                $table->string('contract_signer_name', 120)->nullable()->after('contract_signature_status');
            }
            if (! Schema::hasColumn('loan_group_members', 'contract_signature_data')) {
                $table->longText('contract_signature_data')->nullable()->after('contract_signer_name');
            }
            if (! Schema::hasColumn('loan_group_members', 'contract_signed_at')) {
                $table->timestamp('contract_signed_at')->nullable()->after('contract_signature_data');
            }
            if (! Schema::hasColumn('loan_group_members', 'contract_declined_at')) {
                $table->timestamp('contract_declined_at')->nullable()->after('contract_signed_at');
            }
            if (! Schema::hasColumn('loan_group_members', 'contract_decline_reason')) {
                $table->string('contract_decline_reason', 500)->nullable()->after('contract_declined_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('loan_group_members', function (Blueprint $table) {
            foreach ([
                'contract_decline_reason',
                'contract_declined_at',
                'contract_signed_at',
                'contract_signature_data',
                'contract_signer_name',
                'contract_signature_status',
            ] as $column) {
                if (Schema::hasColumn('loan_group_members', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
