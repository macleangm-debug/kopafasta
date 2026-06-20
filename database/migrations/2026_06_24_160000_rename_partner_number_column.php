<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('partners')) {
            return;
        }

        if (Schema::hasColumn('partners', 'vendor_number') && ! Schema::hasColumn('partners', 'partner_number')) {
            Schema::table('partners', function (Blueprint $table): void {
                $table->renameColumn('vendor_number', 'partner_number');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('partners')) {
            return;
        }

        if (Schema::hasColumn('partners', 'partner_number') && ! Schema::hasColumn('partners', 'vendor_number')) {
            Schema::table('partners', function (Blueprint $table): void {
                $table->renameColumn('partner_number', 'vendor_number');
            });
        }
    }
};
