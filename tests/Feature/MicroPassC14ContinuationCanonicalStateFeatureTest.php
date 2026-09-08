<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\LoanApplicationDraft;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\ApplicationFeePaymentService;
use App\Services\ApplyFeeResumeService;
use Database\Seeders\PublicLoanProductsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * C1.4 — progress bar and rendered body must consume one canonical resolved state.
 */
class MicroPassC14ContinuationCanonicalStateFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function borrower(): Customer
    {
        $user = User::factory()->create(['role' => 'borrower', 'pin_hash' => bcrypt('1234')]);

        return Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-C14-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'C14',
            'last_name' => 'Borrower',
            'phone' => '25571'.random_int(1000000, 9999999),
            'country_code' => 'TZ',
            'membership_status' => 'active',
            'membership_issued_at' => now()->subMonth(),
            'membership_expires_at' => now()->addYear(),
        ]);
    }

    private function seedPaidDraft(Customer $customer, LoanProduct $product, string $draftRef, array $form, array $extra = []): void
    {
        LoanApplicationDraft::query()->updateOrCreate(
            ['customer_id' => $customer->id, 'loan_product_id' => $product->id],
            [
                'phase' => 'application',
                'step' => 0,
                'draft_reference' => $draftRef,
                'asset_reservation_id' => $extra['asset_reservation_id'] ?? null,
                'payload' => array_merge([
                    'form' => $form,
                    'step_key' => 'quote',
                    'group' => $extra['group'] ?? null,
                    'asset_substep' => $extra['asset_substep'] ?? null,
                ], $extra['payload'] ?? []),
                'saved_at' => now(),
            ]
        );

        CustomerPayment::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'payment_type' => 'application_fee',
            'payment_method' => 'mobile_money',
            'amount' => max(1, (int) $product->application_fee_amount),
            'currency' => 'TZS',
            'status' => 'paid',
            'reference' => 'PAY-C14-'.$draftRef,
            'paid_at' => now(),
            'provider_meta' => [
                'apply_context' => [
                    'loan_product_id' => $product->id,
                    'draft_reference' => $draftRef,
                    'next_step_key' => $extra['next_step_key'] ?? 'guarantor',
                ],
            ],
        ]);
    }

    private function assertCanonicalDraft(array $draft, string $expectedStepKey, string $label): void
    {
        $target = $draft['resume_target'] ?? [];
        $progress = $target['progress'] ?? [];

        $this->assertSame($expectedStepKey, $draft['step_key'] ?? null, "{$label} draft step_key");
        $this->assertSame($expectedStepKey, $target['step_key'] ?? null, "{$label} resume_target step_key");
        $this->assertSame($draft['step'] ?? null, $target['step'] ?? null, "{$label} step index agreement");
        $this->assertSame($target['step'] ?? null, $target['furthest_step'] ?? null, "{$label} furthest agrees with step");
        $this->assertSame($expectedStepKey, $progress['step_key'] ?? null, "{$label} progress.step_key");
        $this->assertSame($target['step'] ?? null, $progress['step'] ?? null, "{$label} progress.step");
        $this->assertSame($target['furthest_step'] ?? null, $progress['furthest_step'] ?? null, "{$label} progress.furthest");
        $this->assertSame(ApplyFeeResumeService::INTENT_PAID, $target['intent'] ?? null, "{$label} intent");
        $this->assertNotSame('quote', $expectedStepKey, "{$label} must leave Quote after paid");
    }

    public function test_seven_product_paid_resume_progress_and_body_agree(): void
    {
        $this->seed(PublicLoanProductsSeeder::class);
        $customer = $this->borrower();
        $fees = app(ApplicationFeePaymentService::class);
        $resume = app(ApplyFeeResumeService::class);

        $codes = ['IL', 'WL', 'EL', 'KB', 'AB', 'AL', 'GL'];
        foreach ($codes as $code) {
            $product = LoanProduct::query()->where('code', $code)->where('is_active', true)->first();
            $this->assertNotNull($product, $code);

            $form = [
                'loan_product_id' => $product->id,
                'requested_amount' => max(500_000, (int) $product->min_amount),
                'requested_tenure_months' => max(1, (int) $product->tenure_min_months),
                'purpose' => $product->fixedPurposeKey() ?: 'business_expansion',
            ];
            $extra = [];
            if ($code === 'AB') {
                $form['customer_asset_ids'] = [201];
                $form['asset_substep'] = 3;
                $extra['asset_substep'] = 3;
            }
            if ($code === 'GL') {
                $extra['group'] = [
                    'name' => 'C14 Group',
                    'purpose' => 'business_expansion',
                    'target_member_count' => 3,
                    'amount_per_member' => 200_000,
                    'members' => [
                        ['name' => 'Leader', 'role' => 'leader', 'phone' => $customer->phone],
                        ['name' => 'M2', 'role' => 'member', 'phone' => '255700000012'],
                        ['name' => 'M3', 'role' => 'member', 'phone' => '255700000013'],
                    ],
                ];
            }

            $next = $fees->nextStepAfterApplicationFee($customer, $product, ['form' => $form, 'group' => $extra['group'] ?? null]);
            $this->assertNotSame('quote', $next, $code.' next after fee');

            $this->seedPaidDraft($customer, $product, 'APP-C14-'.$code, $form, array_merge($extra, [
                'next_step_key' => $next,
            ]));

            $url = $resume->resumeUrlAfterFee($customer, $product, [
                'form' => $form,
                'group' => $extra['group'] ?? null,
                'asset_substep' => $extra['asset_substep'] ?? null,
                'draft_reference' => 'APP-C14-'.$code,
            ]);
            $this->assertStringContainsString('fee_return=paid', $url, $code);
            $this->assertStringContainsString('step_key='.$next, $url, $code);

            $this->actingAs($customer->user)
                ->get($url)
                ->assertOk()
                ->assertViewHas('savedDraft', function ($draft) use ($next, $code) {
                    $this->assertCanonicalDraft($draft, $next, $code);
                    if ($code === 'AB') {
                        $this->assertSame(3, (int) ($draft['asset_substep'] ?? $draft['resume_target']['asset_substep'] ?? 0));
                        $this->assertContains(201, $draft['form']['customer_asset_ids'] ?? []);
                    }
                    if ($code === 'GL') {
                        $this->assertSame('C14 Group', $draft['group']['name'] ?? null);
                    }

                    return true;
                });
        }
    }

    public function test_education_fixed_purpose_persisted_in_wizard_payload(): void
    {
        $this->seed(PublicLoanProductsSeeder::class);
        $customer = $this->borrower();
        $product = LoanProduct::query()->where('code', 'EL')->where('is_active', true)->first();
        $this->assertNotNull($product);
        $this->assertTrue($product->hasFixedPurpose());

        $payload = loan_product_wizard_payload($product);
        $this->assertSame('fixed', $payload['purpose_mode']);
        $this->assertSame('education', $payload['fixed_purpose']);

        // Agro shares the same locked-purpose rule; both must expose fixed purpose to the client.
        $agro = LoanProduct::query()->where('code', 'KB')->where('is_active', true)->first();
        $this->assertNotNull($agro);
        $agroPayload = loan_product_wizard_payload($agro);
        $this->assertSame('fixed', $agroPayload['purpose_mode']);
        $this->assertSame('agriculture', $agroPayload['fixed_purpose']);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.apply', ['product' => $product->id]))
            ->assertOk()
            ->assertSee(__('borrower.apply.quote.purpose_locked_hint'), false);
    }

    public function test_ab_cancel_back_to_quote_preserved(): void
    {
        $this->seed(PublicLoanProductsSeeder::class);
        $customer = $this->borrower();
        $ab = LoanProduct::query()->where('code', 'AB')->where('is_active', true)->first();
        $form = [
            'loan_product_id' => $ab->id,
            'customer_asset_ids' => [301],
            'requested_amount' => 1_000_000,
            'requested_tenure_months' => 6,
            'purpose' => 'working_capital',
            'asset_substep' => 3,
        ];
        LoanApplicationDraft::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $ab->id,
            'phase' => 'application',
            'step' => 0,
            'draft_reference' => 'APP-C14-AB-CANCEL',
            'payload' => [
                'form' => $form,
                'step_key' => 'asset_details',
                'asset_substep' => 3,
            ],
            'saved_at' => now(),
        ]);

        $cancelUrl = app(ApplyFeeResumeService::class)->cancelResumeUrl($customer, $ab, [
            'form' => $form,
            'asset_substep' => 3,
        ]);
        $this->assertStringContainsString('fee_return=cancel', $cancelUrl);
        $this->assertStringContainsString('step_key=asset_details', $cancelUrl);
        $this->assertStringContainsString('asset_substep=3', $cancelUrl);

        $this->actingAs($customer->user)
            ->get($cancelUrl)
            ->assertOk()
            ->assertViewHas('savedDraft', function ($draft) {
                return ($draft['step_key'] ?? null) === 'asset_details'
                    && (int) ($draft['asset_substep'] ?? 0) === 3;
            });
    }

    public function test_loan_products_mobile_category_uses_bottom_sheet_not_native_select(): void
    {
        $this->seed(PublicLoanProductsSeeder::class);
        $customer = $this->borrower();

        $html = $this->actingAs($customer->user)
            ->get(route('site.borrower.loan-products'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('id="loan-product-category"', $html);
        $this->assertStringNotContainsString('<select', $html);
        $this->assertStringContainsString('categoriesOpen', $html);
        $this->assertStringContainsString(__('borrower.loan_products_page.categories.individual'), $html);
        $this->assertStringContainsString(__('borrower.loan_products_page.categories.education'), $html);
    }

    public function test_wizard_shell_uses_wide_content_width(): void
    {
        $this->seed(PublicLoanProductsSeeder::class);
        $customer = $this->borrower();
        $product = LoanProduct::query()->where('code', 'IL')->where('is_active', true)->first();

        $html = $this->actingAs($customer->user)
            ->get(route('site.borrower.apply', ['product' => $product->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('max-w-3xl mx-auto', $html);
        // Shell content wrapper should remain wide (header unconstrained by narrow layout).
        $this->assertStringContainsString('max-w-7xl', $html);
    }
}
