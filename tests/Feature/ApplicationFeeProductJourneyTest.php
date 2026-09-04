<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDraft;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\ApplicationFeePaymentService;
use App\Services\GroupLendingService;
use App\Services\LoanApplicationDraftService;
use App\Services\SmartLoanApplicationWizardService;
use Database\Seeders\PublicLoanProductsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApplicationFeeProductJourneyTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const ACTIVE_CODES = ['IL', 'SL', 'GL', 'AB', 'AL', 'FC', 'KB', 'BP', 'EL', 'EM', 'WL'];

    /** Distinct Settings amounts so the gate cannot pass on a hardcoded 10,000. */
    private const SETTINGS_FEES = [
        'IL' => 10_000,
        'SL' => 11_000,
        'GL' => 12_000,
        'AB' => 13_000,
        'AL' => 14_000,
        'FC' => 15_000,
        'KB' => 16_000,
        'BP' => 17_000,
        'EL' => 18_000,
        'EM' => 19_000,
        'WL' => 20_000,
    ];

    private function borrower(string $suffix = '00'): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);

        return Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-JNY-'.$suffix,
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Journey',
            'last_name' => 'Borrower',
            'phone' => '25571500'.str_pad($suffix, 4, '0', STR_PAD_LEFT),
            'date_of_birth' => now()->subYears(32)->toDateString(),
            'national_id' => '19880101123456'.str_pad($suffix, 4, '0', STR_PAD_LEFT),
            'nida_verification_status' => 'verified',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
            'face_verification_status' => 'verified',
            'region' => 'Dar es Salaam',
            'district' => 'Kinondoni',
            'street' => 'Samora',
            'activity_type' => 'employed',
            'income_range' => '500k_1m',
            'nok_first_name' => 'Next',
            'nok_last_name' => 'Kin',
            'nok_phone' => '25571600'.str_pad($suffix, 4, '0', STR_PAD_LEFT),
            'nok_relationship' => 'spouse',
        ]);
    }

    /** @return array<string, mixed> */
    private function setupPayload(Customer $customer, LoanProduct $product): array
    {
        $amount = max((int) $product->min_amount, 500_000);
        $tenure = max((int) $product->tenure_min_months, 3);
        $form = [
            'loan_product_id' => $product->id,
            'requested_amount' => $amount,
            'requested_tenure_months' => $tenure,
            'purpose' => 'business',
        ];
        $payload = [
            'application_started' => true,
            'form' => $form,
        ];

        if (strtoupper((string) $product->code) === 'AB') {
            $form['customer_asset_ids'] = [1];
            $form['asset_type'] = 'vehicle';
            $form['asset_description'] = 'Test vehicle';
        }

        $payload['form'] = $form;

        if (app(GroupLendingService::class)->isGroupProduct($product)) {
            $payload['group'] = [
                'name' => 'Journey Group',
                'purpose' => 'business',
                'target_member_count' => 3,
                'amount_per_member' => 200_000,
                'members' => [
                    ['customer_id' => $customer->id, 'role' => 'leader', 'requested_amount' => 200_000],
                    ['name' => 'Member Two', 'role' => 'member', 'requested_amount' => 200_000],
                    ['name' => 'Member Three', 'role' => 'member', 'requested_amount' => 200_000],
                ],
            ];
        }

        return $payload;
    }

    private function expectedFee(Customer $customer, LoanProduct $product, array $payload): int
    {
        return app(ApplicationFeePaymentService::class)->requiredAmount($customer, $product, $payload);
    }

    private function postSetupStep(Customer $customer, LoanProduct $product, array $payload): string
    {
        $amount = (float) ($payload['form']['requested_amount'] ?? $product->min_amount);
        $plan = app(SmartLoanApplicationWizardService::class)->borrowerStepPlan($customer, $product, $amount);
        $setup = ['quote', 'asset_details', 'asset_tenure', 'group_setup', 'group_members'];
        foreach ($plan as $step) {
            $key = (string) ($step['key'] ?? '');
            if ($key !== '' && ! in_array($key, $setup, true)) {
                return $key;
            }
        }

        return 'review';
    }

    private function seedCatalogueWithDistinctFees(): void
    {
        (new PublicLoanProductsSeeder)->run();

        foreach (self::SETTINGS_FEES as $code => $amount) {
            LoanProduct::query()->where('code', $code)->update(['application_fee_amount' => $amount]);
        }
    }

    public function test_every_active_product_follows_the_canonical_fee_journey(): void
    {
        $this->seedCatalogueWithDistinctFees();

        $codes = LoanProduct::query()
            ->where('is_active', true)
            ->whereIn('code', self::ACTIVE_CODES)
            ->orderBy('code')
            ->pluck('code')
            ->all();

        foreach (self::ACTIVE_CODES as $code) {
            $this->assertContains($code, $codes, $code.' must be an active catalogue product');
        }
        $this->assertCount(11, self::ACTIVE_CODES);
        $this->assertFalse(
            LoanProduct::query()->where('code', 'SAL-12')->where('is_active', true)->exists(),
            'SAL-12 stays excluded while Coming Soon',
        );

        $requiredReachedPaymentShow = 0;
        $zeroSkipped = 0;

        foreach (self::ACTIVE_CODES as $index => $code) {
            $customer = $this->borrower((string) ($index + 1));
            $product = LoanProduct::query()->where('code', $code)->firstOrFail();
            $payload = $this->setupPayload($customer, $product);
            $settingsFee = (int) $product->application_fee_amount;
            $expected = $this->expectedFee($customer, $product, $payload);
            $setupKey = app(LoanApplicationDraftService::class)->lastSetupStepKeyForProduct($product);
            $nextKey = $this->postSetupStep($customer, $product, $payload);
            $fees = app(ApplicationFeePaymentService::class);

            $this->assertSame($settingsFee, (int) $product->fresh()->application_fee_amount, $code.' Settings amount');
            if ($code === 'GL') {
                $this->assertSame('per_member', $fees->obligation($customer, $product, $payload)['basis']);
                $this->assertSame($settingsFee * 3, $expected, 'GL roster × Settings');
            } elseif ($code === 'AB') {
                $this->assertSame('origination', $fees->obligation($customer, $product, $payload)['basis']);
                $this->assertSame(
                    quoted_application_fee($customer, $product) + quoted_valuation_fee($customer, selected_collateral_count($payload['form'] ?? [])),
                    $expected,
                    'AB origination = application fee + valuation policy',
                );
            } else {
                $this->assertSame('flat', $fees->obligation($customer, $product, $payload)['basis']);
                $this->assertSame($settingsFee, $expected, $code.' quoted fee matches Settings');
            }

            $quote = $this->actingAs($customer->user)
                ->getJson(route('site.borrower.apply.application-fee.quote', array_filter([
                    'loan_product_id' => $product->id,
                    'member_count' => $code === 'GL' ? 3 : null,
                ])))
                ->assertOk()
                ->json();

            $this->assertSame($expected, (int) $quote['amount'], $code.' quote API');

            $setupSave = [
                'phase' => 'application',
                'step' => 0,
                'step_key' => $setupKey,
                'loan_product_id' => $product->id,
                'form' => $payload['form'],
            ];
            if (isset($payload['group'])) {
                $setupSave['group'] = $payload['group'];
            }

            $this->actingAs($customer->user)
                ->putJson(route('site.borrower.apply.draft.save'), $setupSave)
                ->assertOk()
                ->assertJsonPath('step_key', $setupKey);

            foreach (['en', 'sw'] as $locale) {
                $html = $this->actingAs($customer->user)
                    ->withSession(['locale' => $locale])
                    ->get(route('site.borrower.apply', [
                        'product' => $product->id,
                        'resume' => 1,
                    ]))
                    ->assertOk()
                    ->getContent();

                $this->assertStringContainsString(__('borrower.apply.next', [], $locale), $html, $code.' '.$locale.' CTA');
                $this->assertStringContainsString(__('borrower.apply.application_fee.paid_badge', [], $locale), $html, $code.' '.$locale.' paid');
                $this->assertStringContainsString(__('borrower.apply.application_fee.failed', [], $locale), $html, $code.' '.$locale.' failed');
                $this->assertStringContainsString('applicationFeePayUrl', $html, $code.' shared pay URL wiring');
            }

            $obligation = $fees->obligation($customer, $product, $payload);
            $this->assertSame('due', $obligation['status'], $code.' starts unpaid');
            $this->assertSame($expected, $obligation['amount']);

            $bypassSave = [
                'phase' => 'application',
                'step' => 3,
                'step_key' => $nextKey,
                'loan_product_id' => $product->id,
                'form' => $payload['form'],
            ];
            if (isset($payload['group'])) {
                $bypassSave['group'] = $payload['group'];
            }

            $this->actingAs($customer->user)
                ->putJson(route('site.borrower.apply.draft.save'), $bypassSave)
                ->assertOk()
                ->assertJsonPath('step_key', $setupKey);

            $poisoned = LoanApplicationDraft::query()
                ->where('customer_id', $customer->id)
                ->where('loan_product_id', $product->id)
                ->first();
            $poisonedPayload = $poisoned?->payload ?? $payload;
            $poisonedPayload['step_key'] = $nextKey;
            $poisoned?->update(['payload' => $poisonedPayload]);

            $this->actingAs($customer->user)
                ->get(route('site.borrower.apply', [
                    'product' => $product->id,
                    'resume' => 1,
                    'step_key' => $nextKey,
                ]))
                ->assertOk()
                ->assertViewHas('savedDraft', function ($draft) use ($setupKey) {
                    return ($draft['step_key'] ?? null) === $setupKey
                        && ($draft['resume_target']['step_key'] ?? null) === $setupKey;
                });

            if ($product->requires_guarantor) {
                $this->actingAs($customer->user)
                    ->postJson(route('site.borrower.apply.guarantor-lookup'), [
                        'membership_no' => 'M-'.$code,
                        'phone' => '255712340000',
                        'loan_product_id' => $product->id,
                    ])
                    ->assertStatus(422)
                    ->assertJsonValidationErrors('application_fee');

                $this->actingAs($customer->user)
                    ->postJson(route('site.borrower.apply.guarantor-invite'), [
                        'loan_product_id' => $product->id,
                        'external_first_name' => 'Asha',
                        'external_last_name' => 'Juma',
                        'external_phone' => '255712340001',
                        'external_relationship' => 'sibling',
                        'external_region' => 'Dar es Salaam',
                        'external_district' => 'Kinondoni',
                    ])
                    ->assertStatus(422)
                    ->assertJsonValidationErrors('application_fee');
            }

            if ($code === 'AB') {
                $this->actingAs($customer->user)
                    ->postJson(route('site.borrower.apply.asset-document'), [
                        'loan_product_id' => $product->id,
                        'document_code' => 'logbook',
                    ])
                    ->assertStatus(422)
                    ->assertJsonMissingValidationErrors('application_fee');
            }

            $this->actingAs($customer->user)
                ->post(route('site.borrower.apply.submit'), [
                    'loan_product_id' => $product->id,
                    'requested_amount' => $payload['form']['requested_amount'],
                    'requested_tenure_months' => $payload['form']['requested_tenure_months'],
                    'purpose' => 'business',
                    'consent' => '1',
                ]);

            $this->assertSame(
                0,
                LoanApplication::query()
                    ->where('customer_id', $customer->id)
                    ->where('loan_product_id', $product->id)
                    ->count(),
                $code.' unpaid submit must not create an application',
            );

            $failed = CustomerPayment::create([
                'customer_id' => $customer->id,
                'loan_product_id' => $product->id,
                'payment_type' => 'application_fee',
                'payment_method' => 'mobile_money',
                'amount' => $expected,
                'currency' => 'TZS',
                'status' => $code === 'EL' ? 'expired' : 'failed',
                'reference' => 'PAY-FAIL-'.$code,
            ]);

            $this->assertSame('failed', $fees->obligation($customer, $product, $payload)['status'], $code.' failed/expired stays unpaid');
            $this->assertFalse($fees->isSatisfiedFor($customer, $product, $payload));

            $outstanding = CustomerPayment::create([
                'customer_id' => $customer->id,
                'loan_product_id' => $product->id,
                'payment_type' => 'application_fee',
                'payment_method' => 'mobile_money',
                'amount' => $expected,
                'currency' => 'TZS',
                'status' => 'awaiting_payment',
                'reference' => 'PAY-WAIT-'.$code,
            ]);

            $this->assertSame('initiated', $fees->obligation($customer, $product, $payload)['status'], $code.' resume keeps the outstanding obligation');

            $pay = $this->actingAs($customer->user)
                ->postJson(route('site.borrower.apply.application-fee.pay'), [
                    'loan_product_id' => $product->id,
                    'payment_phone' => $customer->phone,
                    'member_count' => $code === 'GL' ? 3 : null,
                ])
                ->assertOk()
                ->assertJsonPath('ok', true);

            $waitUrl = (string) $pay->json('wait_url');
            $paymentId = (int) ($pay->json('fee.payment_id') ?? 0);
            $this->assertNotSame('', $waitUrl, $code.' must hand off to payment.show');
            $this->assertStringContainsString('/borrower/payments/', $waitUrl);
            $this->assertSame($outstanding->id, $paymentId, $code.' Pay now must reopen the same outstanding payment');
            $this->assertNotEquals($failed->id, $paymentId, $code.' retry must not treat the failed row as paid');

            $payment = CustomerPayment::query()->findOrFail($paymentId);
            $this->assertSame('application_fee', $payment->payment_type);
            $this->assertSame($expected, (int) $payment->amount, $code.' payment.show amount');

            $this->actingAs($customer->user)
                ->get(route('site.borrower.payments.show', $payment))
                ->assertOk()
                ->assertSee($payment->reference, false);

            $resumePay = $this->actingAs($customer->user)
                ->postJson(route('site.borrower.apply.application-fee.pay'), [
                    'loan_product_id' => $product->id,
                    'payment_phone' => $customer->phone,
                    'member_count' => $code === 'GL' ? 3 : null,
                ])
                ->assertOk();

            $this->assertSame($paymentId, (int) ($resumePay->json('fee.payment_id') ?? 0), $code.' leave and return must not open another TZS charge');

            $payment->update(['status' => 'paid', 'paid_at' => now()]);
            $fees->syncDraftFromVerifiedPayment($customer, $product);
            $draft = app(LoanApplicationDraftService::class)->find($customer, $product->id);
            $this->assertTrue($fees->isSatisfiedFor($customer, $product, $draft?->payload), $code.' verified paid satisfies obligation');

            $this->actingAs($customer->user)
                ->putJson(route('site.borrower.apply.draft.save'), array_filter([
                    'phase' => 'application',
                    'step' => 3,
                    'step_key' => $nextKey,
                    'loan_product_id' => $product->id,
                    'form' => $payload['form'],
                    'group' => $payload['group'] ?? null,
                    'application_fee' => $draft?->payload['application_fee'] ?? ['status' => 'paid'],
                ], fn ($value) => $value !== null))
                ->assertOk()
                ->assertJsonPath('step_key', $nextKey);

            $this->flushSession();
            $this->actingAs($customer->user)
                ->withSession(['locale' => 'en'])
                ->get(route('site.borrower.apply', [
                    'product' => $product->id,
                    'resume' => 1,
                    'step_key' => $nextKey,
                ]))
                ->assertOk()
                ->assertViewHas('savedDraft', function ($saved) use ($nextKey) {
                    return ($saved['step_key'] ?? null) === $nextKey
                        && ($saved['resume_target']['step_key'] ?? null) === $nextKey;
                });

            $again = $fees->openSharedGate($customer, $product, 'PAY-DUP-'.$code);
            $this->assertSame('paid', $again['status']);
            $this->assertSame(
                1,
                CustomerPayment::query()
                    ->where('customer_id', $customer->id)
                    ->where('loan_product_id', $product->id)
                    ->where('payment_type', 'application_fee')
                    ->whereIn('status', ['paid', 'verified'])
                    ->count(),
                $code.' must not create a second verified fee',
            );

            $requiredReachedPaymentShow++;
        }

        $this->assertSame(11, $requiredReachedPaymentShow);
        $this->assertSame(0, $zeroSkipped);
    }

    public function test_settings_override_and_zero_fee_are_honoured_by_the_same_gate(): void
    {
        $this->seedCatalogueWithDistinctFees();
        $customer = $this->borrower();
        $fees = app(ApplicationFeePaymentService::class);

        $il = LoanProduct::query()->where('code', 'IL')->firstOrFail();
        $wl = LoanProduct::query()->where('code', 'WL')->firstOrFail();
        $em = LoanProduct::query()->where('code', 'EM')->firstOrFail();

        $il->update(['application_fee_amount' => 15_000]);
        $wl->update(['application_fee_amount' => 25_000]);
        $em->update(['application_fee_amount' => 0]);

        $ilPayload = $this->setupPayload($customer, $il->fresh());
        $wlPayload = $this->setupPayload($customer, $wl->fresh());
        $emPayload = $this->setupPayload($customer, $em->fresh());

        $this->assertSame(15_000, $fees->requiredAmount($customer, $il->fresh(), $ilPayload));
        $this->assertSame(25_000, $fees->requiredAmount($customer, $wl->fresh(), $wlPayload));
        $this->assertSame(0, $fees->requiredAmount($customer, $em->fresh(), $emPayload));
        $this->assertSame('not_applicable', $fees->obligation($customer, $em->fresh(), $emPayload)['status']);

        $this->actingAs($customer->user)
            ->getJson(route('site.borrower.apply.application-fee.quote', ['loan_product_id' => $il->id]))
            ->assertOk()
            ->assertJsonPath('amount', 15_000);

        $this->actingAs($customer->user)
            ->putJson(route('site.borrower.apply.draft.save'), [
                'phase' => 'application',
                'step' => 1,
                'step_key' => 'quote',
                'loan_product_id' => $il->id,
                'form' => $ilPayload['form'],
            ])
            ->assertOk();

        $pay = $this->actingAs($customer->user)
            ->postJson(route('site.borrower.apply.application-fee.pay'), [
                'loan_product_id' => $il->id,
                'payment_phone' => $customer->phone,
            ])
            ->assertOk();

        $this->assertSame(15_000, (int) CustomerPayment::query()->findOrFail((int) $pay->json('fee.payment_id'))->amount);
        $this->assertStringContainsString('/borrower/payments/', (string) $pay->json('wait_url'));

        $this->actingAs($customer->user)
            ->putJson(route('site.borrower.apply.draft.save'), [
                'phase' => 'application',
                'step' => 1,
                'step_key' => 'guarantor',
                'loan_product_id' => $em->id,
                'form' => $emPayload['form'],
            ])
            ->assertOk()
            ->assertJsonPath('step_key', 'guarantor');

        $this->assertSame(
            0,
            CustomerPayment::query()
                ->where('customer_id', $customer->id)
                ->where('loan_product_id', $em->id)
                ->count(),
            'Zero-fee product must not open payment.show',
        );

        $il->update(['application_fee_amount' => self::SETTINGS_FEES['IL']]);
        $wl->update(['application_fee_amount' => self::SETTINGS_FEES['WL']]);
        $em->update(['application_fee_amount' => self::SETTINGS_FEES['EM']]);

        $this->assertSame(self::SETTINGS_FEES['IL'], (int) $il->fresh()->application_fee_amount);
        $this->assertSame(self::SETTINGS_FEES['WL'], (int) $wl->fresh()->application_fee_amount);
        $this->assertSame(self::SETTINGS_FEES['EM'], (int) $em->fresh()->application_fee_amount);
    }

    public function test_post_approval_and_asset_setup_routes_stay_on_their_own_stages(): void
    {
        $this->assertTrue(Route::has('site.borrower.application.post-approval-fees.pay'));
        $this->assertTrue(Route::has('site.borrower.apply.asset-document'));
        $source = file_get_contents(app_path('Http/Controllers/Site/ApplyController.php'));
        $this->assertNotFalse($source);
        $upload = substr($source, (int) strpos($source, 'function uploadAssetDocument'), 1200);
        $this->assertStringNotContainsString('abortUnlessApplicationFeeAllowsProgress', $upload);
        $this->assertStringContainsString('payPostApprovalFees', file_get_contents(app_path('Http/Controllers/Site/BorrowerController.php')));
    }
}
