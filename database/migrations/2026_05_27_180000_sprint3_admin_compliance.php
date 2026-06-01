<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_products', function (Blueprint $table): void {
            if (! Schema::hasColumn('loan_products', 'bot_regulated_rate')) {
                $table->decimal('bot_regulated_rate', 8, 4)->nullable()->after('interest_rate');
            }
            if (! Schema::hasColumn('loan_products', 'processing_fee_rate')) {
                $table->decimal('processing_fee_rate', 8, 4)->default(0)->after('bot_regulated_rate');
            }
            if (! Schema::hasColumn('loan_products', 'service_fee_rate')) {
                $table->decimal('service_fee_rate', 8, 4)->default(0)->after('processing_fee_rate');
            }
            if (! Schema::hasColumn('loan_products', 'administration_fee_rate')) {
                $table->decimal('administration_fee_rate', 8, 4)->default(0)->after('service_fee_rate');
            }
            if (! Schema::hasColumn('loan_products', 'offer_letter_template_id')) {
                $table->foreignId('offer_letter_template_id')->nullable()->after('approval_workflow_id')->constrained('document_templates')->nullOnDelete();
            }
            if (! Schema::hasColumn('loan_products', 'loan_contract_template_id')) {
                $table->foreignId('loan_contract_template_id')->nullable()->after('offer_letter_template_id')->constrained('document_templates')->nullOnDelete();
            }
            if (! Schema::hasColumn('loan_products', 'guarantor_agreement_template_id')) {
                $table->foreignId('guarantor_agreement_template_id')->nullable()->after('loan_contract_template_id')->constrained('document_templates')->nullOnDelete();
            }
            if (! Schema::hasColumn('loan_products', 'asset_lending_agreement_template_id')) {
                $table->foreignId('asset_lending_agreement_template_id')->nullable()->after('guarantor_agreement_template_id')->constrained('document_templates')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('loan_products', function (Blueprint $table): void {
            foreach ([
                'asset_lending_agreement_template_id',
                'guarantor_agreement_template_id',
                'loan_contract_template_id',
                'offer_letter_template_id',
            ] as $column) {
                if (Schema::hasColumn('loan_products', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }

            foreach ([
                'administration_fee_rate',
                'service_fee_rate',
                'processing_fee_rate',
                'bot_regulated_rate',
            ] as $column) {
                if (Schema::hasColumn('loan_products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
