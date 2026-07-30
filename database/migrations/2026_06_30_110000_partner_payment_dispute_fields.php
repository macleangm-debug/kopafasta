<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('partner_payments')) {
            return;
        }

        Schema::table('partner_payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('partner_payments', 'disputed_at')) {
                $table->timestamp('disputed_at')->nullable();
            }
            if (! Schema::hasColumn('partner_payments', 'dispute_reason')) {
                $table->text('dispute_reason')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('partner_payments')) {
            return;
        }

        Schema::table('partner_payments', function (Blueprint $table): void {
            if (Schema::hasColumn('partner_payments', 'dispute_reason')) {
                $table->dropColumn('dispute_reason');
            }
            if (Schema::hasColumn('partner_payments', 'disputed_at')) {
                $table->dropColumn('disputed_at');
            }
        });
    }
};
