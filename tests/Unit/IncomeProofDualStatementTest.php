<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\DocumentType;
use App\Services\IncomeProofService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncomeProofDualStatementTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_keep_bank_and_mobile_statements_with_separate_account_details(): void
    {
        $customer = Customer::create([
            'customer_number' => 'CU-INC-DUAL-1',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Asha',
            'last_name' => 'Juma',
            'phone' => '255712349901',
            'activity_type' => 'trader',
            'activity_details' => [
                'income_proof_method' => 'bank_statement',
                'income_account_provider' => 'CRDB',
                'income_account_number' => '123',
                'income_statements' => [
                    'bank_statement' => [
                        'provider' => 'CRDB',
                        'number' => '123',
                        'name' => 'Asha Juma',
                    ],
                    'mobile_money_statement' => [
                        'provider' => 'M-Pesa',
                        'number' => '255700000000',
                        'name' => 'Asha Juma',
                    ],
                ],
            ],
        ]);

        $bankType = DocumentType::create([
            'code' => 'bank_statement',
            'name' => 'Bank statement',
            'is_active' => true,
        ]);
        $mobileType = DocumentType::create([
            'code' => 'mobile_money_statement',
            'name' => 'Mobile money statement',
            'is_active' => true,
        ]);

        CustomerDocument::create([
            'customer_id' => $customer->id,
            'document_type_id' => $bankType->id,
            'loan_application_id' => null,
            'file_path' => 'kyc/bank.pdf',
            'status' => 'pending_review',
        ]);
        CustomerDocument::create([
            'customer_id' => $customer->id,
            'document_type_id' => $mobileType->id,
            'loan_application_id' => null,
            'file_path' => 'kyc/mobile.pdf',
            'status' => 'pending_review',
        ]);

        $service = app(IncomeProofService::class);

        $this->assertSame(
            ['bank_statement', 'mobile_money_statement'],
            $service->presentPrimaryMethods($customer->fresh())
        );
        $this->assertTrue($service->hasPrimaryProof($customer->fresh()));
        $this->assertSame('CRDB', $service->statementAccountDetails($customer, 'bank_statement')['provider']);
        $this->assertSame('M-Pesa', $service->statementAccountDetails($customer, 'mobile_money_statement')['provider']);

        $checklist = collect($service->checklist($customer->fresh()))->where('group', 'primary');
        $this->assertTrue($checklist->every(fn (array $item) => ($item['visible'] ?? false) === true));
        $this->assertTrue($checklist->every(fn (array $item) => ($item['complete'] ?? false) === true));
    }
}
