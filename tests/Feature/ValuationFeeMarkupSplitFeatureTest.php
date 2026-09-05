<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\JournalEntryLine;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Services\CustomerPaymentService;
use App\Services\PartnerDefaultsService;
use App\Services\ValuationPartnerService;
use App\Services\ValuationPricingService;
use Database\Seeders\DefaultChartOfAccountsSeeder;
use Database\Seeders\FinanceDefaultsSeeder;
use Database\Seeders\ValuationPricingDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CompletesPartnerJobs;
use Tests\TestCase;

class ValuationFeeMarkupSplitFeatureTest extends TestCase
{
    use CompletesPartnerJobs;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DefaultChartOfAccountsSeeder::class);
        $this->seed(FinanceDefaultsSeeder::class);
        $this->seed(ValuationPricingDefaultsSeeder::class);
    }

    public function test_staging_valuation_payable_is_exact_whole_tzs_10000(): void
    {
        Setting::setMany([
            'partner_defaults.valuer.base_cost' => 9091,
            'partner_defaults.valuer.has_markup' => true,
            'partner_defaults.valuer.markup_percent' => 10,
            'partner_defaults.valuer.borrower_amount' => 10_000,
        ]);

        $quote = app(ValuationPricingService::class)->quote();

        $this->assertSame(10_000, $quote['borrower_amount']);
        $this->assertSame(9_091, $quote['partner_share']);
        $this->assertSame(909, $quote['markup_amount']);
        $this->assertSame(10_000, $quote['partner_share'] + $quote['markup_amount']);
        $this->assertSame(10_000, quoted_valuation_fee(null));
        $this->assertIsInt($quote['borrower_amount']);
    }

    public function test_defaults_are_base_1000_per_asset_with_10_percent_markup(): void
    {
        $defaults = app(PartnerDefaultsService::class);
        $this->assertSame(1000.0, $defaults->valuerBaseCost());
        $this->assertSame(10.0, $defaults->valuerMarkupPercent());

        $quote = app(ValuationPricingService::class)->quote();
        $this->assertSame(1000, $quote['partner_share']);
        $this->assertSame(100, $quote['markup_amount']);
        $this->assertSame(1100, $quote['borrower_amount']);
        $this->assertSame(1100, quoted_valuation_fee(null));
        $this->assertSame(2200, quoted_valuation_fee(null, 2));
    }

    public function test_valuation_payment_ledger_splits_markup_and_partner_payable(): void
    {
        $customer = Customer::create([
            'customer_number' => 'CU-VAL-SPLIT',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Val',
            'last_name' => 'Split',
            'phone' => '255700000001',
        ]);

        $payment = CustomerPayment::create([
            'reference' => 'VAL-SPLIT-1',
            'customer_id' => $customer->id,
            'payment_type' => 'valuation_fee',
            'payment_method' => 'mobile_money',
            'amount' => 1100,
            'currency' => 'TZS',
            'status' => 'verified',
            'paid_at' => now(),
            'verified_at' => now(),
            'provider_meta' => [
                'fee_split' => [
                    'partner_share' => 1000,
                    'markup_amount' => 100,
                    'markup_percent' => 10,
                    'borrower_amount' => 1100,
                ],
            ],
        ]);

        $entry = app(CustomerPaymentService::class)->postLedger($payment->fresh());
        $this->assertNotNull($entry);

        $revenueId = (int) Setting::get('finance.valuation_revenue_gl_account_id');
        $payableId = (int) Setting::get('finance.recovery_partner_payable_gl_account_id');

        $credits = JournalEntryLine::query()
            ->where('journal_entry_id', $entry->id)
            ->where('credit', '>', 0)
            ->get()
            ->keyBy('chart_of_account_id');

        $this->assertSame(100.0, (float) $credits[$revenueId]->credit);
        $this->assertSame(1000.0, (float) $credits[$payableId]->credit);
    }

    public function test_valuation_complete_accrues_partner_base_only(): void
    {
        $branch = Branch::create([
            'code' => 'VS1',
            'name' => 'Val Split Branch',
            'region' => 'Dar',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'branch_id' => $branch->id,
            'customer_number' => 'CU-VAL-ACC',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Acc',
            'last_name' => 'Rue',
            'phone' => '255700000002',
            'region' => 'Dar',
        ]);

        $product = LoanProduct::create([
            'code' => 'AB',
            'name' => 'Asset Backed',
            'is_active' => true,
            'interest_rate' => 3.5,
            'min_amount' => 100_000,
            'max_amount' => 10_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);

        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'branch_id' => $branch->id,
            'application_number' => 'APP-VAL-ACC',
            'requested_amount' => 1_000_000,
            'requested_tenure_months' => 12,
            'status' => 'under_review',
            'current_stage' => 'screening',
        ]);

        $valuer = $this->completePartnerForJobs(Vendor::create([
            'vendor_number' => 'VAL-ACC-1',
            'name' => 'Test Valuer',
            'category' => 'valuer',
            'status' => 'active',
            'coverage_type' => 'nationwide',
            'regions' => ['Dar'],
        ]));

        $actor = User::factory()->create(['role' => 'admin']);
        $service = app(ValuationPartnerService::class);
        $assignment = $service->assign($application, $valuer, $actor);
        $this->assertSame(1000, (int) $assignment->vendorTask->fee_amount);

        $service->complete($assignment->fresh(), 5_000_000, 4_000_000, 'Done');

        $wallet = VendorPayment::query()
            ->where('partner_task_id', $assignment->vendor_task_id)
            ->where('source_type', 'valuation_fee')
            ->first();

        $this->assertNotNull($wallet);
        $this->assertSame(1000, (int) $wallet->amount);
    }
}
