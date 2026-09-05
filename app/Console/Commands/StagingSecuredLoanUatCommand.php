<?php

namespace App\Console\Commands;

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
use App\Services\AssetHandoverService;
use App\Services\AssetLendingService;
use App\Services\AssetReservationService;
use App\Services\CollateralSecureService;
use App\Services\CustomerDisbursementDetailsService;
use App\Services\LoanAgreementService;
use App\Services\LoanDisbursementOrchestrator;
use App\Services\LoanOriginationService;
use App\Services\PostApprovalFeePaymentService;
use App\Services\PostApprovalFeeService;
use App\Services\PostApprovalNextActionService;
use App\Services\Staging\StagingPaymentSimulator;
use App\Services\Staging\StagingPaymentsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Staging-only secured AB/AL owner UAT harness. Creates clearly marked test data
 * and drives the real service path (including staging payment.show simulator).
 */
class StagingSecuredLoanUatCommand extends Command
{
    protected $signature = 'staging:secured-loan-uat
                            {--skip-al : Skip AL journey}
                            {--skip-ab : Skip AB journey}';

    protected $description = 'Run secured AB/AL owner UAT journeys on staging (no production).';

    /** @var array<string, mixed> */
    private array $report = [];

    public function handle(): int
    {
        if (! app()->environment('staging') && ! app()->environment('local')) {
            $this->error('Refusing: only staging/local.');

            return self::FAILURE;
        }

        if (! app(StagingPaymentsService::class)->isSimulator()) {
            $this->error('Staging payment simulator must be enabled.');

            return self::FAILURE;
        }

        $this->alignOwnershipTimingForOwnerSequence();
        Setting::set('underwriting.insurance_expiry_buffer_months', 1);

        $historicalBefore = $this->historicalFingerprint();
        $maxHistoricalId = (int) (CustomerPayment::query()->max('id') ?? 0);
        $suffix = strtoupper(Str::random(4));

        try {
            if (! $this->option('skip-ab')) {
                $this->report['ab'] = $this->runAb($suffix);
            }
            if (! $this->option('skip-al')) {
                $this->report['al'] = $this->runAl($suffix);
            }
        } catch (\Throwable $e) {
            $this->report['fatal'] = $e->getMessage();
            $this->line(json_encode($this->report, JSON_PRETTY_PRINT));
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $historicalAfter = $this->historicalFingerprint($maxHistoricalId);
        $this->report['historical_safety'] = $historicalBefore === $historicalAfter ? 'PASS' : 'FAIL';
        $this->report['historical_before_count'] = count($historicalBefore);
        $this->report['historical_after_count'] = count($historicalAfter);
        $this->report['sha'] = trim((string) @file_get_contents(base_path('DEPLOYED_SHA')))
            ?: trim((string) shell_exec('git -C '.escapeshellarg(base_path()).' rev-parse HEAD 2>/dev/null'));

        $this->line(json_encode($this->report, JSON_PRETTY_PRINT));

        $ok = ($this->report['ab']['pass'] ?? true)
            && ($this->report['al']['pass'] ?? true)
            && ($this->report['historical_safety'] ?? '') === 'PASS';

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    /** @return array<string, mixed> */
    private function runAb(string $suffix): array
    {
        $out = ['journey' => 'AB', 'pass' => false, 'checks' => []];
        $branch = Branch::query()->where('is_active', true)->orderBy('id')->first();
        $admin = User::query()->where('role', 'admin')->where('is_active', true)->orderBy('id')->first();
        $product = LoanProduct::query()->where('code', 'AB')->firstOrFail();

        $customer = $this->makeCustomer($branch, 'Ab'.$suffix, 'UatBorrower', '25571');
        $asset = CustomerAsset::create([
            'customer_id' => $customer->id,
            'asset_type' => 'vehicle',
            'label' => 'STAGING-UAT AB Vehicle '.$suffix,
            'is_active' => true,
            'metadata' => [
                'details' => [
                    'insurance_type' => 'comprehensive',
                    'insurance_expires_at' => now()->addMonthsNoOverflow(8)->toDateString(),
                    'insurance_policy_number' => 'STG-UAT-'.$suffix,
                    'make' => 'Toyota',
                    'model' => 'UAT',
                ],
                'insurance_document_path' => 'staging-uat/ab-'.$suffix.'.pdf',
            ],
        ]);

        $app = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'branch_id' => $branch->id,
            'application_number' => 'APP-UAT-AB-'.$suffix,
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
            'purpose' => 'STAGING-ONLY UAT AB secured journey '.$suffix,
            'application_fee_amount' => 0,
            'application_fee_status' => 'waived',
        ]);

        LoanApplicationAsset::create([
            'loan_application_id' => $app->id,
            'customer_asset_id' => $asset->id,
            'asset_type' => 'vehicle',
            'description' => $asset->label,
            'market_value' => 8_000_000,
            'forced_sale_value' => 6_000_000,
            'ltv_percent' => 70,
            'max_loan_amount' => 4_200_000,
            'gps_required' => false,
            'uw_status' => LoanApplicationAsset::UW_ACCEPTED,
            'is_primary' => true,
        ]);

        $out['checks']['no_comp_insurance_before_approval'] = ! CustomerPayment::query()
            ->where('payment_type', 'insurance_premium')
            ->where('customer_id', $customer->id)
            ->exists();

        $bundle = $this->payPostApprovalBundle($app->fresh(), $customer);
        $out['checks']['post_approval_bundle'] = $bundle;
        $out['application'] = $app->application_number;

        $this->confirmDestinationAndSignContract($app->fresh(), $customer);
        $contract = LoanAgreement::query()
            ->where('loan_application_id', $app->id)
            ->where('document_type', 'loan_contract')
            ->where('status', 'signed')
            ->latest('id')
            ->first();
        $out['checks']['contract_no_disbursement_date'] = $contract
            && data_get($contract->snapshot, 'disbursement_date') === null
            && (bool) data_get($contract->snapshot, 'schedule_is_estimate', true);
        $signedPath = $contract?->file_path;
        $signedHash = ($signedPath && is_file(storage_path('app/'.$signedPath)))
            ? md5_file(storage_path('app/'.$signedPath))
            : null;

        $okInsurance = app(CollateralSecureService::class)->insuranceCheck($app->fresh(), $asset->fresh());
        $out['checks']['existing_insurance_satisfies'] = (bool) ($okInsurance['ok'] ?? false);
        $out['checks']['no_new_comp_insurance_payment'] = ! CustomerPayment::query()
            ->where('payment_type', 'insurance_premium')
            ->where('customer_id', $customer->id)
            ->exists();

        // Duration failure branch
        $asset->update([
            'metadata' => array_replace_recursive($asset->metadata ?? [], [
                'details' => [
                    'insurance_type' => 'comprehensive',
                    'insurance_expires_at' => now()->addMonthsNoOverflow(2)->toDateString(),
                    'insurance_policy_number' => 'STG-UAT-SHORT-'.$suffix,
                ],
            ]),
        ]);
        $blocks = app(ApplicationDisbursementReadinessService::class)
            ->comprehensiveInsuranceBlockingMessages($app->fresh(), now());
        $out['checks']['short_cover_blocks'] = $blocks !== [];

        $loan = app(LoanOriginationService::class)->createFromApplication($app->fresh());
        $blockedRelease = false;
        try {
            app(LoanDisbursementOrchestrator::class)->disburse($loan->fresh(), $admin);
        } catch (ValidationException $e) {
            $blockedRelease = true;
            $out['checks']['short_cover_release_exception'] = implode(' ', $e->errors()['disburse'] ?? []);
        }
        $out['checks']['short_cover_keeps_pending'] = $loan->fresh()->status === 'pending'
            && $loan->fresh()->disbursement_date === null
            && $blockedRelease;

        // Restore qualifying cover and release
        $asset->update([
            'metadata' => array_replace_recursive($asset->metadata ?? [], [
                'details' => [
                    'insurance_type' => 'comprehensive',
                    'insurance_expires_at' => now()->addMonthsNoOverflow(8)->toDateString(),
                    'insurance_policy_number' => 'STG-UAT-OK-'.$suffix,
                ],
            ]),
        ]);

        $released = app(LoanDisbursementOrchestrator::class)->disburse($loan->fresh(), $admin);
        $out['checks']['released_active'] = $released->status === 'active'
            && $released->disbursement_date !== null
            && $released->disbursements()->where('status', Disbursement::STATUS_RELEASED)->exists()
            && $app->fresh()->disbursed_at !== null;

        $contract->refresh();
        $out['checks']['signed_pdf_not_mutated'] = $signedPath === $contract->file_path
            && ($signedHash === null || (
                is_file(storage_path('app/'.$contract->file_path))
                && md5_file(storage_path('app/'.$contract->file_path)) === $signedHash
            ));

        $out['pass'] = ! in_array(false, $out['checks'], true)
            && ($bundle['pass'] ?? false);

        return $out;
    }

    /** @return array<string, mixed> */
    private function runAl(string $suffix): array
    {
        $out = ['journey' => 'AL', 'pass' => false, 'checks' => []];
        $branch = Branch::query()->where('is_active', true)->orderBy('id')->first();
        $admin = User::query()->where('role', 'admin')->where('is_active', true)->orderBy('id')->first();
        $product = LoanProduct::query()->where('code', 'AL')->firstOrFail();

        $customer = $this->makeCustomer($branch, 'Al'.$suffix, 'UatBorrower', '25576');
        $market = MarketplaceAsset::query()
            ->where('category', 'vehicle')
            ->where('is_active', true)
            ->where('availability_status', 'available')
            ->where('asset_value', '>=', 1_000_000)
            ->orderBy('id')
            ->first();

        if (! $market) {
            $market = MarketplaceAsset::create([
                'slug' => 'stg-uat-al-'.strtolower($suffix),
                'category' => 'vehicle',
                'title' => 'STAGING-UAT AL Vehicle '.$suffix,
                'description' => 'Staging-only UAT marketplace asset',
                'supplier_name' => 'STAGING UAT Supplier',
                'asset_value' => 10_000_000,
                'supplier_deposit' => 2_000_000,
                'customer_deposit' => 2_200_000,
                'weekly_installment' => 150_000,
                'max_tenure_months' => 36,
                'is_active' => true,
                'availability_status' => 'available',
            ]);
        }

        $quote = app(AssetLendingService::class)->comprehensiveInsuranceQuote($market);
        $out['checks']['marketplace_basis'] = ($quote['basis'] ?? '') === 'marketplace_asset_value'
            && (int) $quote['insured_value'] === (int) round((float) $market->asset_value)
            && isset($quote['snapshotted_at'], $quote['premium'], $quote['rate_percent']);
        $out['quote'] = [
            'insured_value' => $quote['insured_value'],
            'rate_percent' => $quote['rate_percent'],
            'premium' => $quote['premium'],
            'basis' => $quote['basis'],
        ];

        $reservation = app(AssetReservationService::class)->createReservation($customer, $market);
        $app = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'branch_id' => $branch->id,
            'application_number' => 'APP-UAT-AL-'.$suffix,
            'requested_amount' => (float) $market->asset_value,
            'requested_tenure_months' => 12,
            'offered_amount' => (float) $market->asset_value,
            'offered_tenure_months' => 12,
            'status' => 'approved',
            'current_stage' => 'approval',
            'offer_status' => 'pending_borrower',
            'approved_at' => now(),
            'funding_source' => 'internal',
            'recommendation_type' => 'approve',
            'purpose' => 'STAGING-ONLY UAT AL secured journey '.$suffix,
            'application_fee_amount' => 0,
            'application_fee_status' => 'waived',
        ]);
        app(AssetReservationService::class)->linkApplication($reservation->fresh(), $app);
        $out['application'] = $app->application_number;
        $out['marketplace_asset_id'] = $market->id;

        $out['checks']['no_comp_insurance_before_approval'] = ! CustomerPayment::query()
            ->where('payment_type', 'insurance_premium')
            ->where('customer_id', $customer->id)
            ->exists();

        app(AssetReservationService::class)->markDepositPaid($reservation->fresh(), 'STG-UAT-DEP-'.$suffix);
        $bundle = $this->payPostApprovalBundle($app->fresh(), $customer);
        $out['checks']['post_approval_bundle'] = $bundle;
        $this->signContractOnly($app->fresh(), $customer);

        $contract = LoanAgreement::query()
            ->where('loan_application_id', $app->id)
            ->where('document_type', 'loan_contract')
            ->where('status', 'signed')
            ->latest('id')
            ->first();
        $out['checks']['contract_no_disbursement_date'] = $contract
            && data_get($contract->snapshot, 'disbursement_date') === null;

        // Separate insurance payment via simulator
        $insPayment = app(\App\Services\CustomerPaymentService::class)->create([
            'customer' => $customer,
            'payment_type' => 'insurance_premium',
            'payment_method' => 'mobile_money',
            'amount' => $quote['premium'],
            'loan_product' => $product,
            'reference' => $app->application_number.'-INS',
            'source' => $app,
            'mobile_number' => $customer->phone,
            'provider_meta' => [
                'collateral_insurance' => [
                    'insured_value' => $quote['insured_value'],
                    'rate_percent' => $quote['rate_percent'],
                    'premium' => $quote['premium'],
                    'basis' => $quote['basis'],
                    'marketplace_asset_id' => $market->id,
                    'settings_buffer_months' => 1,
                    'snapshotted_at' => $quote['snapshotted_at'],
                ],
            ],
        ]);
        $sim = app(StagingPaymentSimulator::class);
        if (! $insPayment->isVerified()) {
            $sim->initiate($insPayment->fresh(), $customer->phone);
            $insPayment = $sim->applyOutcome($insPayment->fresh(), 'success');
        }
        $out['checks']['insurance_payment_verified'] = $insPayment->fresh()->isVerified();
        $out['checks']['payment_alone_not_ready'] = ! app(AssetReservationService::class)
            ->handoverReady($reservation->fresh());

        app(AssetReservationService::class)->advance($reservation->fresh(), 'gps_installation');
        app(AssetReservationService::class)->advance($reservation->fresh(), 'insurance_active');
        app(AssetReservationService::class)->advance($reservation->fresh(), 'registration_complete');
        $out['checks']['conditions_then_ready'] = app(AssetReservationService::class)
            ->handoverReady($reservation->fresh());

        $loan = app(LoanOriginationService::class)->createFromApplication($app->fresh());
        $handed = app(AssetHandoverService::class)->completeHandover($loan->fresh(), $admin);
        $out['checks']['released_active'] = $handed->status === 'active'
            && $handed->disbursement_date !== null
            && $app->fresh()->disbursed_at !== null;

        $flat = [];
        foreach ($out['checks'] as $k => $v) {
            $flat[$k] = is_array($v) ? (bool) ($v['pass'] ?? false) : (bool) $v;
        }
        $out['pass'] = ! in_array(false, $flat, true);

        return $out;
    }

    /** @return array<string, mixed> */
    private function payPostApprovalBundle(LoanApplication $app, Customer $customer): array
    {
        $agreements = app(LoanAgreementService::class);
        $offer = $agreements->generateOfferLetter($app->fresh());
        $agreements->acceptDirectly($offer);
        $app = $agreements->advanceAfterOfferAcceptance($app->fresh());

        app(PostApprovalFeeService::class)->generateForApplication($app->fresh());
        $fees = $app->fresh(['postApprovalFees'])->postApprovalFees;
        $lines = $fees->map(fn ($f) => [
            'code' => $f->code,
            'name' => $f->name,
            'amount' => (float) $f->calculated_amount,
        ])->values()->all();

        $hasInsFee = $fees->contains(fn ($f) => strtoupper((string) $f->code) === 'INS_FEE');
        $hasComprehensiveName = $fees->contains(fn ($f) => str_contains(strtolower((string) $f->name), 'comprehensive'));

        $pay = app(PostApprovalFeePaymentService::class);
        $ref = $pay->generatePaymentReference($app);
        $result = $pay->processMobileMoney($customer, $app->fresh(), $ref, false, $customer->phone);
        $payment = $result['payment'];

        if ($payment && ! $payment->isVerified()) {
            $sim = app(StagingPaymentSimulator::class);
            $sim->initiate($payment->fresh(), $customer->phone);
            $payment = $sim->applyOutcome($payment->fresh(), 'success');
        }

        $allPaid = app(PostApprovalFeeService::class)->allPaid($app->fresh());
        app(LoanAgreementService::class)->ensureLoanContractAfterFees($app->fresh());

        return [
            'pass' => $payment !== null
                && $payment->payment_type === 'post_approval_fee'
                && $payment->isVerified()
                && $allPaid
                && ! $hasComprehensiveName,
            'payment_id' => $payment?->id,
            'payment_type' => $payment?->payment_type,
            'amount' => (float) ($payment?->amount ?? 0),
            'lines' => $lines,
            'has_ins_fee' => $hasInsFee,
            'has_comprehensive_in_bundle' => $hasComprehensiveName,
            'itemized_count' => count($lines),
        ];
    }

    private function confirmDestinationAndSignContract(LoanApplication $app, Customer $customer): void
    {
        $customer = $customer->fresh();
        $name = $customer->legalDisplayName()
            ?: trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''));
        $account = CustomerDisbursementAccount::create([
            'customer_id' => $customer->id,
            'type' => 'mobile_money',
            'account_name' => $name,
            'mobile_provider' => 'mpesa',
            'mobile_number' => $customer->phone,
            'is_default' => true,
        ]);
        app(CustomerDisbursementDetailsService::class)->confirmForApplication(
            $app->fresh(),
            $customer,
            $account,
        );
        $this->signContractOnly($app->fresh(), $customer);
    }

    private function signContractOnly(LoanApplication $app, Customer $customer): void
    {
        $agreements = app(LoanAgreementService::class);
        $contract = LoanAgreement::query()
            ->where('loan_application_id', $app->id)
            ->where('document_type', 'loan_contract')
            ->latest('id')
            ->first() ?? $agreements->generateLoanContract($app->fresh());

        if (! $contract) {
            throw new \RuntimeException('Loan contract could not be generated for '.$app->application_number);
        }
        if (! $contract->isSigned()) {
            $agreements->acceptDirectly($contract);
        }
    }

    private function makeCustomer(?Branch $branch, string $first, string $last, string $phonePrefix): Customer
    {
        $user = User::query()->create([
            'name' => $first.' '.$last,
            'email' => strtolower($first).'.'.Str::lower(Str::random(4)).'@staging-uat.kopafasta.test',
            'password' => Hash::make(Str::random(16)),
            'role' => 'borrower',
            'is_active' => true,
            'pin_hash' => Hash::make('1234'),
        ]);

        return Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-UAT-'.strtoupper(Str::random(6)),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => $first,
            'last_name' => $last,
            'phone' => $phonePrefix.random_int(2000000, 8999999),
            'branch_id' => $branch?->id,
            'monthly_income' => 3_500_000,
            'country_code' => 'TZ',
        ]);
    }

    private function alignOwnershipTimingForOwnerSequence(): void
    {
        $catalog = collect(app(PostApprovalNextActionService::class)->catalog())
            ->map(function (array $row) {
                if (($row['key'] ?? '') === 'ownership_transfer') {
                    $row['timing'] = PostApprovalNextActionService::TIMING_BEFORE_DISBURSEMENT;
                }

                return $row;
            })
            ->values()
            ->all();
        Setting::set('underwriting.post_approval_conditions', $catalog);
    }

    /** @return list<array{id:int,amount:float,type:string,status:string}> */
    private function historicalFingerprint(?int $maxId = null): array
    {
        $q = CustomerPayment::query()->orderBy('id');
        if ($maxId !== null) {
            $q->where('id', '<=', $maxId);
        }

        return $q->get(['id', 'amount', 'payment_type', 'status'])
            ->map(fn ($p) => [
                'id' => (int) $p->id,
                'amount' => (float) $p->amount,
                'type' => (string) $p->payment_type,
                'status' => (string) $p->status,
            ])
            ->all();
    }
}
