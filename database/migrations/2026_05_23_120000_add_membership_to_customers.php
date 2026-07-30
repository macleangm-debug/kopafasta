<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $t): void {
            if (! Schema::hasColumn('customers', 'member_no')) {
                $t->string('member_no')->nullable()->unique();
            }
            if (! Schema::hasColumn('customers', 'membership_status')) {
                // active | expiring | expired | grace | archived
                $t->string('membership_status', 20)->nullable()->index();
            }
            if (! Schema::hasColumn('customers', 'membership_issued_at')) {
                $t->date('membership_issued_at')->nullable();
            }
            if (! Schema::hasColumn('customers', 'membership_expires_at')) {
                $t->date('membership_expires_at')->nullable()->index();
            }
            if (! Schema::hasColumn('customers', 'last_renewal_at')) {
                $t->date('last_renewal_at')->nullable();
            }
            if (! Schema::hasColumn('customers', 'renewal_count')) {
                $t->unsignedInteger('renewal_count')->default(0);
            }
            if (! Schema::hasColumn('customers', 'last_renewal_payment_ref')) {
                $t->string('last_renewal_payment_ref')->nullable();
            }
            if (! Schema::hasColumn('customers', 'reminders_sent')) {
                // tracks ['30','14','7','1','expired'] => date sent, to prevent double-sends
                $t->json('reminders_sent')->nullable();
            }
        });

        Schema::create('membership_histories', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            // issued | renewed | expired | archived | grace_started | restored
            $t->string('event', 30)->index();
            $t->date('issued_at')->nullable();
            $t->date('expires_at')->nullable();
            $t->date('previous_expires_at')->nullable();
            $t->unsignedInteger('renewal_count_after')->nullable();
            $t->decimal('fee_amount', 12, 2)->nullable();
            $t->string('payment_reference')->nullable();
            $t->string('channel', 30)->nullable(); // mobile_money, bank, cash, system
            $t->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['customer_id', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_histories');

        Schema::table('customers', function (Blueprint $t): void {
            foreach (['member_no','membership_status','membership_issued_at','membership_expires_at','last_renewal_at','renewal_count','last_renewal_payment_ref','reminders_sent'] as $c) {
                if (Schema::hasColumn('customers', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
    }
};
