<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lenders', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type')->default('institution'); // institution, individual, fund
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->decimal('credit_limit', 18, 2)->default(0);
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('funding_pools', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lender_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('currency', 3)->default('TZS');
            $table->decimal('amount_committed', 18, 2);
            $table->decimal('amount_deployed', 18, 2)->default(0);
            $table->decimal('expected_yield', 8, 4)->default(0); // percent
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->default('open'); // open, deployed, closed
            $table->timestamps();
        });

        Schema::create('lender_investments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lender_id')->constrained()->cascadeOnDelete();
            $table->foreignId('funding_pool_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->unique();
            $table->decimal('principal', 18, 2);
            $table->decimal('return_amount', 18, 2)->default(0);
            $table->decimal('return_rate', 8, 4)->default(0);
            $table->date('invested_at')->nullable();
            $table->date('matures_at')->nullable();
            $table->string('status')->default('active'); // active, matured, closed
            $table->timestamps();
        });

        Schema::create('lender_statements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lender_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->decimal('investments_total', 18, 2)->default(0);
            $table->decimal('returns_total', 18, 2)->default(0);
            $table->decimal('withdrawals_total', 18, 2)->default(0);
            $table->decimal('closing_balance', 18, 2)->default(0);
            $table->string('file_path')->nullable();
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category'); // rent, salaries, utilities, marketing, legal, gps, insurance, other
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('TZS');
            $table->date('expense_date');
            $table->string('payment_method')->default('bank'); // bank, cash, mobile
            $table->string('reference')->nullable();
            $table->string('status')->default('recorded'); // recorded, approved, paid
            $table->timestamps();
        });

        Schema::create('settlements', function (Blueprint $table): void {
            $table->id();
            $table->string('reference')->unique();
            $table->string('partner'); // mpesa, tigopesa, airtel, bank
            $table->date('settlement_date');
            $table->decimal('gross_amount', 18, 2);
            $table->decimal('fees', 15, 2)->default(0);
            $table->decimal('net_amount', 18, 2);
            $table->unsignedInteger('transactions_count')->default(0);
            $table->string('status')->default('pending'); // pending, reconciled, disputed
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('settlement_id')->nullable()->constrained()->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('system_total', 18, 2)->default(0);
            $table->decimal('bank_total', 18, 2)->default(0);
            $table->decimal('variance', 18, 2)->default(0);
            $table->string('status')->default('open'); // open, balanced, variance, closed
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reconciled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->string('ticket_number')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject');
            $table->text('description');
            $table->string('priority')->default('normal'); // low, normal, high, urgent
            $table->string('status')->default('open'); // open, in_progress, resolved, closed
            $table->string('category')->default('general'); // general, loan, payment, kyc, technical
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('complaints', function (Blueprint $table): void {
            $table->id();
            $table->string('complaint_number')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject');
            $table->text('description');
            $table->string('severity')->default('minor'); // minor, moderate, major, critical
            $table->string('status')->default('received'); // received, investigating, resolved, escalated
            $table->string('channel')->default('in_app'); // in_app, phone, email, walk_in
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('reconciliations');
        Schema::dropIfExists('settlements');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('lender_statements');
        Schema::dropIfExists('lender_investments');
        Schema::dropIfExists('funding_pools');
        Schema::dropIfExists('lenders');
    }
};
