<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\User;
use App\Services\CustomerPaymentService;
use App\Services\PaymentAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultCollectionBankAccountFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_bank_payments_use_configured_collection_bank_account(): void
    {
        $branch = Branch::create([
            'code' => 'TCB'.random_int(10, 99),
            'name' => 'TCB Branch',
            'region' => 'Dar',
            'is_active' => true,
        ]);
        $admin = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        $customer = Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-TCB-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Bank',
            'last_name' => 'Payer',
            'phone' => '25571'.random_int(1000000, 9999999),
            'branch_id' => $branch->id,
        ]);

        $tcb = BankAccount::create([
            'name' => 'Kopafasta Collection',
            'bank_name' => 'TCB',
            'account_number' => '0123456789',
            'purpose' => 'collection',
            'is_active' => true,
        ]);
        BankAccount::create([
            'name' => 'Other Bank',
            'bank_name' => 'CRDB',
            'account_number' => '999999',
            'purpose' => 'collection',
            'is_active' => true,
        ]);

        Setting::set('payments.default_collection_bank_account_id', $tcb->id);

        $accounts = app(PaymentAccountService::class);
        $this->assertSame($tcb->id, $accounts->defaultCollectionBankAccount()?->id);
        $this->assertSame($tcb->id, $accounts->resolveBankAccount('application_fee')?->id);

        $payment = app(CustomerPaymentService::class)->create([
            'customer' => $customer,
            'payment_type' => 'application_fee',
            'payment_method' => 'bank_transfer',
            'amount' => 3_500_000,
        ]);

        $this->assertSame('bank_transfer', $payment->payment_method);
        $this->assertSame('pending_verification', $payment->status);
        $this->assertSame($tcb->id, (int) $payment->bank_account_id);
        $this->assertStringContainsString('0123456789', (string) $payment->payment_instructions);
        $this->assertStringContainsString('Upload proof of payment', (string) $payment->payment_instructions);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.settings.payment-accounts.default-collection-bank'), [
                'default_collection_bank_account_id' => $tcb->id,
                'apply_to_all_bank_mappings' => '1',
            ])
            ->assertRedirect();

        $this->assertSame((string) $tcb->id, (string) Setting::get('payments.default_collection_bank_account_id'));

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.payments.show', $payment))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('TCB', $html);
        $this->assertStringContainsString('0123456789', $html);
    }
}
