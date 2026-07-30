<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'legal_signature_data')) {
                $table->longText('legal_signature_data')->nullable();
            }
            if (! Schema::hasColumn('customers', 'legal_signer_name')) {
                $table->string('legal_signer_name')->nullable();
            }
            if (! Schema::hasColumn('customers', 'legal_signed_at')) {
                $table->timestamp('legal_signed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            foreach (['legal_signed_at', 'legal_signer_name', 'legal_signature_data'] as $column) {
                if (Schema::hasColumn('customers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
