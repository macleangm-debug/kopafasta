<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('loan_application_post_approval_fees')) {
            Schema::table('loan_application_post_approval_fees', function (Blueprint $table): void {
                if (! Schema::hasColumn('loan_application_post_approval_fees', 'override_reason')) {
                    $table->string('override_reason')->nullable();
                }
                if (! Schema::hasColumn('loan_application_post_approval_fees', 'waived_at')) {
                    $table->timestamp('waived_at')->nullable();
                }
                if (! Schema::hasColumn('loan_application_post_approval_fees', 'waived_by')) {
                    $table->foreignId('waived_by')->nullable()->constrained('users')->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('repayments')) {
            Schema::table('repayments', function (Blueprint $table): void {
                if (! Schema::hasColumn('repayments', 'recorded_by')) {
                    $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('repayments', 'approved_by')) {
                    $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('repayments', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable();
                }
            });
        }

        if (Schema::hasTable('loan_applications')) {
            Schema::table('loan_applications', function (Blueprint $table): void {
                if (! Schema::hasColumn('loan_applications', 'funding_source')) {
                    $table->string('funding_source', 20)->nullable();
                }
                if (! Schema::hasColumn('loan_applications', 'preferred_lender_id')) {
                    $table->foreignId('preferred_lender_id')->nullable()->constrained('lenders')->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('lenders')) {
            Schema::table('lenders', function (Blueprint $table): void {
                if (! Schema::hasColumn('lenders', 'funding_source')) {
                    $table->string('funding_source', 20)->default('external');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('loan_application_post_approval_fees')) {
            Schema::table('loan_application_post_approval_fees', function (Blueprint $table): void {
                foreach (['override_reason', 'waived_at', 'waived_by'] as $col) {
                    if (Schema::hasColumn('loan_application_post_approval_fees', $col)) {
                        if ($col === 'waived_by') {
                            $table->dropConstrainedForeignId('waived_by');
                        } else {
                            $table->dropColumn($col);
                        }
                    }
                }
            });
        }

        if (Schema::hasTable('repayments')) {
            Schema::table('repayments', function (Blueprint $table): void {
                foreach (['recorded_by', 'approved_by', 'approved_at'] as $col) {
                    if (Schema::hasColumn('repayments', $col)) {
                        if (in_array($col, ['recorded_by', 'approved_by'], true)) {
                            $table->dropConstrainedForeignId($col);
                        } else {
                            $table->dropColumn($col);
                        }
                    }
                }
            });
        }

        if (Schema::hasTable('loan_applications')) {
            Schema::table('loan_applications', function (Blueprint $table): void {
                if (Schema::hasColumn('loan_applications', 'preferred_lender_id')) {
                    $table->dropConstrainedForeignId('preferred_lender_id');
                }
                if (Schema::hasColumn('loan_applications', 'funding_source')) {
                    $table->dropColumn('funding_source');
                }
            });
        }

        if (Schema::hasTable('lenders')) {
            Schema::table('lenders', function (Blueprint $table): void {
                if (Schema::hasColumn('lenders', 'funding_source')) {
                    $table->dropColumn('funding_source');
                }
            });
        }
    }
};
