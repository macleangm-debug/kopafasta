<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_applications', function (Blueprint $table): void {
            if (! Schema::hasColumn('loan_applications', 'rejection_advice_code')) {
                $table->string('rejection_advice_code', 80)->nullable()->after('rejection_internal_notes');
            }
            if (! Schema::hasColumn('loan_applications', 'rejection_advice')) {
                $table->text('rejection_advice')->nullable()->after('rejection_advice_code');
            }
            if (! Schema::hasColumn('loan_applications', 'screening_rejection_reason_code')) {
                $table->string('screening_rejection_reason_code', 80)->nullable()->after('rejection_advice');
            }
        });
    }

    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table): void {
            foreach (['rejection_advice_code', 'rejection_advice', 'screening_rejection_reason_code'] as $column) {
                if (Schema::hasColumn('loan_applications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
