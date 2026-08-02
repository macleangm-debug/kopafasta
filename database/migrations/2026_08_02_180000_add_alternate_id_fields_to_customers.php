<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            if (! Schema::hasColumn('customers', 'alternate_id_types')) {
                $table->json('alternate_id_types')->nullable()->after('no_physical_nida_card');
            }
            if (! Schema::hasColumn('customers', 'alternate_id_notes')) {
                $table->string('alternate_id_notes', 255)->nullable()->after('alternate_id_types');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            if (Schema::hasColumn('customers', 'alternate_id_notes')) {
                $table->dropColumn('alternate_id_notes');
            }
            if (Schema::hasColumn('customers', 'alternate_id_types')) {
                $table->dropColumn('alternate_id_types');
            }
        });
    }
};
