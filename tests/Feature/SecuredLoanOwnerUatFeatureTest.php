<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerAsset;
use App\Models\CustomerDisbursementAccount;
use App\Models\CustomerPayment;
use App\Models\Disbursement;
use App\Models\LoanAgreement;
use App\Models\LoanApplication;
use App\Models\LoanApplicationAsset;
use App\Models\LoanProduct;
use App\Models\MarketplaceAsset;
use App\Models\Setting;
use App\Models\User;
use App\Services\ApplicationDisbursementReadinessService;
use App\Services\ApplicationOfferService;
use App\Services\AssetHandoverService;
use App\Services\AssetLendingService;
use App\Services\AssetReservationService;
use App\Services\CapitalPartnerAllocationService;
use App\Services\CollateralSecureService;
use App\Services\CustomerDisbursementDetailsService;
use App\Services\LoanAgreementService;
use App\Services\LoanDisbursementOrchestrator;
use App\Services\LoanOriginationService;
use App\Services\PostApprovalFeeService;
use App\Services\UnderwritingSettingsService;
use Database\Seeders\ChargesFeeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Owner UAT invariants for secured AB/AL — no comprehensive insurance before approval,
 * duration guard at Released, marketplace AL basis, contract date rules.
 */
class SecuredLoanOwnerUatFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChargesFeeSeeder::class);
        Setting::set('underwriting.insurance_expiry_buffer_months', 1);
        Setting::setMany([
            'partner_defaults.insurance.rate_percent' => 3.5,
            'partner_defaults.insurance.markup_percent' => 0,
            'partner_defaults.insurance.has_markup' => false,
        ]);
        // Owner UAT sequence: Contract before GPS/insurance/ownership security conditions.
        $catalog = collect(\App\Services\PostApprovalNextActionService::defaultCatalog())
            ->map(function (array $row) {
                if ($row['key'] === 'ownership_transfer') {
                    $row['timing'] = \App\Services\PostApprovalNextActionService::TIMING_BEFORE_DISBURSEMENT;
                }

                return $row;
            })
            ->values()
            ->all();
        Setting::set('underwriting.post_approval_conditions', $catalog);
    }

    public function test_ab_existing_insurance_satisfies_condition_and_short_cover_blocks_release(): void
    {
        [$admin, $app, $asset] = $this->makeApprovedAbWithVehicleInsurance(
            expiry: now()->addMonthsNoOverflow(8)->toDateString(),
        );

        $this->assertNull(
            CustomerPayment::query()
                ->where('payment_type', 'insurance_premium')
                ->where('customer_id', $app->customer_id)
                ->first(),
            'No comprehensive insurance payment before/at approval'
        );

        $this->drivePostApprovalToContract($admin, $app);

        $check = app(CollateralSecureService::class)->insuranceCheck($app->fresh(), $asset->fresh());
        $this->assertTrue($check['ok'], 'Valid existing comprehensive cover should satisfy condition');
        $this->assertSame([], app(ApplicationDisbursementReadinessService::class)
            ->comprehensiveInsuranceBlockingMessages($app->fresh()));

        // Insufficient duration vs tenure(3) + buffer(1) from release date.
        $asset->update([
            'metadata' => array_replace_recursive($asset->metadata ?? [], [
                'details' => [
                    'insurance_type' => 'comprehensive',
                    'insurance_expires_at' => now()->addMonthsNoOverflow(2)->toDateString(),
                    'insurance_policy_number' => 'UAT-SHORT',
                ],
                'insurance_document_path' => 'uat/insurance.pdf',
            ]),
        ]);

        $blocks = app(ApplicationDisbursementReadinessService::class)
            ->comprehensiveInsuranceBlockingMessages($app->fresh(), now());
        $this->assertNotEmpty($blocks);
        $this->assertStringContainsString('renewal', strtolower(implode(' ', $blocks)));

        $this->instance(
            CapitalPartnerAllocationService::class,
            \Mockery::mock(CapitalPartnerAllocationService::class, function ($mock) {
                $mock->shouldReceive('allocateForLoan')->andReturnNull();
            })
        );

        $loan = app(LoanOriginationService::class)->createFromApplication($app->fresh());
        try {
            app(LoanDisbursementOrchestrator::class)->disburse($loan->fresh(), $admin);
            $this->fail('Release must be blocked when insurance duration is insufficient');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('insurance', strtolower(implode(' ', $e->errors()['disburse'] ?? [''])));
        }

        $this->assertSame('pending', $loan->fresh()->status);
        $this->assertNull($loan->fresh()->disbursement_date);

        // Restore qualifying cover and release.
        $asset->update([
            'metadata' => array_replace_recursive($asset->metadata ?? [], [
                'details' => [
                    'insurance_type' => 'comprehensive',
                    'insurance_expires_at' => now()->addMonthsNoOverflow(8)->toDateString(),
                    'insurance_policy_number' => 'UAT-OK',
                ],
            ]),
        ]);

        $released = app(LoanDisbursementOrchestrator::class)->disburse($loan->fresh(), $admin);
        $this->assertSame('active', $released->status);
        $this->assertNotNull($released->disbursement_date);
        $this->assertTrue(
            $released->disbursements()->where('status', Disbursement::STATUS_RELEASED)->exists()
        );
        $this->assertNotNull($app->fresh()->disbursed_at);
    }

    public function test_al_insurance_uses_marketplace_price_and_payment_alone_does_not_satisfy(): void
    {
        $this->assertSame(1, app(UnderwritingSettingsService::class)->insuranceExpiryBufferMonths());

        [$admin, $app, $asset, $reservation] = $this->makeApprovedAlMarketplaceVehicle();

        $quote = app(AssetLendingService::class)->comprehensiveInsuranceQuote($asset);
        $this->assertSame((int) round((float) $asset->asset_value), $quote['insured_value']);
        $this->assertSame('marketplace_asset_value', $quote['basis']);
        $this->assertSame(350_000, $quote['premium']); // 10m × 3.5%
        $this->assertArrayHasKey('snapshotted_at', $quote);

        $this->drivePostApprovalToContract($admin, $app, isAl: true);
        app(AssetReservationService::class)->markDepositPaid($reservation->fresh(), 'UAT-DEP');

        // Payment alone must not make handover ready (still needs insurance_active / registration).
        $this->assertFalse(app(AssetReservationService::class)->handoverReady($reservation->fresh()));

        CustomerPayment::query()->create([
            'customer_id' => $app->customer_id,
            'payment_type' => 'insurance_premium',
            'payment_method' => 'mobile_money',
            'amount' => $quote['premium'],
            'status' => 'verified',
            'reference' => 'UAT-INS-'.random_int(1000, 9999),
            'provider_meta' => [
                'collateral_insurance' => [
                    'insured_value' => $quote['insured_value'],
                    'rate_percent' => $quote['rate_percent'],
                    'premium' => $quote['premium'],
                    'basis' => $quote['basis'],
                    'marketplace_asset_id' => $asset->id,
                    'snapshotted_at' => $quote['snapshotted_at'],
                ],
            ],
        ]);

        $this->assertFalse(
            app(AssetReservationService::class)->handoverReady($reservation->fresh()),
            'Verified insurance payment alone must not satisfy AL insurance condition'
        );

        app(AssetReservationService::class)->advance($reservation->fresh(), 'gps_installation');
        app(AssetReservationService::class)->advance($reservation->fresh(), 'insurance_active');
        app(AssetReservationService::class)->advance($reservation->fresh(), 'registration_complete');

        $this->assertTrue(app(AssetReservationService::class)->handoverReady($reservation->fresh()));

        $loan = app(LoanOriginationService::class)->createFromApplication($app->fresh());
        $handed = app(AssetHandoverService::class)->completeHandover($loan->fresh(), $admin);

        $this->assertSame('active', $handed->status);
        $this->assertNotNull($handed->disbursement_date);
    }

    public function test_pre_disbursement_contract_has_no_actual_disbursement_date(): void
    {
        [$admin, $app] = $this->makeApprovedAbWithVehicleInsurance(
            expiry: now()->addYear()->toDateString(),
        );
        $this->drivePostApprovalToContract($admin, $app);

        $contract = LoanAgreement::query()
            ->where('loan_application_id', $app->id)
            ->where('document_type', 'loan_contract')
            ->latest('id')
            ->first();

        $this->assertNotNull($contract);
        $this->assertTrue($contract->isSigned());
        $this->assertNull(data_get($contract->snapshot, 'disbursement_date'));
        $this->assertTrue((bool) data_get($contract->snapshot, 'schedule_is_estimate'));

        $pathBefore = $contract->file_path;
        $hashBefore = $contract->file_path && is_file(storage_path('app/'.$contract->file_path))
            ? md5_file(storage_path('app/'.$contract->file_path))
            : (string) $contract->updated_at;

        // Signing must not activate the loan.
        $loan = app(LoanOriginationService::class)->createFromApplication($app->fresh());
        $this->assertSame('pending', $loan->status);
        $this->assertNull($loan->disbursement_date);

        $contract->refresh();
        $this->assertSame($pathBefore, $contract->file_path);
        if ($contract->file_path && is_file(storage_path('app/'.$contract->file_path))) {
            $this->assertSame($hashBefore, md5_file(storage_path('app/'.$contract->file_path)));
        }
    }

    /** @return array{0: User, 1: LoanApplication, 2: CustomerAsset} */
    private function makeApprovedAbWithVehicleInsurance(string $expiry): array
    {
        $branch = Branch::create([
            'code' => 'UAT'.random_int(100, 999),
            'name' => 'UAT Branch',
            'region' => 'Dar',
            'is_active' => true,
        ]);
        $admin = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
            'is_active' => true,
            'pin_hash' => bcrypt('1234'),
        ]);
        $product = LoanProduct::query()->updateOrCreate(
            ['code' => 'AB'],
            [
                'name' => 'Asset-Backed Loan',
                'category' => 'asset',
                'is_active' => true,
                'interest_rate' => 0.15,
                'min_amount' => 500_000,
                'max_amount' => 100_000_000,
                'tenure_min_months' => 3,
                'tenure_max_months' => 60,
                'application_fee_amount' => 1_000,
                'requires_collateral' => true,
                'requires_guarantor' => false,
                'uses_capital_partner' => false,
            ]
        );

        $customer = Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower', 'pin_hash' => bcrypt('1234')])->id,
            'customer_number' => 'CU-UAT-AB-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'UatAb',
            'last_name' => 'Borrower',
            'phone' => '25571'.random_int(1000000, 9999999),
            'branch_id' => $branch->id,
            'monthly_income' => 3_000_000,
        ]);

        $asset = CustomerAsset::create([
            'customer_id' => $customer->id,
            'asset_type' => 'vehicle',
            'label' => 'UAT AB Vehicle',
            'is_active' => true,
            'metadata' => [
                'details' => [
                    'insurance_type' => 'comprehensive',
                    'insurance_expires_at' => $expiry,
                    'insurance_policy_number' => 'UAT-POL-1',
                    'make' => 'Toyota',
                    'model' => 'Hilux',
                ],
                'insurance_document_path' => 'uat/ab-insurance.pdf',
            ],
        ]);

        $app = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'branch_id' => $branch->id,
            'application_number' => 'APP-UAT-AB-'.strtoupper(bin2hex(random_bytes(2))),
            'requested_amount' => 2_000_000,
            'requested_tenure_months' => 3,
            'offered_amount' => 2_000_000,
            'offered_tenure_months' => 3,
            'status' => 'approved',
            'current_stage' => 'approval',
            'offer_status' => 'pending_borrower',
            'approved_at' => now(),
            'funding_source' => 'internal',
            'recommendation_type' => 'approve',
            'purpose' => 'STAGING UAT AB secured journey',
        ]);

        LoanApplicationAsset::create([
            'loan_application_id' => $app->id,
            'customer_asset_id' => $asset->id,
            'asset_type' => 'vehicle',
            'description' => 'UAT AB Vehicle',
            'market_value' => 8_000_000,
            'forced_sale_value' => 6_000_000,
            'ltv_percent' => 70,
            'max_loan_amount' => 4_200_000,
            'gps_required' => false,
            'uw_status' => LoanApplicationAsset::UW_ACCEPTED,
            'is_primary' => true,
        ]);

        return [$admin, $app, $asset];
    }

    /** @return array{0: User, 1: LoanApplication, 2: MarketplaceAsset, 3: \App\Models\AssetReservation} */
    private function makeApprovedAlMarketplaceVehicle(): array
    {
        $branch = Branch::create([
            'code' => 'UAL'.random_int(100, 999),
            'name' => 'UAT AL Branch',
            'region' => 'Dar',
            'is_active' => true,
        ]);
        $admin = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
            'is_active' => true,
            'pin_hash' => bcrypt('1234'),
        ]);
        $product = LoanProduct::query()->updateOrCreate(
            ['code' => 'AL'],
            [
                'name' => 'Asset Lending',
                'category' => 'asset',
                'is_active' => true,
                'interest_rate' => 0.155,
                'min_amount' => 500_000,
                'max_amount' => 100_000_000,
                'tenure_min_months' => 3,
                'tenure_max_months' => 60,
                'application_fee_amount' => 1_000,
                'requires_collateral' => true,
                'requires_guarantor' => false,
                'uses_capital_partner' => false,
            ]
        );

        $customer = Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower', 'pin_hash' => bcrypt('1234')])->id,
            'customer_number' => 'CU-UAT-AL-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'UatAl',
            'last_name' => 'Borrower',
            'phone' => '25576'.random_int(1000000, 9999999),
            'branch_id' => $branch->id,
            'monthly_income' => 4_000_000,
        ]);

        $asset = MarketplaceAsset::create([
            'slug' => 'uat-al-'.bin2hex(random_bytes(3)),
            'category' => 'vehicle',
            'title' => 'UAT AL Marketplace Vehicle',
            'description' => 'Staging UAT only',
            'supplier_name' => 'UAT Supplier',
            'asset_value' => 10_000_000,
            'supplier_deposit' => 2_000_000,
            'customer_deposit' => 2_200_000,
            'weekly_installment' => 150_000,
            'max_tenure_months' => 36,
            'is_active' => true,
            'availability_status' => 'available',
        ]);

        $reservation = app(AssetReservationService::class)->createReservation($customer, $asset);

        $app = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'branch_id' => $branch->id,
            'application_number' => 'APP-UAT-AL-'.strtoupper(bin2hex(random_bytes(2))),
            'requested_amount' => 10_000_000,
            'requested_tenure_months' => 12,
            'offered_amount' => 10_000_000,
            'offered_tenure_months' => 12,
            'status' => 'approved',
            'current_stage' => 'approval',
            'offer_status' => 'pending_borrower',
            'approved_at' => now(),
            'funding_source' => 'internal',
            'recommendation_type' => 'approve',
            'purpose' => 'STAGING UAT AL secured journey',
        ]);

        app(AssetReservationService::class)->linkApplication($reservation->fresh(), $app);

        return [$admin, $app, $asset, $reservation->fresh()];
    }

    private function drivePostApprovalToContract(User $admin, LoanApplication $app, bool $isAl = false): void
    {
        $agreements = app(LoanAgreementService::class);
        $offer = $agreements->generateOfferLetter($app->fresh());
        $agreements->acceptDirectly($offer);
        $app = $agreements->advanceAfterOfferAcceptance($app->fresh());

        app(PostApprovalFeeService::class)->generateForApplication($app->fresh());
        $fees = $app->fresh()->postApprovalFees;
        $insFee = $fees->first(fn ($f) => strtoupper((string) $f->code) === 'INS_FEE');
        if ($insFee) {
            $this->assertSame('percent', $insFee->fee_type);
            $this->assertEqualsWithDelta(1.0, (float) $insFee->amount, 0.001);
        }
        $this->assertFalse(
            $fees->contains(fn ($f) => str_contains(strtolower((string) $f->name), 'comprehensive')),
            'Comprehensive insurance must not be inside Post-Approval fee rows'
        );

        app(PostApprovalFeeService::class)->markAllPaid($app->fresh(), $app->customer);
        $agreements->ensureLoanContractAfterFees($app->fresh());

        if (! $isAl) {
            $customer = $app->fresh(['customer'])->customer;
            $accountName = $customer->legalDisplayName()
                ?: trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''));
            $account = CustomerDisbursementAccount::create([
                'customer_id' => $customer->id,
                'type' => 'mobile_money',
                'account_name' => $accountName,
                'mobile_provider' => 'mpesa',
                'mobile_number' => $customer->phone,
                'is_default' => true,
            ]);
            app(CustomerDisbursementDetailsService::class)->confirmForApplication(
                $app->fresh(),
                $customer->fresh(),
                $account,
            );
        }

        $contract = LoanAgreement::query()
            ->where('loan_application_id', $app->id)
            ->where('document_type', 'loan_contract')
            ->latest('id')
            ->first() ?? $agreements->generateLoanContract($app->fresh());

        $agreements->acceptDirectly($contract);
        $this->assertSame('accepted', $app->fresh()->offer_status);
        $this->assertNull(data_get($contract->fresh()->snapshot, 'disbursement_date'));
    }
}
