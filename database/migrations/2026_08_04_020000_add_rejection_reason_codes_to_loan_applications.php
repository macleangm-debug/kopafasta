<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_applications', 'rejection_reason_codes')) {
                $table->json('rejection_reason_codes')->nullable()->after('rejection_reason_code');
            }
        });

        if (Schema::hasColumn('loan_applications', 'rejection_reason_codes')) {
            DB::table('loan_applications')
                ->whereNotNull('rejection_reason_code')
                ->whereNull('rejection_reason_codes')
                ->orderBy('id')
                ->chunkById(200, function ($rows) {
                    foreach ($rows as $row) {
                        DB::table('loan_applications')->where('id', $row->id)->update([
                            'rejection_reason_codes' => json_encode([(string) $row->rejection_reason_code]),
                        ]);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            if (Schema::hasColumn('loan_applications', 'rejection_reason_codes')) {
                $table->dropColumn('rejection_reason_codes');
            }
        });
    }
};
