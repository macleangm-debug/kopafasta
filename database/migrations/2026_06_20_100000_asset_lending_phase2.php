<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketplace_assets')) {
            Schema::table('marketplace_assets', function (Blueprint $table): void {
                if (! Schema::hasColumn('marketplace_assets', 'serial_number')) {
                    $table->string('serial_number', 80)->nullable()->after('title');
                }
                if (! Schema::hasColumn('marketplace_assets', 'chassis_number')) {
                    $table->string('chassis_number', 80)->nullable()->after('serial_number');
                }
                if (! Schema::hasColumn('marketplace_assets', 'engine_number')) {
                    $table->string('engine_number', 80)->nullable()->after('chassis_number');
                }
                if (! Schema::hasColumn('marketplace_assets', 'insurance_policy_number')) {
                    $table->string('insurance_policy_number', 80)->nullable()->after('engine_number');
                }
            });
        }

        if (Schema::hasTable('loan_application_post_approval_fees')
            && ! Schema::hasColumn('loan_application_post_approval_fees', 'manual_post_approval_fee_id')) {
            Schema::table('loan_application_post_approval_fees', function (Blueprint $table): void {
                $table->unsignedBigInteger('manual_post_approval_fee_id')
                    ->nullable()
                    ->after('loan_product_post_approval_fee_id');
            });

            Schema::table('loan_application_post_approval_fees', function (Blueprint $table): void {
                $table->foreign('manual_post_approval_fee_id', 'app_post_fee_manual_fee_fk')
                    ->references('id')
                    ->on('manual_post_approval_fees')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('loan_application_post_approval_fees')
            && Schema::hasColumn('loan_application_post_approval_fees', 'manual_post_approval_fee_id')) {
            Schema::table('loan_application_post_approval_fees', function (Blueprint $table): void {
                $table->dropForeign('app_post_fee_manual_fee_fk');
                $table->dropColumn('manual_post_approval_fee_id');
            });
        }

        if (Schema::hasTable('marketplace_assets')) {
            Schema::table('marketplace_assets', function (Blueprint $table): void {
                foreach (['serial_number', 'chassis_number', 'engine_number', 'insurance_policy_number'] as $col) {
                    if (Schema::hasColumn('marketplace_assets', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
