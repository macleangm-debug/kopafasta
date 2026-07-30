<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            // any | individual | business | group
            $table->string('applies_to', 20)->default('any');
            $table->index(['category', 'applies_to']);
        });

        // Existing seeded KYC docs are for individuals
        DB::table('document_types')
            ->where('category', 'kyc')
            ->whereIn('code', ['national_id_front', 'national_id_back', 'selfie', 'signature'])
            ->update(['applies_to' => 'individual']);
    }

    public function down(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->dropIndex(['category', 'applies_to']);
            $table->dropColumn('applies_to');
        });
    }
};
