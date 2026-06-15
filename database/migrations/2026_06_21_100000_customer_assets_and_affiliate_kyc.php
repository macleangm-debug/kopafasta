<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_assets')) {
            Schema::create('customer_assets', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->string('asset_type', 40);
                $table->string('label', 150);
                $table->text('description')->nullable();
                $table->string('registration_number', 80)->nullable();
                $table->decimal('estimated_value', 14, 2)->nullable();
                $table->json('photo_paths')->nullable();
                $table->json('metadata')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['customer_id', 'asset_type']);
            });
        }

        if (Schema::hasTable('loan_application_assets') && ! Schema::hasColumn('loan_application_assets', 'customer_asset_id')) {
            Schema::table('loan_application_assets', function (Blueprint $table): void {
                $table->foreignId('customer_asset_id')->nullable()->after('loan_application_id')->constrained()->nullOnDelete();
            });
        }

        Schema::table('vendors', function (Blueprint $table): void {
            if (! Schema::hasColumn('vendors', 'affiliate_kyc_status')) {
                $table->string('affiliate_kyc_status', 30)->nullable()->after('affiliate_commission_percent');
            }
            if (! Schema::hasColumn('vendors', 'affiliate_selfie_path')) {
                $table->string('affiliate_selfie_path', 255)->nullable()->after('affiliate_kyc_status');
            }
            if (! Schema::hasColumn('vendors', 'affiliate_id_path')) {
                $table->string('affiliate_id_path', 255)->nullable()->after('affiliate_selfie_path');
            }
            if (! Schema::hasColumn('vendors', 'affiliate_photo_path')) {
                $table->string('affiliate_photo_path', 255)->nullable()->after('affiliate_id_path');
            }
            if (! Schema::hasColumn('vendors', 'affiliate_phone_verified_at')) {
                $table->timestamp('affiliate_phone_verified_at')->nullable()->after('affiliate_photo_path');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('loan_application_assets') && Schema::hasColumn('loan_application_assets', 'customer_asset_id')) {
            Schema::table('loan_application_assets', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('customer_asset_id');
            });
        }

        Schema::dropIfExists('customer_assets');

        Schema::table('vendors', function (Blueprint $table): void {
            foreach ([
                'affiliate_kyc_status',
                'affiliate_selfie_path',
                'affiliate_id_path',
                'affiliate_photo_path',
                'affiliate_phone_verified_at',
            ] as $column) {
                if (Schema::hasColumn('vendors', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
