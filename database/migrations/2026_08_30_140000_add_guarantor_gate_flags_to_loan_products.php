<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_products', 'guarantor_gate_1_required')) {
                $table->boolean('guarantor_gate_1_required')->default(false)->after('requires_guarantor');
            }
            if (! Schema::hasColumn('loan_products', 'guarantor_gate_2_required')) {
                $table->boolean('guarantor_gate_2_required')->default(false)->after('guarantor_gate_1_required');
            }
        });
    }

    public function down(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            foreach (['guarantor_gate_1_required', 'guarantor_gate_2_required'] as $column) {
                if (Schema::hasColumn('loan_products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
