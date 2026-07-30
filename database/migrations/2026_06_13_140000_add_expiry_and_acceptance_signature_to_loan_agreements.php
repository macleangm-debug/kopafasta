<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_agreements', function (Blueprint $table): void {
            if (! Schema::hasColumn('loan_agreements', 'expires_at')) {
                $table->timestamp('expires_at')->nullable();
            }
            if (! Schema::hasColumn('loan_agreements', 'acceptance_signature_data')) {
                $table->text('acceptance_signature_data')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('loan_agreements', function (Blueprint $table): void {
            if (Schema::hasColumn('loan_agreements', 'acceptance_signature_data')) {
                $table->dropColumn('acceptance_signature_data');
            }
            if (Schema::hasColumn('loan_agreements', 'expires_at')) {
                $table->dropColumn('expires_at');
            }
        });
    }
};
