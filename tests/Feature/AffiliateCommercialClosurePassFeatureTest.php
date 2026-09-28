<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\JournalEntry;
use App\Models\PartnerApplication;
use App\Models\PartnerPayment;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
use App\Models\PartnerPayoutRequest;
use App\Services\AffiliateApplicationFeePaymentService;
use App\Services\AffiliateCommissionWalletService;
use App\Services\AffiliateService;
use App\Services\AffiliateSettingsService;
use App\Services\AffiliateTermsService;
use App\Services\CustomerPaymentService;
use App\Services\LedgerService;
use App\Services\PartnerPayoutRequestService;
use App\Services\PartnerSettlementService;
use Database\Seeders\DefaultChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AffiliateCommercialClosurePassFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DefaultChartOfAccountsSeeder::class);
        $this->mapFinanceAccounts();
    }

    public function test_canonical_commission_uses_applicable_remaining_amount_base(): void
    {
        Setting::set('affiliates.commission_calculation_base', 'discounted_amount');
        Setting::set('affiliates.default_commission_percent', 10);
        Setting::set('affiliates.default_application_discount_percent', 10);
        Setting::set('affiliates.applies_to', array_merge(
            (array) config('affiliates.applies_to'),
            ['application_fee' => true]
        ));

        $affiliate = $this->affiliate([
            'affiliate_commission_percent' => 10,
            'application_discount_percent' => 10,
        ]);
        $customer = $this->customer(['affiliate_vendor_id' => $affiliate->id]);

        $quote = app(AffiliateService::class)->quoteFee($customer, 10_000, 'application_fee', $affiliate);

        $this->assertSame(10_000.0, $quote['base']);
        $this->assertSame(1_000.0, $quote['discount']);
        $this->assertSame(9_000.0, $quote['after_discount']);
        $this->assertSame(9_000.0, $quote['commission_base']);
        $this->assertSame('discounted_amount', $quote['calculation_base']);
        $this->assertSame(900.0, $quote['commission']);
        $this->assertSame(
            'Percentage of applicable remaining amount',
            app(AffiliateSettingsService::class)->commissionBasisLabel('en')
        );
    }

    public function test_earned_commission_posts_expense_and_payable_once_withdrawal_settles_liability(): void
    {
        $affiliate = $this->affiliate([
            'affiliate_commission_percent' => 10,
            'application_discount_percent' => 10,
        ]);
        $customer = $this->customer(['affiliate_vendor_id' => $affiliate->id]);

        $payment = CustomerPayment::create([
            'customer_id' => $customer->id,
            'payment_type' => 'application_fee',
            'payment_method' => 'mobile_money',
            'status' => 'awaiting_payment',
            'amount' => 9000,
            'currency' => 'TZS',
            'reference' => 'PAY-AFF90',
            'provider_meta' => [
                'pricing' => [
                    'gross' => 10000,
                    'net_payable' => 9000,
                    'affiliate_partner_id' => $affiliate->id,
                ],
            ],
        ]);

        app(CustomerPaymentService::class)->verify($payment);

        $wallet = PartnerPayment::query()
            ->where('partner_id', $affiliate->id)
            ->where('source_type', 'affiliate_commission')
            ->first();

        $this->assertNotNull($wallet);
        $this->assertSame('approved', $wallet->status);
        $this->assertSame(900, (int) $wallet->amount);
        $this->assertSame(9000.0, (float) data_get($wallet->meta, 'commission_base'));
        $this->assertSame(10.0, (float) data_get($wallet->meta, 'commission_rate_percent'));

        $earned = JournalEntry::query()
            ->whereIn('source_type', [PartnerPayment::class, \App\Models\VendorPayment::class])
            ->where('source_id', $wallet->id)
            ->where('memo', 'like', 'kind=affiliate_commission_earned%')
            ->get();
        $this->assertCount(1, $earned);

        $expenseId = app(LedgerService::class)->affiliateCommissionExpenseAccountId();
        $payableId = app(LedgerService::class)->affiliateCommissionPayableAccountId();
        $this->assertNotNull($expenseId);
        $this->assertNotNull($payableId);
        $this->assertTrue($earned->first()->lines->contains(fn ($l) => (int) $l->chart_of_account_id === (int) $expenseId && (float) $l->debit > 0));
        $this->assertTrue($earned->first()->lines->contains(fn ($l) => (int) $l->chart_of_account_id === (int) $payableId && (float) $l->credit > 0));

        // Accrual journal must stay single even if promote/heal runs again.
        app(PartnerSettlementService::class)->promotePendingAffiliateCommissions($affiliate->fresh());
        $this->assertSame(1, JournalEntry::query()
            ->where('source_id', $wallet->id)
            ->where('memo', 'like', 'kind=affiliate_commission_earned%')
            ->where('memo', 'not like', '%reversal%')
            ->count());

        $admin = User::factory()->create(['role' => 'admin']);
        $vendorPayment = \App\Models\VendorPayment::query()->findOrFail($wallet->id);
        app(PartnerSettlementService::class)->markPaymentPaid($vendorPayment, $admin, 'mobile_money', 'WD-AFF90');

        $payout = JournalEntry::query()
            ->where('source_id', $wallet->id)
            ->where('memo', 'like', 'kind=partner_payout_paid%')
            ->first();
        $this->assertNotNull($payout);
        $this->assertTrue($payout->lines->contains(fn ($l) => (int) $l->chart_of_account_id === (int) $payableId && (float) $l->debit > 0));
        $this->assertFalse($payout->lines->contains(fn ($l) => (int) $l->chart_of_account_id === (int) $expenseId));
    }

    public function test_affiliate_wallet_withdrawal_settles_affiliate_payable_not_recovery(): void
    {
        Setting::set('affiliates.minimum_payout_amount', 50);
        $affiliate = $this->affiliate([
            'affiliate_commission_percent' => 10,
            'application_discount_percent' => 10,
            'metadata' => [
                'plus_discount_percent' => 10,
                'payout_account' => [
                    'type' => 'mobile_money',
                    'mobile_provider' => 'M-Pesa',
                    'mobile_number' => '255700000091',
                ],
            ],
        ]);
        $customer = $this->customer(['affiliate_vendor_id' => $affiliate->id]);
        $payment = CustomerPayment::create([
            'customer_id' => $customer->id,
            'payment_type' => 'application_fee',
            'payment_method' => 'mobile_money',
            'status' => 'awaiting_payment',
            'amount' => 9000,
            'currency' => 'TZS',
            'reference' => 'PAY-WD90',
            'provider_meta' => [
                'pricing' => [
                    'gross' => 10000,
                    'net_payable' => 9000,
                    'affiliate_partner_id' => $affiliate->id,
                ],
            ],
        ]);
        app(CustomerPaymentService::class)->verify($payment);

        $payouts = app(PartnerPayoutRequestService::class);
        $this->assertSame(900.0, $payouts->availableBalance($affiliate, 'affiliate_commission'));
        $request = $payouts->request($affiliate->fresh(), 'affiliate_commission', 900);
        $payouts->markPaid($request, User::factory()->create(['role' => 'admin']));

        $payableId = app(LedgerService::class)->affiliateCommissionPayableAccountId();
        $recoveryId = app(LedgerService::class)->recoveryPartnerPayableAccountId();
        $expenseId = app(LedgerService::class)->affiliateCommissionExpenseAccountId();

        $journal = JournalEntry::query()
            ->where('source_type', PartnerPayoutRequest::class)
            ->where('source_id', $request->id)
            ->where('memo', 'like', 'kind=partner_payout_paid%')
            ->first();

        $this->assertNotNull($journal);
        $this->assertTrue($journal->lines->contains(fn ($l) => (int) $l->chart_of_account_id === (int) $payableId && (float) $l->debit === 900.0));
        $this->assertFalse($journal->lines->contains(fn ($l) => (int) $l->chart_of_account_id === (int) $expenseId));
        if ($recoveryId && (int) $recoveryId !== (int) $payableId) {
            $this->assertFalse($journal->lines->contains(fn ($l) => (int) $l->chart_of_account_id === (int) $recoveryId));
        }
    }

    public function test_tzs_90_commission_traces_basis_rate_journal_wallet(): void
    {
        Setting::set('affiliates.minimum_payout_amount', 50);
        $affiliate = $this->affiliate([
            'affiliate_commission_percent' => 10,
            'application_discount_percent' => 10,
            'metadata' => [
                'plus_discount_percent' => 10,
                'payout_account' => [
                    'type' => 'mobile_money',
                    'mobile_provider' => 'M-Pesa',
                    'mobile_number' => '255700000090',
                ],
            ],
        ]);
        $customer = $this->customer(['affiliate_vendor_id' => $affiliate->id, 'member_no' => 'KPF-TZ-UAT90']);

        $quote = app(AffiliateService::class)->quoteFee($customer, 1000, 'kopafasta_plus', $affiliate);
        $this->assertSame(1000.0, $quote['base']);
        $this->assertSame(900.0, $quote['commission_base']); // remaining after 10%
        $this->assertSame(10.0, $quote['commission_rate_percent']);
        $this->assertSame(90.0, $quote['commission']);

        $payment = CustomerPayment::create([
            'customer_id' => $customer->id,
            'payment_type' => 'kopafasta_plus',
            'payment_method' => 'mobile_money',
            'status' => 'awaiting_payment',
            'amount' => 900,
            'currency' => 'TZS',
            'reference' => 'PAY-UAT90',
            'provider_meta' => [
                'pricing' => [
                    'gross' => 1000,
                    'net_payable' => 900,
                    'affiliate_partner_id' => $affiliate->id,
                ],
            ],
        ]);
        app(CustomerPaymentService::class)->verify($payment);

        $wallet = PartnerPayment::query()
            ->where('partner_id', $affiliate->id)
            ->where('reference', 'PAY-UAT90')
            ->first();
        $this->assertNotNull($wallet);
        $this->assertSame(90, (int) $wallet->amount);
        $this->assertSame(900.0, (float) data_get($wallet->meta, 'commission_base'));
        $this->assertSame(10.0, (float) data_get($wallet->meta, 'commission_rate_percent'));
        $this->assertSame('approved', $wallet->status);

        $expense = ChartOfAccount::query()->find(app(LedgerService::class)->affiliateCommissionExpenseAccountId());
        $payable = ChartOfAccount::query()->find(app(LedgerService::class)->affiliateCommissionPayableAccountId());
        $this->assertSame('5130', $expense?->code);
        $this->assertSame('2140', $payable?->code);

        $earned = JournalEntry::query()
            ->where('source_id', $wallet->id)
            ->where('memo', 'like', 'kind=affiliate_commission_earned%')
            ->where('memo', 'not like', '%reversal%')
            ->first();
        $this->assertNotNull($earned);
        $this->assertTrue($earned->lines->contains(fn ($l) => (int) $l->chart_of_account_id === (int) $expense->id && (float) $l->debit === 90.0));
        $this->assertTrue($earned->lines->contains(fn ($l) => (int) $l->chart_of_account_id === (int) $payable->id && (float) $l->credit === 90.0));

        $this->assertSame(90.0, app(PartnerPayoutRequestService::class)->availableBalance($affiliate, 'affiliate_commission'));
    }

    public function test_shared_payout_service_routes_liability_by_source_type(): void
    {
        Setting::set('affiliates.minimum_payout_amount', 50);
        $cashId = app(LedgerService::class)->cashAccountId();
        $affPayable = app(LedgerService::class)->affiliateCommissionPayableAccountId();
        $recoveryPayable = app(LedgerService::class)->recoveryPartnerPayableAccountId();
        $supplierPayable = app(LedgerService::class)->supplierPayableAccountId();
        $this->assertNotNull($cashId);
        $this->assertNotNull($affPayable);
        $this->assertNotNull($recoveryPayable);
        $this->assertNotSame((int) $affPayable, (int) $recoveryPayable);

        $cases = [
            'affiliate_commission' => $affPayable,
            'recovery_commission' => $recoveryPayable,
            'supplier_deposit' => $supplierPayable ?? $recoveryPayable,
        ];

        foreach ($cases as $sourceType => $expectedPayable) {
            $vendor = Vendor::create([
                'user_id' => User::factory()->create(['role' => 'vendor'])->id,
                'vendor_number' => 'PAY-'.strtoupper(substr($sourceType, 0, 3)).random_int(100, 999),
                'name' => 'Payout '.$sourceType,
                'category' => $sourceType === 'affiliate_commission' ? 'affiliate' : 'debt_collector',
                'status' => 'active',
                'phone' => '25571'.random_int(1000000, 9999999),
                'metadata' => ['payout_account' => [
                    'type' => 'mobile_money',
                    'mobile_provider' => 'M-Pesa',
                    'mobile_number' => '255700'.random_int(100000, 999999),
                ]],
            ]);

            PartnerPayment::create([
                'partner_id' => $vendor->id,
                'invoice_number' => 'INV-'.strtoupper(Str::random(6)),
                'amount' => 100,
                'status' => 'approved',
                'source_type' => $sourceType,
                'description' => 'Regression '.$sourceType,
                'approved_at' => now(),
            ]);

            $payouts = app(PartnerPayoutRequestService::class);
            $minOk = $sourceType === 'affiliate_commission' ? 100.0 : 100.0;
            if ($sourceType === 'affiliate_commission') {
                Setting::set('affiliates.minimum_payout_amount', 50);
            }
            $request = $payouts->request($vendor, $sourceType, $minOk);
            $payouts->markPaid($request, User::factory()->create(['role' => 'admin']));

            $journal = JournalEntry::query()
                ->where('source_type', PartnerPayoutRequest::class)
                ->where('source_id', $request->id)
                ->first();
            $this->assertNotNull($journal, $sourceType.' missing payout journal');
            $this->assertTrue(
                $journal->lines->contains(fn ($l) => (int) $l->chart_of_account_id === (int) $expectedPayable && (float) $l->debit === 100.0),
                $sourceType.' must debit expected payable'
            );
            $this->assertTrue(
                $journal->lines->contains(fn ($l) => (int) $l->chart_of_account_id === (int) $cashId && (float) $l->credit === 100.0),
                $sourceType.' must credit cash'
            );

            if ($sourceType === 'recovery_commission') {
                $this->assertSame('2120', ChartOfAccount::query()->find($expectedPayable)?->code);
            }
            if ($sourceType === 'affiliate_commission') {
                $this->assertSame('2140', ChartOfAccount::query()->find($expectedPayable)?->code);
                $this->assertFalse($journal->lines->contains(
                    fn ($l) => (int) $l->chart_of_account_id === (int) $recoveryPayable
                ));
            }
        }
    }

    public function test_dispute_reverses_earned_journal_once(): void
    {
        $affiliate = $this->affiliate(['affiliate_commission_percent' => 10, 'application_discount_percent' => 0]);
        $customer = $this->customer(['affiliate_vendor_id' => $affiliate->id]);
        $payment = CustomerPayment::create([
            'customer_id' => $customer->id,
            'payment_type' => 'application_fee',
            'payment_method' => 'mobile_money',
            'status' => 'awaiting_payment',
            'amount' => 10000,
            'currency' => 'TZS',
            'reference' => 'PAY-DSP01',
            'provider_meta' => ['pricing' => ['gross' => 10000, 'affiliate_partner_id' => $affiliate->id]],
        ]);
        app(CustomerPaymentService::class)->verify($payment);
        $wallet = PartnerPayment::query()->where('reference', 'PAY-DSP01')->first();
        $this->assertNotNull($wallet);

        app(AffiliateCommissionWalletService::class)->dispute($wallet, $affiliate, 'Owner UAT dispute');
        $this->assertSame('disputed', $wallet->fresh()->status);

        $this->assertSame(1, JournalEntry::query()
            ->where('source_id', $wallet->id)
            ->where('memo', 'like', 'kind=affiliate_commission_earned_reversal%')
            ->count());

        app(PartnerSettlementService::class)->reverseAffiliateCommissionEarnedJournal($wallet->fresh());
        $this->assertSame(1, JournalEntry::query()
            ->where('source_id', $wallet->id)
            ->where('memo', 'like', 'kind=affiliate_commission_earned_reversal%')
            ->count());
    }

    public function test_contracts_state_percent_of_applicable_remaining_amount_en_sw(): void
    {
        $affiliate = $this->affiliate();
        $premium = $this->premium();
        $terms = app(AffiliateTermsService::class);

        $this->assertGreaterThanOrEqual(4, $terms->agreementVersion());

        foreach ([$affiliate, $premium] as $partner) {
            $en = $terms->render($partner, 'en');
            $sw = $terms->render($partner, 'sw');
            $this->assertStringContainsString('applicable remaining amount', $en);
            $this->assertStringContainsString('Commission basis', $en);
            $this->assertStringContainsString('Percentage of applicable remaining amount', $en);
            $this->assertStringContainsString('kiasi husika kinachobaki', $sw);
            $this->assertStringContainsString('Msingi wa kamisheni', $sw);
            $this->assertStringContainsString('not authorized to represent themselves as a Kopafasta employee', $en);
            $this->assertStringContainsString('Pending, Earned, Paid, Reserved, Disputed or Reversed', $en);
            $this->assertStringContainsString('haaruhusiwi kujiwakilisha kama mfanyakazi wa Kopafasta', $sw);
        }

        $acceptance = $terms->accept($affiliate, Request::create('/terms', 'POST'), 'en');
        $this->assertGreaterThanOrEqual(4, (int) $acceptance->agreement_version);
        $this->assertStringContainsString('applicable remaining amount', (string) $acceptance->rendered_text);
        $this->assertStringContainsString('Prohibited conduct and Affiliate obligations', (string) $acceptance->rendered_text);
    }

    public function test_application_fee_settings_gate_and_payment_show_body(): void
    {
        Storage::fake('public');
        Setting::set('affiliates.application_fee_required', false);
        Setting::set('affiliates.application_fee_amount', 10000);

        $this->assertFalse(app(AffiliateApplicationFeePaymentService::class)->isRequired());

        $this->postAffiliateApply('off@example.com', '+255712345901')
            ->assertRedirect(route('site.partners.apply.tracking', ['phone' => '+255712345901']));
        $this->assertSame(0, CustomerPayment::query()->where('payment_type', 'affiliate_application_fee')->count());
        $app = PartnerApplication::query()->where('email', 'off@example.com')->first();
        $this->assertNotNull($app);
        $this->assertNotSame('awaiting_fee', $app->status);

        Setting::set('affiliates.application_fee_required', true);
        Setting::set('affiliates.application_fee_amount', 5000);
        $this->assertTrue(app(AffiliateApplicationFeePaymentService::class)->isRequired());

        $response = $this->postAffiliateApply('on@example.com', '+255712345902');
        $payment = CustomerPayment::query()->where('payment_type', 'affiliate_application_fee')->latest('id')->first();
        $this->assertNotNull($payment);
        $this->assertSame(5000.0, (float) $payment->amount);
        $response->assertRedirect(app(AffiliateApplicationFeePaymentService::class)->payUrl($payment));

        $this->get(app(AffiliateApplicationFeePaymentService::class)->payUrl($payment))
            ->assertOk()
            ->assertSee(__('site.affiliate_apply.fee_title'), false)
            ->assertSee($payment->reference, false);

        // Same unpaid obligation reused.
        $again = app(AffiliateApplicationFeePaymentService::class)->open($app = PartnerApplication::query()->where('email', 'on@example.com')->first());
        $this->assertSame($payment->id, $again->id);

        app(CustomerPaymentService::class)->verify($payment->fresh());
        $this->assertNotNull($payment->fresh()->journal_entry_id);
        $this->assertSame('pending', PartnerApplication::query()->where('email', 'on@example.com')->value('status'));
    }

    /** @param  array<string, mixed>  $overrides */
    private function affiliate(array $overrides = []): Vendor
    {
        $affiliate = Vendor::create(array_merge([
            'user_id' => User::factory()->create(['role' => 'vendor'])->id,
            'vendor_number' => 'AFF-CC-'.random_int(100, 999),
            'name' => 'Commercial Closure Affiliate',
            'category' => 'affiliate',
            'status' => 'active',
            'phone' => '2557123'.random_int(10000, 99999),
            'affiliate_code' => 'CC'.random_int(1000, 9999),
            'affiliate_kyc_status' => 'verified',
            'affiliate_lifecycle_status' => 'active',
            'membership_status' => 'active',
            'membership_started_at' => now()->subMonth(),
            'membership_expires_at' => now()->addYear(),
            'application_discount_percent' => 10,
            'affiliate_commission_percent' => 10,
            'metadata' => ['plus_discount_percent' => 10],
        ], $overrides));
        app(AffiliateTermsService::class)->accept($affiliate, Request::create('/terms', 'POST'));

        return $affiliate->fresh();
    }

    /** @param  array<string, mixed>  $overrides */
    private function premium(array $overrides = []): Vendor
    {
        return $this->affiliate(array_merge([
            'name' => 'Premium Commercial Affiliate',
            'affiliate_premium' => true,
            'affiliate_code' => 'PC'.random_int(1000, 9999),
        ], $overrides));
    }

    /** @param  array<string, mixed>  $overrides */
    private function customer(array $overrides = []): Customer
    {
        return Customer::create(array_merge([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'C-CC'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Said',
            'last_name' => 'Affiliate',
            'phone' => '+255600'.random_int(100000, 999999),
            'country_code' => 'TZ',
        ], $overrides));
    }

    private function postAffiliateApply(string $email, string $phone)
    {
        return $this->post(route('site.affiliate.apply.post'), array_merge([
            'applicant_category' => 'individual',
            'full_name' => 'Commercial Closure Applicant',
            'email' => $email,
            'phone' => $phone,
            'region' => 'Dar es Salaam',
            'occupation' => 'Shop owner',
            'sales_experience' => 'I sell airtime and assist customers daily.',
            'languages' => ['sw', 'en'],
            'why_affiliate' => 'I already advise customers on mobile money.',
            'acquisition_methods' => ['existing_customers', 'community'],
            'monthly_reach' => '11-30',
            'first_10_customers' => 'I will start with my regular shop customers this month.',
            'declaration_accepted' => '1',
            'doc_national_id_front' => UploadedFile::fake()->image('id-front.jpg'),
            'doc_national_id_back' => UploadedFile::fake()->image('id-back.jpg'),
        ], $this->affiliateApplyCompletenessFields()));
    }

    /** @return array<string, mixed> */
    private function affiliateApplyCompletenessFields(): array
    {
        return [
            'date_of_birth' => '1990-05-12',
            'gender' => 'female',
            'district' => 'Ilala',
            'ward' => 'Kariakoo',
            'previous_agent' => 'no',
            'channels' => ['whatsapp', 'physical'],
            'how_heard' => 'Friend referral',
            'registered_business' => 'no',
            'conduct_accepted' => '1',
        ];
    }

    private function mapFinanceAccounts(): void
    {
        $cash = ChartOfAccount::query()->where('code', '1000')->value('id');
        $expense = ChartOfAccount::query()->where('code', '5130')->value('id');
        $payable = ChartOfAccount::query()->where('code', '2140')->value('id');
        $appFeeIncome = ChartOfAccount::query()->where('code', '4020')->value('id');
        $debit = ChartOfAccount::query()->where('code', '1020')->value('id') ?: $cash;

        Setting::setMany([
            'finance.cash_gl_account_id' => $cash,
            'finance.affiliate_commission_expense_gl_account_id' => $expense,
            'finance.affiliate_commission_payable_gl_account_id' => $payable,
            'finance.application_fee_income_gl_account_id' => $appFeeIncome,
            'finance.fee_income_gl_account_id' => $appFeeIncome,
            'finance.customer_gl_account_id' => $debit,
        ]);
        Setting::set('affiliates.commission_calculation_base', 'discounted_amount');
        Setting::set('affiliates.commission_mode', 'percentage');
        Setting::set('affiliates.applies_to', array_merge(
            (array) config('affiliates.applies_to'),
            ['application_fee' => true, 'kopafasta_plus' => true]
        ));
    }
}
