<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_applications', function (Blueprint $table): void {
            if (! Schema::hasColumn('loan_applications', 'rejection_reason_code')) {
                $table->string('rejection_reason_code', 80)->nullable();
            }
            if (! Schema::hasColumn('loan_applications', 'rejection_internal_notes')) {
                $table->text('rejection_internal_notes')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table): void {
            $cols = array_filter(
                ['rejection_reason_code', 'rejection_internal_notes'],
                fn (string $c) => Schema::hasColumn('loan_applications', $c),
            );
            if ($cols) {
                $table->dropColumn($cols);
            }
        });
    }
};
