<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_payments', 'provider')) {
                $table->string('provider', 40)->nullable()->after('payment_method');
            }
            if (! Schema::hasColumn('customer_payments', 'provider_ref')) {
                $table->string('provider_ref', 100)->nullable()->after('provider');
                $table->index('provider_ref', 'cust_payments_provider_ref_idx');
            }
            if (! Schema::hasColumn('customer_payments', 'provider_meta')) {
                $table->json('provider_meta')->nullable()->after('provider_ref');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_payments', function (Blueprint $table) {
            if (Schema::hasColumn('customer_payments', 'provider_meta')) {
                $table->dropColumn('provider_meta');
            }
            if (Schema::hasColumn('customer_payments', 'provider_ref')) {
                $table->dropIndex('cust_payments_provider_ref_idx');
                $table->dropColumn('provider_ref');
            }
            if (Schema::hasColumn('customer_payments', 'provider')) {
                $table->dropColumn('provider');
            }
        });
    }
};
