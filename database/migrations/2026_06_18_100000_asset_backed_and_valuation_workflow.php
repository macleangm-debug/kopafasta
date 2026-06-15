<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_application_assets')) {
            Schema::create('loan_application_assets', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('loan_application_id')->constrained()->cascadeOnDelete();
                $table->string('asset_type', 40);
                $table->string('description', 500)->nullable();
                $table->decimal('market_value', 15, 2)->nullable();
                $table->decimal('forced_sale_value', 15, 2)->nullable();
                $table->decimal('ltv_percent', 5, 2)->nullable();
                $table->decimal('max_loan_amount', 15, 2)->nullable();
                $table->boolean('gps_required')->default(false);
                $table->string('valuation_status', 30)->default('pending');
                $table->timestamp('valuation_fee_paid_at')->nullable();
                $table->text('valuer_notes')->nullable();
                $table->timestamps();

                $table->unique('loan_application_id');
            });
        }

        if (! Schema::hasTable('valuation_assignments')) {
            Schema::create('valuation_assignments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('loan_application_id')->constrained()->cascadeOnDelete();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->foreignId('vendor_task_id')->nullable()->constrained()->nullOnDelete();
                $table->string('status', 30)->default('assigned');
                $table->decimal('market_value', 15, 2)->nullable();
                $table->decimal('forced_sale_value', 15, 2)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('assigned_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['loan_application_id', 'status']);
            });
        }

        if (! Schema::hasTable('manual_post_approval_fees')) {
            Schema::create('manual_post_approval_fees', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('loan_application_id')->constrained()->cascadeOnDelete();
                $table->string('description', 200);
                $table->decimal('partner_cost', 15, 2);
                $table->decimal('markup_percent', 8, 2)->default(0);
                $table->decimal('borrower_amount', 15, 2);
                $table->string('status', 30)->default('pending');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_post_approval_fees');
        Schema::dropIfExists('valuation_assignments');
        Schema::dropIfExists('loan_application_assets');
    }
};
