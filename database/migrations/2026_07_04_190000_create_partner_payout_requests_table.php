<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('partner_payout_requests')) {
            return;
        }

        Schema::create('partner_payout_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->string('source_type', 64);
            $table->decimal('amount', 14, 2);
            $table->string('status', 32)->default('pending');
            $table->text('notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['partner_id', 'source_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_payout_requests');
    }
};
