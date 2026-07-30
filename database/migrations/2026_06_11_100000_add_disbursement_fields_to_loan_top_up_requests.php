<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_top_up_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_top_up_requests', 'disbursed_at')) {
                $table->timestamp('disbursed_at')->nullable();
            }
            if (! Schema::hasColumn('loan_top_up_requests', 'disbursed_by')) {
                $table->foreignId('disbursed_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('loan_top_up_requests', function (Blueprint $table) {
            if (Schema::hasColumn('loan_top_up_requests', 'disbursed_by')) {
                $table->dropConstrainedForeignId('disbursed_by');
            }
            if (Schema::hasColumn('loan_top_up_requests', 'disbursed_at')) {
                $table->dropColumn('disbursed_at');
            }
        });
    }
};
