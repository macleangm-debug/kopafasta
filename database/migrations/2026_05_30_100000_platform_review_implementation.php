<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vendors')) {
            Schema::table('vendors', function (Blueprint $table): void {
                if (! Schema::hasColumn('vendors', 'partner_cost')) {
                    $table->decimal('partner_cost', 14, 2)->nullable();
                }
                if (! Schema::hasColumn('vendors', 'markup_percent')) {
                    $table->decimal('markup_percent', 5, 2)->nullable();
                }
            });
        }

        if (Schema::hasTable('bank_accounts') && Schema::hasColumn('bank_accounts', 'purpose')) {
            // purpose column already supports string values — fee account types configured in admin UI.
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('vendors')) {
            Schema::table('vendors', function (Blueprint $table): void {
                if (Schema::hasColumn('vendors', 'markup_percent')) {
                    $table->dropColumn('markup_percent');
                }
                if (Schema::hasColumn('vendors', 'partner_cost')) {
                    $table->dropColumn('partner_cost');
                }
            });
        }
    }
};
