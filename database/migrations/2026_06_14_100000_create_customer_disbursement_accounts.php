<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_disbursement_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('account_name', 120);
            $table->string('mobile_provider', 20)->nullable();
            $table->string('mobile_number', 20)->nullable();
            $table->string('bank_name', 120)->nullable();
            $table->string('account_number', 40)->nullable();
            $table->string('bank_branch', 120)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['customer_id', 'type']);
        });

        Schema::table('loan_applications', function (Blueprint $table) {
            $table->foreignId('disbursement_account_id')
                ->nullable()
                ->after('disbursement_details_snapshot')
                ->constrained('customer_disbursement_accounts')
                ->nullOnDelete();
            $table->string('borrower_current_action', 60)->nullable()->after('disbursement_account_id');
            $table->json('borrower_completed_steps')->nullable()->after('borrower_current_action');
        });

        if (Schema::hasTable('customers')) {
            $customers = DB::table('customers')
                ->whereNotNull('preferred_disbursement_method')
                ->get(['id', 'preferred_disbursement_method', 'disbursement_mobile_provider', 'disbursement_mobile_number',
                    'disbursement_mobile_account_name', 'disbursement_bank_name', 'disbursement_bank_account_name',
                    'disbursement_bank_account_number', 'disbursement_bank_branch']);

            foreach ($customers as $customer) {
                $method = (string) $customer->preferred_disbursement_method;
                if ($method === 'mobile_money'
                    && filled($customer->disbursement_mobile_number)
                    && filled($customer->disbursement_mobile_account_name)) {
                    DB::table('customer_disbursement_accounts')->insert([
                        'customer_id'      => $customer->id,
                        'type'             => 'mobile_money',
                        'account_name'     => $customer->disbursement_mobile_account_name,
                        'mobile_provider'  => $customer->disbursement_mobile_provider,
                        'mobile_number'    => $customer->disbursement_mobile_number,
                        'is_default'       => true,
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ]);
                } elseif ($method === 'bank'
                    && filled($customer->disbursement_bank_account_number)
                    && filled($customer->disbursement_bank_account_name)) {
                    DB::table('customer_disbursement_accounts')->insert([
                        'customer_id'    => $customer->id,
                        'type'           => 'bank',
                        'account_name'   => $customer->disbursement_bank_account_name,
                        'bank_name'      => $customer->disbursement_bank_name,
                        'account_number' => $customer->disbursement_bank_account_number,
                        'bank_branch'    => $customer->disbursement_bank_branch,
                        'is_default'     => true,
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('disbursement_account_id');
            $table->dropColumn(['borrower_current_action', 'borrower_completed_steps']);
        });

        Schema::dropIfExists('customer_disbursement_accounts');
    }
};
