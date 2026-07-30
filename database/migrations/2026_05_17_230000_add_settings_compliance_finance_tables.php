<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---------- Org structure ----------
        Schema::create('departments', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('code')->unique();
            $t->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $t->foreignId('head_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('description')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // ---------- Roles & approval limits ----------
        Schema::create('roles', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('code')->unique();
            $t->text('description')->nullable();
            $t->json('permissions')->nullable();
            $t->boolean('is_system')->default(false);
            $t->timestamps();
        });

        Schema::create('approval_limits', function (Blueprint $t): void {
            $t->id();
            $t->string('role_code');           // matches roles.code or users.role
            $t->string('action');              // e.g. loan_approve, write_off, restructure
            $t->decimal('min_amount', 16, 2)->default(0);
            $t->decimal('max_amount', 16, 2);
            $t->string('currency', 3)->default('TZS');
            $t->boolean('requires_dual_control')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index(['role_code', 'action']);
        });

        // ---------- Finance: Chart of Accounts & banking ----------
        Schema::create('chart_of_accounts', function (Blueprint $t): void {
            $t->id();
            $t->string('code', 20)->unique();
            $t->string('name');
            $t->enum('type', ['asset', 'liability', 'equity', 'income', 'expense']);
            $t->string('category')->nullable();        // current_asset / long_term, etc.
            $t->foreignId('parent_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $t->decimal('opening_balance', 16, 2)->default(0);
            $t->string('currency', 3)->default('TZS');
            $t->boolean('is_active')->default(true);
            $t->text('description')->nullable();
            $t->timestamps();
            $t->index(['type', 'is_active']);
        });

        Schema::create('bank_accounts', function (Blueprint $t): void {
            $t->id();
            $t->string('name');                        // e.g. CRDB Main Operating
            $t->string('bank_name');
            $t->string('account_number');
            $t->string('branch')->nullable();
            $t->string('swift_code')->nullable();
            $t->string('currency', 3)->default('TZS');
            $t->decimal('opening_balance', 16, 2)->default(0);
            $t->foreignId('gl_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $t->enum('purpose', ['operating', 'disbursement', 'collection', 'reserve', 'escrow'])->default('operating');
            $t->boolean('is_active')->default(true);
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['bank_name', 'account_number']);
        });

        Schema::create('mobile_money_accounts', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->enum('provider', ['m_pesa', 'tigo_pesa', 'airtel_money', 'halopesa', 'other']);
            $t->string('msisdn');
            $t->string('paybill_number')->nullable();
            $t->string('till_number')->nullable();
            $t->string('api_username')->nullable();
            $t->text('api_secret')->nullable();        // encrypt via cast
            $t->string('environment')->default('production');
            $t->decimal('opening_balance', 16, 2)->default(0);
            $t->foreignId('gl_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $t->enum('purpose', ['disbursement', 'collection', 'both'])->default('both');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('disbursement_methods', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('code')->unique();
            $t->enum('channel', ['bank_transfer', 'mobile_money', 'cash', 'cheque', 'wallet'])->default('bank_transfer');
            $t->decimal('fixed_fee', 12, 2)->default(0);
            $t->decimal('percentage_fee', 6, 4)->default(0);
            $t->integer('priority')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('repayment_methods', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('code')->unique();
            $t->enum('channel', ['bank_transfer', 'mobile_money', 'cash', 'cheque', 'standing_order', 'wallet'])->default('mobile_money');
            $t->decimal('fixed_fee', 12, 2)->default(0);
            $t->decimal('percentage_fee', 6, 4)->default(0);
            $t->boolean('auto_reconcile')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // ---------- Charges, fees, write-off rules ----------
        Schema::create('charges_fees', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('code')->unique();
            $t->enum('type', ['origination', 'processing', 'late_fee', 'penalty', 'insurance', 'gps', 'valuation', 'restructure', 'early_settlement', 'other']);
            $t->enum('basis', ['fixed', 'percentage', 'per_day', 'per_installment']);
            $t->decimal('amount', 16, 4);              // fixed amount or percent
            $t->decimal('min_amount', 16, 2)->nullable();
            $t->decimal('max_amount', 16, 2)->nullable();
            $t->enum('charge_when', ['application', 'post_approval', 'disbursement', 'repayment', 'late', 'event'])->default('disbursement');
            $t->foreignId('gl_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $t->boolean('is_active')->default(true);
            $t->text('description')->nullable();
            $t->timestamps();
        });

        Schema::create('write_off_rules', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->integer('days_past_due');              // e.g. 180
            $t->decimal('min_outstanding', 16, 2)->nullable();
            $t->decimal('max_outstanding', 16, 2)->nullable();
            $t->boolean('require_committee_approval')->default(true);
            $t->boolean('auto_propose')->default(false);
            $t->text('description')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // ---------- KYC / Risk / AML ----------
        Schema::create('risk_scoring_rules', function (Blueprint $t): void {
            $t->id();
            $t->string('factor');                      // e.g. age, income, employment
            $t->string('operator');                    // <, >, =, between, in
            $t->string('value');                       // operand or json range
            $t->integer('weight');                     // points awarded
            $t->enum('category', ['demographic', 'financial', 'behavioural', 'collateral', 'external'])->default('financial');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('blacklist_entries', function (Blueprint $t): void {
            $t->id();
            $t->enum('identifier_type', ['nida', 'phone', 'email', 'tin', 'passport', 'name']);
            $t->string('identifier_value');
            $t->string('reason');
            $t->enum('source', ['internal', 'crb', 'court', 'regulator', 'other'])->default('internal');
            $t->date('listed_on')->nullable();
            $t->date('expires_on')->nullable();
            $t->foreignId('added_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->boolean('is_active')->default(true);
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['identifier_type', 'identifier_value']);
        });

        Schema::create('pep_flags', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $t->string('full_name');
            $t->string('position')->nullable();
            $t->string('organization')->nullable();
            $t->enum('category', ['domestic', 'foreign', 'international_org', 'family', 'associate'])->default('domestic');
            $t->enum('risk_level', ['low', 'medium', 'high', 'extreme'])->default('high');
            $t->date('listed_on')->nullable();
            $t->boolean('is_active')->default(true);
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('aml_rules', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('code')->unique();
            $t->enum('rule_type', ['large_txn', 'velocity', 'structuring', 'repeated_early_settle', 'multi_account', 'geo', 'pattern']);
            $t->decimal('threshold_amount', 16, 2)->nullable();
            $t->integer('threshold_count')->nullable();
            $t->integer('window_days')->nullable();
            $t->enum('action', ['flag', 'block', 'review', 'report'])->default('flag');
            $t->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $t->boolean('is_active')->default(true);
            $t->text('description')->nullable();
            $t->timestamps();
        });

        Schema::create('suspicious_activities', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $t->foreignId('loan_id')->nullable()->constrained('loans')->nullOnDelete();
            $t->foreignId('aml_rule_id')->nullable()->constrained('aml_rules')->nullOnDelete();
            $t->string('activity_type');
            $t->decimal('amount', 16, 2)->nullable();
            $t->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $t->enum('status', ['open', 'investigating', 'cleared', 'reported', 'closed'])->default('open');
            $t->text('description');
            $t->text('investigator_notes')->nullable();
            $t->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('detected_at')->useCurrent();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'severity']);
        });

        // ---------- Customer risk additions ----------
        Schema::table('customers', function (Blueprint $t): void {
            if (! Schema::hasColumn('customers', 'risk_score')) {
                $t->integer('risk_score')->nullable();
            }
            if (! Schema::hasColumn('customers', 'risk_band')) {
                $t->enum('risk_band', ['low', 'medium', 'high', 'extreme'])->nullable();
            }
            if (! Schema::hasColumn('customers', 'is_pep')) {
                $t->boolean('is_pep')->default(false);
            }
            if (! Schema::hasColumn('customers', 'is_blacklisted')) {
                $t->boolean('is_blacklisted')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $t): void {
            foreach (['is_blacklisted', 'is_pep', 'risk_band', 'risk_score'] as $col) {
                if (Schema::hasColumn('customers', $col)) {
                    $t->dropColumn($col);
                }
            }
        });

        Schema::dropIfExists('suspicious_activities');
        Schema::dropIfExists('aml_rules');
        Schema::dropIfExists('pep_flags');
        Schema::dropIfExists('blacklist_entries');
        Schema::dropIfExists('risk_scoring_rules');
        Schema::dropIfExists('write_off_rules');
        Schema::dropIfExists('charges_fees');
        Schema::dropIfExists('repayment_methods');
        Schema::dropIfExists('disbursement_methods');
        Schema::dropIfExists('mobile_money_accounts');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('chart_of_accounts');
        Schema::dropIfExists('approval_limits');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('departments');
    }
};
