<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('capital_withdrawal_requests')) {
            Schema::create('capital_withdrawal_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('lender_id')->constrained()->cascadeOnDelete();
                $table->foreignId('funding_pool_id')->nullable()->constrained()->nullOnDelete();
                $table->decimal('amount', 18, 2);
                $table->string('status', 20)->default('pending'); // pending | approved | rejected | cancelled
                $table->text('notes')->nullable();
                $table->text('admin_notes')->nullable();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                $table->index(['lender_id', 'status']);
            });
        }

        if (Schema::hasTable('lender_transactions')) {
            Schema::table('lender_transactions', function (Blueprint $table): void {
                if (! Schema::hasColumn('lender_transactions', 'loan_id')) {
                    $table->foreignId('loan_id')->nullable()->constrained()->nullOnDelete();
                }
                if (! Schema::hasColumn('lender_transactions', 'created_by')) {
                    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('lender_transactions', 'direction')) {
                    $table->string('direction', 10)->nullable(); // credit | debit
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('capital_withdrawal_requests');

        if (Schema::hasTable('lender_transactions')) {
            Schema::table('lender_transactions', function (Blueprint $table): void {
                foreach (['loan_id', 'created_by', 'direction'] as $col) {
                    if (Schema::hasColumn('lender_transactions', $col)) {
                        if ($col === 'loan_id') {
                            $table->dropConstrainedForeignId('loan_id');
                        } elseif ($col === 'created_by') {
                            $table->dropConstrainedForeignId('created_by');
                        } else {
                            $table->dropColumn($col);
                        }
                    }
                }
            });
        }
    }
};
