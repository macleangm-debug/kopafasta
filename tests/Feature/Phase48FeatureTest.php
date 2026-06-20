<?php

namespace Tests\Feature;

use App\Models\FundingPool;
use App\Models\Lender;
use App\Models\Loan;
use App\Models\LoanCapitalAllocation;
use App\Models\LoanProduct;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\User;
use App\Services\CapitalPartnerAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase48FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_lender_form_accepts_external_kyc_and_revenue_share(): void
    {
        Setting::set('finance.capital_partner_interest_share_percent', 60);

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.lenders.store'), [
                'code'                  => 'EXT-P48',
                'name'                  => 'External Capital Ltd',
                'type'                  => 'institutional',
                'funding_source'        => 'external',
                'status'                => 'active',
                'revenue_share_percent' => 55,
                'registration_number'   => 'BR-123456',
                'tax_id'                => '123-456-789',
                'license_number'        => 'BOT-2024-001',
                'kyc_status'            => 'verified',
                'kyc_notes'             => 'Documents on file.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('lenders', [
            'code'                => 'EXT-P48',
            'funding_source'      => 'external',
            'revenue_share_percent' => 55,
            'registration_number' => 'BR-123456',
            'tax_id'              => '123-456-789',
            'license_number'      => 'BOT-2024-001',
            'kyc_status'          => 'verified',
        ]);
    }

    public function test_internal_lender_clears_kyc_fields_on_save(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $lender = Lender::create([
            'code'                => 'INT-P48',
            'name'                => 'Balance Sheet',
            'type'                => 'other',
            'funding_source'      => 'external',
            'status'              => 'active',
            'registration_number' => 'OLD-REG',
            'tax_id'              => 'OLD-TIN',
            'kyc_status'          => 'verified',
        ]);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.lenders.update', $lender), [
                'code'           => 'INT-P48',
                'name'           => 'Balance Sheet',
                'type'           => 'other',
                'funding_source' => 'internal',
                'status'         => 'active',
            ])
            ->assertRedirect();

        $lender->refresh();
        $this->assertSame('internal', $lender->funding_source);
        $this->assertNull($lender->registration_number);
        $this->assertNull($lender->tax_id);
        $this->assertNull($lender->kyc_status);
    }

    public function test_allocation_uses_partner_specific_revenue_share(): void
    {
        Setting::setMany([
            'finance.capital_partner_interest_share_percent' => 60,
            'finance.capital_allocation_strategy'            => 'manual',
        ]);

        $customer = Customer::create([
            'customer_number' => 'CU-P48-S',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Split',
            'last_name'       => 'Test',
            'phone'           => '255712345895',
        ]);

        $lender = Lender::create([
            'code'                  => 'P48-SPLIT',
            'name'                  => 'Custom Split Partner',
            'type'                  => 'bank',
            'funding_source'        => 'external',
            'status'                => 'active',
            'revenue_share_percent' => 70,
            'credit_limit'          => 5_000_000,
        ]);

        FundingPool::create([
            'lender_id'        => $lender->id,
            'name'             => 'Main pool',
            'amount_committed' => 5_000_000,
            'amount_deployed'  => 0,
            'status'           => 'open',
        ]);

        $product = LoanProduct::create([
            'code'                 => 'IL-P48',
            'name'                 => 'Individual',
            'is_active'            => true,
            'uses_capital_partner' => true,
            'interest_rate'        => 0.15,
            'min_amount'           => 100_000,
            'max_amount'           => 5_000_000,
            'tenure_min_months'    => 3,
            'tenure_max_months'    => 24,
        ]);

        $loan = Loan::create([
            'customer_id'         => $customer->id,
            'loan_product_id'     => $product->id,
            'loan_number'         => 'LN-P48-001',
            'principal_amount'    => 1_000_000,
            'approved_amount'     => 1_000_000,
            'outstanding_balance' => 1_000_000,
            'interest_rate'       => 0.15,
            'tenure_months'       => 12,
            'status'              => 'pending',
        ]);

        app(CapitalPartnerAllocationService::class)->allocateManually($loan, [
            ['lender_id' => $lender->id, 'amount' => 1_000_000],
        ]);

        $allocation = LoanCapitalAllocation::query()->where('loan_id', $loan->id)->firstOrFail();

        $this->assertEqualsWithDelta(70.0, (float) $allocation->partner_interest_share_percent, 0.001);
        $this->assertEqualsWithDelta(30.0, (float) $allocation->company_interest_share_percent, 0.001);
    }
}
