<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            if (! Schema::hasColumn('partners', 'membership_status')) {
                $table->string('membership_status', 32)->nullable()->after('status');
            }
            if (! Schema::hasColumn('partners', 'membership_started_at')) {
                $table->timestamp('membership_started_at')->nullable()->after('membership_status');
            }
            if (! Schema::hasColumn('partners', 'membership_expires_at')) {
                $table->timestamp('membership_expires_at')->nullable()->after('membership_started_at');
            }
            if (! Schema::hasColumn('partners', 'membership_payment_due_at')) {
                $table->timestamp('membership_payment_due_at')->nullable()->after('membership_expires_at');
            }
            if (! Schema::hasColumn('partners', 'membership_payment_reference')) {
                $table->string('membership_payment_reference', 64)->nullable()->after('membership_payment_due_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            foreach ([
                'membership_status',
                'membership_started_at',
                'membership_expires_at',
                'membership_payment_due_at',
                'membership_payment_reference',
            ] as $column) {
                if (Schema::hasColumn('partners', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
