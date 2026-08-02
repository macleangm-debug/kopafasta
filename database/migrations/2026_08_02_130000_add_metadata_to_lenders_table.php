<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lenders', function (Blueprint $table) {
            if (! Schema::hasColumn('lenders', 'metadata')) {
                $table->json('metadata')->nullable()->after('kyc_notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lenders', function (Blueprint $table) {
            if (Schema::hasColumn('lenders', 'metadata')) {
                $table->dropColumn('metadata');
            }
        });
    }
};
