<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guarantor_invitations', function (Blueprint $table) {
            if (! Schema::hasColumn('guarantor_invitations', 'loan_product_id')) {
                $table->foreignId('loan_product_id')
                    ->nullable()
                    
                    ->constrained('loan_products')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('guarantor_invitations', function (Blueprint $table) {
            if (Schema::hasColumn('guarantor_invitations', 'loan_product_id')) {
                $table->dropConstrainedForeignId('loan_product_id');
            }
        });
    }
};
