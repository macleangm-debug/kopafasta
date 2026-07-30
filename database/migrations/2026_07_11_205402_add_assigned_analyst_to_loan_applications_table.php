<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_applications', 'assigned_analyst_id')) {
                $table->foreignId('assigned_analyst_id')
                    ->nullable()
                    
                    ->constrained('users')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('loan_applications', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            if (Schema::hasColumn('loan_applications', 'assigned_analyst_id')) {
                $table->dropConstrainedForeignId('assigned_analyst_id');
            }
            if (Schema::hasColumn('loan_applications', 'assigned_at')) {
                $table->dropColumn('assigned_at');
            }
        });
    }
};
