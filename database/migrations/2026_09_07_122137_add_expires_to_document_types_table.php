<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            if (! Schema::hasColumn('document_types', 'expires')) {
                $table->boolean('expires')->default(false)->after('is_active');
            }
        });

        if (Schema::hasColumn('document_types', 'expires')) {
            DB::table('document_types')->update(['expires' => false]);
            DB::table('document_types')->where('code', 'business_license')->update(['expires' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            if (Schema::hasColumn('document_types', 'expires')) {
                $table->dropColumn('expires');
            }
        });
    }
};
