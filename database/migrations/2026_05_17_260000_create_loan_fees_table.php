<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('loan_fees', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $t->foreignId('charges_fee_id')->nullable()->constrained('charges_fees')->nullOnDelete();
            $t->string('code');                                    // snapshot, e.g. ORIG_FEE
            $t->string('name');                                    // snapshot
            $t->string('type');                                    // origination / insurance / gps ...
            $t->string('basis');                                   // fixed / percentage / per_day / per_installment
            $t->decimal('rate_or_amount', 16, 4);                  // original config value
            $t->decimal('computed_amount', 16, 2);                 // final TZS amount applied
            $t->boolean('deducted_from_principal')->default(true); // true => netted off disbursement
            $t->string('status')->default('charged');              // charged / paid / waived / reversed
            $t->string('charge_when')->default('disbursement');    // when this fee fired
            $t->foreignId('gl_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $t->timestamp('charged_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();

            $t->index(['loan_id', 'status']);
            $t->index('code');
        });

        Schema::table('loans', function (Blueprint $t): void {
            if (!Schema::hasColumn('loans', 'fees_total')) {
                $t->decimal('fees_total', 16, 2)->default(0);
            }
            if (!Schema::hasColumn('loans', 'net_disbursed_amount')) {
                $t->decimal('net_disbursed_amount', 16, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_fees');
        Schema::table('loans', function (Blueprint $t): void {
            if (Schema::hasColumn('loans', 'net_disbursed_amount')) $t->dropColumn('net_disbursed_amount');
            if (Schema::hasColumn('loans', 'fees_total')) $t->dropColumn('fees_total');
        });
    }
};
