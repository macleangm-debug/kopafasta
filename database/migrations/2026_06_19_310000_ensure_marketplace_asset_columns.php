<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_assets')) {
            return;
        }

        Schema::table('marketplace_assets', function (Blueprint $table): void {
            if (! Schema::hasColumn('marketplace_assets', 'vendor_id')) {
                $table->foreignId('vendor_id')->nullable()->after('id')->constrained()->nullOnDelete();
            }
            foreach ([
                'serial_number'           => fn () => $table->string('serial_number', 80)->nullable(),
                'chassis_number'          => fn () => $table->string('chassis_number', 80)->nullable(),
                'engine_number'           => fn () => $table->string('engine_number', 80)->nullable(),
                'insurance_policy_number' => fn () => $table->string('insurance_policy_number', 80)->nullable(),
                'insurance_expires_at'    => fn () => $table->date('insurance_expires_at')->nullable(),
                'waiting_period_days'     => fn () => $table->unsignedSmallInteger('waiting_period_days')->nullable(),
                'availability_status'     => fn () => $table->string('availability_status', 20)->default('available'),
            ] as $column => $definition) {
                if (! Schema::hasColumn('marketplace_assets', $column)) {
                    $definition();
                }
            }
        });
    }

    public function down(): void
    {
        // Columns may predate this migration; leave schema unchanged on rollback.
    }
};
