<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('lenders')) {
            return;
        }

        Schema::table('lenders', function (Blueprint $table): void {
            if (! Schema::hasColumn('lenders', 'revenue_share_percent')) {
                $table->decimal('revenue_share_percent', 5, 2)->nullable()->after('funding_source');
            }
            if (! Schema::hasColumn('lenders', 'registration_number')) {
                $table->string('registration_number', 80)->nullable()->after('revenue_share_percent');
            }
            if (! Schema::hasColumn('lenders', 'tax_id')) {
                $table->string('tax_id', 40)->nullable()->after('registration_number');
            }
            if (! Schema::hasColumn('lenders', 'license_number')) {
                $table->string('license_number', 80)->nullable()->after('tax_id');
            }
            if (! Schema::hasColumn('lenders', 'kyc_status')) {
                $table->string('kyc_status', 20)->nullable()->default('pending')->after('license_number');
            }
            if (! Schema::hasColumn('lenders', 'kyc_verified_at')) {
                $table->timestamp('kyc_verified_at')->nullable()->after('kyc_status');
            }
            if (! Schema::hasColumn('lenders', 'kyc_notes')) {
                $table->text('kyc_notes')->nullable()->after('kyc_verified_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('lenders')) {
            return;
        }

        Schema::table('lenders', function (Blueprint $table): void {
            foreach ([
                'kyc_notes',
                'kyc_verified_at',
                'kyc_status',
                'license_number',
                'tax_id',
                'registration_number',
                'revenue_share_percent',
            ] as $column) {
                if (Schema::hasColumn('lenders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
