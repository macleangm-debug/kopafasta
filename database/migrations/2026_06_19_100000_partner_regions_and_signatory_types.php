<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table): void {
            if (! Schema::hasColumn('vendors', 'regions')) {
                $table->json('regions')->nullable();
            }
        });

        Schema::table('company_signatories', function (Blueprint $table): void {
            if (! Schema::hasColumn('company_signatories', 'signatory_type')) {
                $table->string('signatory_type', 40)->default('company');
            }
            if (! Schema::hasColumn('company_signatories', 'stamp_path')) {
                $table->string('stamp_path')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table): void {
            if (Schema::hasColumn('vendors', 'regions')) {
                $table->dropColumn('regions');
            }
        });

        Schema::table('company_signatories', function (Blueprint $table): void {
            foreach (['signatory_type', 'stamp_path'] as $column) {
                if (Schema::hasColumn('company_signatories', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
