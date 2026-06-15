<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vendors') && ! Schema::hasColumn('vendors', 'supplier_type')) {
            Schema::table('vendors', function (Blueprint $table): void {
                $table->string('supplier_type', 30)->default('managed_loan')->after('category');
            });
        }

        if (Schema::hasTable('asset_requests')) {
            Schema::table('asset_requests', function (Blueprint $table): void {
                if (! Schema::hasColumn('asset_requests', 'description')) {
                    $table->text('description')->nullable()->after('asset_name');
                }
                if (! Schema::hasColumn('asset_requests', 'additional_photos')) {
                    $table->json('additional_photos')->nullable()->after('photo_path');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('vendors') && Schema::hasColumn('vendors', 'supplier_type')) {
            Schema::table('vendors', function (Blueprint $table): void {
                $table->dropColumn('supplier_type');
            });
        }

        if (Schema::hasTable('asset_requests')) {
            Schema::table('asset_requests', function (Blueprint $table): void {
                if (Schema::hasColumn('asset_requests', 'description')) {
                    $table->dropColumn('description');
                }
                if (Schema::hasColumn('asset_requests', 'additional_photos')) {
                    $table->dropColumn('additional_photos');
                }
            });
        }
    }
};
