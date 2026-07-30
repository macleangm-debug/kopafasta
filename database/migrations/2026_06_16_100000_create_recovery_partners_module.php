<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('vendors', 'recovery_commission_percent')) {
            Schema::table('vendors', function (Blueprint $table): void {
                $table->decimal('recovery_commission_percent', 8, 2)->nullable();
                $table->decimal('recovery_markup_percent', 8, 2)->nullable();
            });
        }

        if (Schema::hasTable('recovery_assignments')) {
            return;
        }

        Schema::create('recovery_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('arrear_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('partner_type', 40);
            $table->string('status', 30)->default('assigned');
            $table->decimal('original_outstanding', 15, 2);
            $table->decimal('commission_percent', 8, 2)->default(0);
            $table->decimal('company_markup_percent', 8, 2)->default(0);
            $table->decimal('recovery_charge', 15, 2)->default(0);
            $table->decimal('commission_earned', 15, 2)->default(0);
            $table->decimal('commission_paid', 15, 2)->default(0);
            $table->timestamp('sla_due_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('outcome', 80)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('vendor_task_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
            $table->index(['arrear_case_id', 'partner_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recovery_assignments');

        if (Schema::hasColumn('vendors', 'recovery_commission_percent')) {
            Schema::table('vendors', function (Blueprint $table): void {
                $table->dropColumn(['recovery_commission_percent', 'recovery_markup_percent']);
            });
        }
    }
};
