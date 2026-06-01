<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('partner_settlements')) {
            Schema::create('partner_settlements', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->string('reference', 40)->unique();
                $table->date('period_start')->nullable();
                $table->date('period_end')->nullable();
                $table->unsignedInteger('total_amount')->default(0);
                $table->string('status', 20)->default('pending');
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('paid_at')->nullable();
                $table->string('channel', 30)->nullable();
                $table->string('payment_reference', 60)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('vendor_payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('vendor_payments', 'partner_settlement_id')) {
                $table->foreignId('partner_settlement_id')->nullable()->after('vendor_task_id')->constrained('partner_settlements')->nullOnDelete();
            }
            if (! Schema::hasColumn('vendor_payments', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('vendor_payments', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('vendor_payments', 'source_type')) {
                $table->string('source_type', 40)->nullable()->after('approved_by');
            }
            if (! Schema::hasColumn('vendor_payments', 'source_id')) {
                $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            }
            if (! Schema::hasColumn('vendor_payments', 'description')) {
                $table->string('description')->nullable()->after('source_id');
            }
        });

        Schema::table('asset_reservations', function (Blueprint $table): void {
            if (! Schema::hasColumn('asset_reservations', 'reservation_fee_paid_at')) {
                $table->timestamp('reservation_fee_paid_at')->nullable()->after('reservation_fee_status');
            }
            if (! Schema::hasColumn('asset_reservations', 'reservation_payment_reference')) {
                $table->string('reservation_payment_reference', 60)->nullable()->after('reservation_fee_paid_at');
            }
            if (! Schema::hasColumn('asset_reservations', 'deposit_paid_at')) {
                $table->timestamp('deposit_paid_at')->nullable()->after('deposit_status');
            }
            if (! Schema::hasColumn('asset_reservations', 'deposit_payment_reference')) {
                $table->string('deposit_payment_reference', 60)->nullable()->after('deposit_paid_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('asset_reservations', function (Blueprint $table): void {
            foreach (['reservation_fee_paid_at', 'reservation_payment_reference', 'deposit_paid_at', 'deposit_payment_reference'] as $column) {
                if (Schema::hasColumn('asset_reservations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('vendor_payments', function (Blueprint $table): void {
            if (Schema::hasColumn('vendor_payments', 'partner_settlement_id')) {
                $table->dropConstrainedForeignId('partner_settlement_id');
            }
            if (Schema::hasColumn('vendor_payments', 'approved_by')) {
                $table->dropConstrainedForeignId('approved_by');
            }
            foreach (['approved_at', 'source_type', 'source_id', 'description'] as $column) {
                if (Schema::hasColumn('vendor_payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('partner_settlements');
    }
};
