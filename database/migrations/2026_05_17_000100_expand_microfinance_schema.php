<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone')->nullable();
            $table->string('role')->default('customer');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->decimal('approval_limit', 15, 2)->nullable();
            $table->boolean('is_active')->default(true);
        });

        Schema::table('branches', function (Blueprint $table): void {
            $table->string('code')->unique();
            $table->string('name');
            $table->string('region')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_number')->unique();
            $table->string('type')->default('individual');
            $table->string('status')->default('active');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('national_id')->nullable();
            $table->text('address')->nullable();
            $table->string('employment_type')->nullable();
            $table->string('business_name')->nullable();
            $table->decimal('monthly_income', 15, 2)->nullable();
            $table->date('onboarded_at')->nullable();
        });

        Schema::table('customer_kycs', function (Blueprint $table): void {
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->json('payload')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('document_types', function (Blueprint $table): void {
            $table->string('code')->unique();
            $table->string('name');
            $table->string('category')->default('kyc');
            $table->boolean('is_active')->default(true);
        });

        Schema::table('customer_documents', function (Blueprint $table): void {
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('file_path');
            $table->string('status')->default('pending_review');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
        });

        Schema::table('loan_products', function (Blueprint $table): void {
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->default('business_loan');
            $table->decimal('interest_rate', 8, 4);
            $table->unsignedInteger('tenure_min_months');
            $table->unsignedInteger('tenure_max_months');
            $table->decimal('min_amount', 15, 2);
            $table->decimal('max_amount', 15, 2);
            $table->boolean('requires_collateral')->default(false);
            $table->boolean('requires_guarantor')->default(false);
            $table->json('collateral_rules')->nullable();
            $table->foreignId('approval_workflow_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
        });

        Schema::table('loan_product_requirements', function (Blueprint $table): void {
            $table->foreignId('loan_product_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('document');
            $table->string('name');
            $table->boolean('is_required')->default(true);
            $table->text('description')->nullable();
        });

        Schema::table('approval_workflows', function (Blueprint $table): void {
            $table->string('name');
            $table->string('scope')->default('loan_application');
            $table->boolean('is_active')->default(true);
        });

        Schema::table('approval_steps', function (Blueprint $table): void {
            $table->foreignId('approval_workflow_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('step_order');
            $table->string('name');
            $table->string('role_required');
            $table->decimal('min_limit', 15, 2)->nullable();
            $table->decimal('max_limit', 15, 2)->nullable();
            $table->boolean('is_final')->default(false);
        });

        Schema::table('loan_applications', function (Blueprint $table): void {
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('application_number')->unique();
            $table->decimal('requested_amount', 15, 2);
            $table->unsignedInteger('requested_tenure_months');
            $table->decimal('recommended_amount', 15, 2)->nullable();
            $table->string('status')->default('submitted');
            $table->string('current_stage')->default('submitted');
            $table->text('purpose')->nullable();
            $table->json('screening_payload')->nullable();
            $table->json('credit_appraisal_payload')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('pre_approved_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('disbursed_at')->nullable();
        });

        Schema::table('application_stage_histories', function (Blueprint $table): void {
            $table->foreignId('loan_application_id')->constrained()->cascadeOnDelete();
            $table->string('from_stage')->nullable();
            $table->string('to_stage');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
        });

        Schema::table('guarantors', function (Blueprint $table): void {
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('national_id')->nullable();
            $table->text('address')->nullable();
            $table->string('relationship')->nullable();
        });

        Schema::table('customer_guarantors', function (Blueprint $table): void {
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guarantor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_application_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending');
        });

        Schema::table('credit_histories', function (Blueprint $table): void {
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('source')->default('internal');
            $table->integer('score')->nullable();
            $table->string('risk_grade')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('checked_at')->nullable();
        });

        Schema::table('communication_logs', function (Blueprint $table): void {
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel');
            $table->string('direction')->default('outbound');
            $table->string('subject')->nullable();
            $table->text('message');
            $table->timestamp('sent_at')->nullable();
        });

        Schema::table('vendors', function (Blueprint $table): void {
            $table->string('vendor_number')->unique();
            $table->string('name');
            $table->string('category');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
        });

        Schema::table('vendor_tasks', function (Blueprint $table): void {
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_application_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('task_type');
            $table->string('status')->default('assigned');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('proof_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('loans', function (Blueprint $table): void {
            $table->foreignId('loan_application_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_product_id')->constrained()->cascadeOnDelete();
            $table->string('loan_number')->unique();
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('interest_rate', 8, 4);
            $table->unsignedInteger('tenure_months');
            $table->decimal('approved_amount', 15, 2);
            $table->decimal('outstanding_balance', 15, 2)->default(0);
            $table->string('status')->default('approved');
            $table->date('disbursement_date')->nullable();
            $table->date('maturity_date')->nullable();
            $table->date('next_due_date')->nullable();
            $table->timestamp('closed_at')->nullable();
        });

        Schema::table('disbursements', function (Blueprint $table): void {
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->string('channel');
            $table->decimal('amount', 15, 2);
            $table->string('status')->default('pending');
            $table->timestamp('released_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
        });

        Schema::table('disbursement_recipients', function (Blueprint $table): void {
            $table->foreignId('disbursement_id')->constrained()->cascadeOnDelete();
            $table->string('recipient_type');
            $table->unsignedBigInteger('recipient_id')->nullable();
            $table->string('account_reference')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('status')->default('pending');
        });

        Schema::table('repayment_schedules', function (Blueprint $table): void {
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('installment_no');
            $table->date('due_date');
            $table->decimal('principal_due', 15, 2);
            $table->decimal('interest_due', 15, 2);
            $table->decimal('total_due', 15, 2);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->string('status')->default('pending');
            $table->timestamp('paid_at')->nullable();
        });

        Schema::table('repayments', function (Blueprint $table): void {
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('repayment_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->unique();
            $table->string('channel');
            $table->decimal('amount', 15, 2);
            $table->decimal('principal_component', 15, 2)->default(0);
            $table->decimal('interest_component', 15, 2)->default(0);
            $table->decimal('penalty_component', 15, 2)->default(0);
            $table->string('status')->default('received');
            $table->timestamp('paid_at')->nullable();
        });

        Schema::table('arrear_cases', function (Blueprint $table): void {
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('repayment_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('days_past_due')->default(0);
            $table->decimal('amount_in_arrears', 15, 2)->default(0);
            $table->decimal('penalty_amount', 15, 2)->default(0);
            $table->string('status')->default('open');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_follow_up_at')->nullable();
        });

        Schema::table('collection_actions', function (Blueprint $table): void {
            $table->foreignId('arrear_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_type');
            $table->text('notes')->nullable();
            $table->string('result')->nullable();
            $table->timestamp('performed_at')->nullable();
        });

        Schema::table('restructure_requests', function (Blueprint $table): void {
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->string('reason');
            $table->unsignedInteger('new_tenure_months')->nullable();
            $table->decimal('new_interest_rate', 8, 4)->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('decision_notes')->nullable();
        });

        Schema::table('notification_logs', function (Blueprint $table): void {
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel');
            $table->string('template')->nullable();
            $table->string('recipient');
            $table->text('message');
            $table->string('status')->default('queued');
            $table->timestamp('sent_at')->nullable();
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event');
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
        });

        Schema::create('system_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('document_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->longText('content');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('channel');
            $table->text('subject')->nullable();
            $table->longText('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('document_templates');
        Schema::dropIfExists('system_settings');

        $tables = [
            'audit_logs',
            'notification_logs',
            'restructure_requests',
            'collection_actions',
            'arrear_cases',
            'repayments',
            'repayment_schedules',
            'disbursement_recipients',
            'disbursements',
            'loans',
            'vendor_tasks',
            'vendors',
            'communication_logs',
            'credit_histories',
            'customer_guarantors',
            'guarantors',
            'application_stage_histories',
            'loan_applications',
            'approval_steps',
            'approval_workflows',
            'loan_product_requirements',
            'loan_products',
            'customer_documents',
            'document_types',
            'customer_kycs',
            'customers',
            'branches',
            'users',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table): void {
                // No-op rollback for added columns to keep starter migration simple.
            });
        }
    }
};
