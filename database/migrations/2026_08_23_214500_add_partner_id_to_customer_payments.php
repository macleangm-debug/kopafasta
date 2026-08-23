<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_payments', 'partner_id')) {
                $table->unsignedBigInteger('partner_id')->nullable()->after('customer_id');
            }
        });

        $driver = Schema::getConnection()->getDriverName();

        try {
            Schema::table('customer_payments', function (Blueprint $table) {
                $table->dropForeign('cust_payments_customer_fk');
            });
        } catch (\Throwable) {
            try {
                Schema::table('customer_payments', function (Blueprint $table) {
                    $table->dropForeign(['customer_id']);
                });
            } catch (\Throwable) {
            }
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE customer_payments MODIFY customer_id BIGINT UNSIGNED NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE customer_payments ALTER COLUMN customer_id DROP NOT NULL');
        } else {
            Schema::table('customer_payments', function (Blueprint $table) {
                $table->unsignedBigInteger('customer_id')->nullable()->change();
            });
        }

        Schema::table('customer_payments', function (Blueprint $table) {
            try {
                $table->foreign('customer_id', 'cust_payments_customer_fk')->references('id')->on('customers')->nullOnDelete();
            } catch (\Throwable) {
            }
            if (Schema::hasColumn('customer_payments', 'partner_id')) {
                try {
                    $table->foreign('partner_id')->references('id')->on('partners')->nullOnDelete();
                } catch (\Throwable) {
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_payments', function (Blueprint $table) {
            try {
                $table->dropForeign(['partner_id']);
            } catch (\Throwable) {
            }
            if (Schema::hasColumn('customer_payments', 'partner_id')) {
                $table->dropColumn('partner_id');
            }
        });
    }
};
