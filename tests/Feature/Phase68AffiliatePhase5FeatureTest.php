<?php

namespace Tests\Feature;

use App\Models\AffiliateEvent;
use App\Models\Customer;
use App\Models\FundingPool;
use App\Models\Lender;
use App\Models\Loan;
use App\Models\LoanCapitalAllocation;
use App\Models\LoanProduct;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AffiliateCapitalAttributionReportService;
use App\Services\AffiliateDeviceFingerprintService;
use App\Services\AffiliateFraudDetectionService;
use App\Services\AffiliateMarketingAttributionReportService;
use App\Services\AffiliateLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase68AffiliatePhase5FeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function affiliate(array $overrides = []): Vendor
    {
        return Vendor::create(array_merge([
            'vendor_number'              => 'AFF-P68',
            'name'                       => 'Affiliate P68',
            'category'                   => 'affiliate',
            'status'                     => 'active',
            'phone'                      => '255712346680',
            'affiliate_code'             => 'AFFP68',
            'affiliate_lifecycle_status' => AffiliateLifecycleService::ACTIVE,
        ], $overrides));
    }

    public function test_device_fingerprint_is_stable_for_same_device(): void
    {
        $service = app(AffiliateDeviceFingerprintService::class);
        $first = $service->generate('Mozilla/5.0 iPhone', '192.168.1.25', 'en');
        $second = $service->generate('Mozilla/5.0 iPhone', '192.168.1.99', 'en');

        $this->assertSame($first, $second);
        $this->assertNotSame($first, $service->generate('Mozilla/5.0 Android', '192.168.1.25', 'en'));
    }

    public function test_self_referral_detection_blocks_affiliate(): void
    {
        $affiliate = $this->affiliate(['phone' => '255712346681']);

        Customer::create([
            'customer_number'      => 'CU-P68-SR',
            'type'                 => 'individual',
            'status'               => 'active',
            'first_name'           => 'Self',
            'last_name'            => 'Referrer',
            'phone'                => '255712346681',
            'affiliate_partner_id' => $affiliate->id,
        ]);

        $result = app(AffiliateFraudDetectionService::class)->scanAndPersist($affiliate);

        $this->assertSame(AffiliateFraudDetectionService::FLAG_BLOCKED, $result['risk_flag']);
        $this->assertNull(app(\App\Services\AffiliateService::class)->findByCode('AFFP68'));
    }

    public function test_marketing_attribution_report_groups_utm_sources(): void
    {
        $affiliate = $this->affiliate();

        AffiliateEvent::create([
            'vendor_id'   => $affiliate->id,
            'event_type'  => 'click',
            'utm_source'  => 'facebook',
            'utm_campaign'=> 'launch',
        ]);
        AffiliateEvent::create([
            'vendor_id'  => $affiliate->id,
            'event_type' => 'registration',
            'utm_source' => 'facebook',
        ]);

        $report = app(AffiliateMarketingAttributionReportService::class)->report();

        $this->assertSame(1, $report['totals']['clicks']);
        $this->assertSame(1, $report['totals']['registrations']);
        $this->assertSame(2, $report['by_source']['facebook'] ?? 0);
    }

    public function test_capital_attribution_links_affiliate_to_lender(): void
    {
        $affiliate = $this->affiliate(['vendor_number' => 'AFF-P68-C']);

        $customer = Customer::create([
            'customer_number'      => 'CU-P68-C',
            'type'                 => 'individual',
            'status'               => 'active',
            'first_name'           => 'Capital',
            'last_name'            => 'Borrower',
            'phone'                => '255712346682',
            'affiliate_partner_id' => $affiliate->id,
        ]);

        $lender = Lender::create([
            'code'           => 'P68-LEND',
            'name'           => 'Capital Bank',
            'type'           => 'bank',
            'funding_source' => 'external',
            'status'         => 'active',
            'credit_limit'   => 5_000_000,
        ]);

        $product = LoanProduct::create([
            'code'                 => 'IL-P68',
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
            'loan_number'         => 'LN-P68-C',
            'principal_amount'    => 1_000_000,
            'approved_amount'     => 1_000_000,
            'outstanding_balance' => 900_000,
            'interest_rate'       => 0.15,
            'tenure_months'       => 12,
            'status'              => 'active',
        ]);

        LoanCapitalAllocation::create([
            'loan_id'             => $loan->id,
            'lender_id'           => $lender->id,
            'allocated_principal' => 1_000_000,
            'allocation_percent'  => 100,
            'outstanding_exposure'=> 900_000,
        ]);

        $report = app(AffiliateCapitalAttributionReportService::class)->report();

        $this->assertSame(1, $report['totals']['loans']);
        $this->assertSame('Affiliate P68', $report['rows'][0]['affiliate_name']);
        $this->assertSame('Capital Bank', $report['rows'][0]['lender_name']);
        $this->assertEqualsWithDelta(1_000_000, $report['rows'][0]['allocated_principal'], 0.01);
    }

    public function test_admin_marketing_attribution_report_page_loads(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.reports.affiliate-marketing-attribution'))
            ->assertOk()
            ->assertSee('Marketing attribution', false);
    }

    public function test_fraud_scan_command_runs(): void
    {
        $this->affiliate();

        $this->artisan('affiliate:scan-fraud --dry-run')
            ->assertSuccessful();
    }
}
