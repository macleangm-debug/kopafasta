<?php

namespace Tests\Feature;

use App\Models\AssetReservation;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\LoanApplicationDraft;
use App\Models\LoanProduct;
use App\Models\MarketplaceAsset;
use App\Models\User;
use App\Services\ApplicationFeePaymentService;
use App\Services\ApplyFeeResumeService;
use App\Services\LoanApplicationDraftService;
use Database\Seeders\PublicLoanProductsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * C1.5 — navigation must never delete AL/AB drafts; education bank-only destination.
 */
class MicroPassC15ContinuationClosureFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function borrower(): Customer
    {
        $user = User::factory()->create(['role' => 'borrower', 'pin_hash' => bcrypt('1234')]);

        return Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-C15-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'C15',
            'last_name' => 'Borrower',
            'phone' => '25571'.random_int(1000000, 9999999),
            'country_code' => 'TZ',
            'membership_status' => 'active',
            'membership_issued_at' => now()->subMonth(),
            'membership_expires_at' => now()->addYear(),
        ]);
    }

    private function marketplaceReservation(Customer $customer): AssetReservation
    {
        $asset = MarketplaceAsset::create([
            'slug' => 'c15-truck-'.random_int(1000, 9999),
            'title' => 'C15 Truck',
            'category' => 'vehicle',
            'supplier_name' => 'Supplier',
            'asset_value' => 10_000_000,
            'supplier_deposit' => 2_000_000,
            'customer_deposit' => 2_200_000,
            'weekly_installment' => 150_000,
            'max_tenure_months' => 24,
            'is_active' => true,
        ]);

        return AssetReservation::create([
            'customer_id' => $customer->id,
            'marketplace_asset_id' => $asset->id,
            'status' => 'application_started',
            'reservation_fee_amount' => 50_000,
            'reservation_fee_status' => 'paid',
            'deposit_amount' => 2_200_000,
            'deposit_status' => 'pending',
        ]);
    }

    public function test_al_back_to_quote_preserves_same_draft_and_reservation(): void
    {
        $this->seed(PublicLoanProductsSeeder::class);
        $customer = $this->borrower();
        $al = LoanProduct::query()->where('code', 'AL')->where('is_active', true)->first();
        $this->assertNotNull($al);
        $reservation = $this->marketplaceReservation($customer);

        $form = [
            'loan_product_id' => $al->id,
            'requested_amount' => 7_800_000,
            'requested_tenure_months' => 24,
            'purpose' => 'asset_financing',
        ];

        $draft = LoanApplicationDraft::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $al->id,
            'phase' => 'application',
            'step' => 0,
            'draft_reference' => 'APP-C15-AL',
            'asset_reservation_id' => $reservation->id,
            'payload' => [
                'form' => $form,
                'step_key' => 'asset_tenure',
                'asset_reservation_id' => $reservation->id,
            ],
            'saved_at' => now(),
        ]);
        $beforeId = $draft->id;
        $beforeRef = $draft->draft_reference;

        CustomerPayment::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $al->id,
            'payment_type' => 'application_fee',
            'payment_method' => 'mobile_money',
            'amount' => max(1, (int) $al->application_fee_amount),
            'currency' => 'TZS',
            'status' => 'paid',
            'reference' => 'PAY-C15-AL-BACK',
            'paid_at' => now(),
        ]);

        $cancelUrl = app(ApplyFeeResumeService::class)->cancelResumeUrl(
            $customer,
            $al,
            ['form' => $form, 'draft_reference' => $beforeRef],
            $reservation->id,
        );
        $this->assertStringContainsString('fee_return=cancel', $cancelUrl);
        $this->assertStringContainsString('reservation='.$reservation->id, $cancelUrl);

        $this->actingAs($customer->user)
            ->get($cancelUrl)
            ->assertOk()
            ->assertDontSee(__('borrower.policy.draft_no_longer_available'), false)
            ->assertViewHas('savedDraft', function ($saved) use ($beforeRef, $reservation) {
                return ($saved['draft_reference'] ?? null) === $beforeRef
                    && (int) ($saved['asset_reservation_id'] ?? 0) === (int) $reservation->id
                    && ($saved['step_key'] ?? null) === 'asset_tenure';
            });

        $after = app(LoanApplicationDraftService::class)->find($customer, $al->id);
        $this->assertNotNull($after);
        $this->assertSame($beforeId, $after->id);
        $this->assertSame($beforeRef, $after->draft_reference);
        $this->assertSame($reservation->id, (int) $after->asset_reservation_id);
    }

    public function test_al_verified_fee_preserves_same_draft_and_advances(): void
    {
        $this->seed(PublicLoanProductsSeeder::class);
        $customer = $this->borrower();
        $al = LoanProduct::query()->where('code', 'AL')->where('is_active', true)->first();
        $reservation = $this->marketplaceReservation($customer);
        $form = [
            'loan_product_id' => $al->id,
            'requested_amount' => 7_800_000,
            'requested_tenure_months' => 24,
            'purpose' => 'asset_financing',
        ];

        $draft = LoanApplicationDraft::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $al->id,
            'phase' => 'application',
            'step' => 0,
            'draft_reference' => 'APP-C15-AL-PAID',
            'asset_reservation_id' => $reservation->id,
            'payload' => [
                'form' => $form,
                'step_key' => 'asset_tenure',
                'asset_reservation_id' => $reservation->id,
            ],
            'saved_at' => now(),
        ]);
        $beforeId = $draft->id;

        CustomerPayment::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $al->id,
            'payment_type' => 'application_fee',
            'payment_method' => 'mobile_money',
            'amount' => max(1, (int) $al->application_fee_amount),
            'currency' => 'TZS',
            'status' => 'paid',
            'reference' => 'PAY-C15-AL-PAID',
            'paid_at' => now(),
        ]);

        $next = app(ApplicationFeePaymentService::class)->nextStepAfterApplicationFee($customer, $al, ['form' => $form]);
        $this->assertNotSame('quote', $next);
        $this->assertNotSame('asset_tenure', $next);

        $paidUrl = app(ApplyFeeResumeService::class)->resumeUrlAfterFee(
            $customer,
            $al,
            ['form' => $form, 'draft_reference' => 'APP-C15-AL-PAID'],
            $next,
            $reservation->id,
        );
        $this->assertStringContainsString('fee_return=paid', $paidUrl);
        $this->assertStringContainsString('reservation='.$reservation->id, $paidUrl);

        $this->actingAs($customer->user)
            ->get($paidUrl)
            ->assertOk()
            ->assertDontSee(__('borrower.policy.draft_no_longer_available'), false)
            ->assertViewHas('savedDraft', function ($saved) use ($next, $reservation) {
                return ($saved['draft_reference'] ?? null) === 'APP-C15-AL-PAID'
                    && ($saved['step_key'] ?? null) === $next
                    && (int) ($saved['asset_reservation_id'] ?? 0) === (int) $reservation->id;
            });

        $after = app(LoanApplicationDraftService::class)->find($customer, $al->id);
        $this->assertSame($beforeId, $after?->id);
        $this->assertSame($reservation->id, (int) $after?->asset_reservation_id);
    }

    public function test_al_autosave_without_reservation_id_does_not_detach(): void
    {
        $this->seed(PublicLoanProductsSeeder::class);
        $customer = $this->borrower();
        $al = LoanProduct::query()->where('code', 'AL')->where('is_active', true)->first();
        $reservation = $this->marketplaceReservation($customer);

        LoanApplicationDraft::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $al->id,
            'phase' => 'application',
            'step' => 0,
            'draft_reference' => 'APP-C15-AL-SAVE',
            'asset_reservation_id' => $reservation->id,
            'payload' => ['form' => ['requested_amount' => 1_000_000], 'step_key' => 'asset_tenure'],
            'saved_at' => now(),
        ]);

        $saved = app(LoanApplicationDraftService::class)->save($customer, [
            'phase' => 'application',
            'step' => 0,
            'step_key' => 'asset_tenure',
            'loan_product_id' => $al->id,
            'form' => [
                'loan_product_id' => $al->id,
                'requested_amount' => 1_000_000,
                'requested_tenure_months' => 12,
                'purpose' => 'asset_financing',
            ],
            // Intentionally omit asset_reservation_id — navigation must not wipe it.
        ]);

        $this->assertSame($reservation->id, (int) $saved->asset_reservation_id);
        $this->assertSame('APP-C15-AL-SAVE', $saved->draft_reference);
    }

    public function test_ab_back_and_paid_preserve_same_draft(): void
    {
        $this->seed(PublicLoanProductsSeeder::class);
        $customer = $this->borrower();
        $ab = LoanProduct::query()->where('code', 'AB')->where('is_active', true)->first();
        $form = [
            'loan_product_id' => $ab->id,
            'customer_asset_ids' => [501],
            'requested_amount' => 1_000_000,
            'requested_tenure_months' => 6,
            'purpose' => 'working_capital',
            'asset_substep' => 3,
        ];

        $draft = LoanApplicationDraft::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $ab->id,
            'phase' => 'application',
            'step' => 0,
            'draft_reference' => 'APP-C15-AB',
            'payload' => [
                'form' => $form,
                'step_key' => 'asset_details',
                'asset_substep' => 3,
            ],
            'saved_at' => now(),
        ]);
        $beforeId = $draft->id;

        CustomerPayment::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $ab->id,
            'payment_type' => 'application_fee',
            'payment_method' => 'mobile_money',
            'amount' => max(1, (int) $ab->application_fee_amount),
            'currency' => 'TZS',
            'status' => 'paid',
            'reference' => 'PAY-C15-AB',
            'paid_at' => now(),
        ]);

        $cancelUrl = app(ApplyFeeResumeService::class)->cancelResumeUrl($customer, $ab, [
            'form' => $form,
            'asset_substep' => 3,
            'draft_reference' => 'APP-C15-AB',
        ]);
        $this->actingAs($customer->user)->get($cancelUrl)->assertOk();
        $afterCancel = app(LoanApplicationDraftService::class)->find($customer, $ab->id);
        $this->assertSame($beforeId, $afterCancel?->id);
        $this->assertSame('APP-C15-AB', $afterCancel?->draft_reference);
        $this->assertSame(3, (int) ($afterCancel?->payload['asset_substep'] ?? 0));

        $next = app(ApplicationFeePaymentService::class)->nextStepAfterApplicationFee($customer, $ab, ['form' => $form]);
        $paidUrl = app(ApplyFeeResumeService::class)->resumeUrlAfterFee($customer, $ab, [
            'form' => $form,
            'draft_reference' => 'APP-C15-AB',
        ], $next);
        $this->actingAs($customer->user)
            ->get($paidUrl)
            ->assertOk()
            ->assertViewHas('savedDraft', fn ($d) => ($d['step_key'] ?? null) === $next
                && ($d['draft_reference'] ?? null) === 'APP-C15-AB'
                && in_array(501, $d['form']['customer_asset_ids'] ?? [], false));

        $afterPaid = app(LoanApplicationDraftService::class)->find($customer, $ab->id);
        $this->assertSame($beforeId, $afterPaid?->id);
    }

    public function test_education_details_is_bank_only_and_uses_document_holder(): void
    {
        $this->seed(PublicLoanProductsSeeder::class);
        $customer = $this->borrower();
        $el = LoanProduct::query()->where('code', 'EL')->where('is_active', true)->first();

        LoanApplicationDraft::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $el->id,
            'phase' => 'application',
            'step' => 1,
            'draft_reference' => 'APP-C15-EL',
            'payload' => [
                'form' => [
                    'loan_product_id' => $el->id,
                    'requested_amount' => 500_000,
                    'requested_tenure_months' => 6,
                    'purpose' => 'education',
                ],
                'step_key' => 'education_details',
                'institution_payment' => [
                    'method' => 'bank',
                    'bank_name' => 'CRDB',
                    'account_name' => 'School Account',
                    'account_number' => '0123456789',
                    'verified' => false,
                ],
            ],
            'saved_at' => now(),
        ]);

        CustomerPayment::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $el->id,
            'payment_type' => 'application_fee',
            'payment_method' => 'mobile_money',
            'amount' => max(1, (int) $el->application_fee_amount),
            'currency' => 'TZS',
            'status' => 'paid',
            'reference' => 'PAY-C15-EL',
            'paid_at' => now(),
        ]);

        $html = $this->actingAs($customer->user)
            ->get(route('site.borrower.apply', [
                'product' => $el->id,
                'resume' => 1,
                'step_key' => 'education_details',
                'fee_return' => 'paid',
            ]))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('method_mobile', $html);
        $this->assertStringNotContainsString('mobile_money', $html);
        $this->assertStringContainsString('singleImageDocumentUpload', $html);
        $this->assertStringContainsString(__('borrower.profile.view_document'), $html);
        $this->assertStringContainsString(__('borrower.apply.education_details.destination_unverified'), $html);
    }

    public function test_wizard_footer_continue_loading_label_exists(): void
    {
        $this->assertSame('Loading…', __('borrower.apply.loading'));
        $this->assertSame('Inapakia…', __('borrower.apply.loading', [], 'sw'));
    }
}
