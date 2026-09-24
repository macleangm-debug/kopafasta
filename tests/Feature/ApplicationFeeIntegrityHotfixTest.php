<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDraft;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\ApplicationFeeIntegrityService;
use App\Services\DuplicateMemberResolutionService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationFeeIntegrityHotfixTest extends TestCase
{
    use RefreshDatabase;

    private function product(): LoanProduct
    {
        return LoanProduct::create([
            'code' => 'IL',
            'name' => 'Individual Loan',
            'name_sw' => 'Mkopo wa Mdau',
            'category' => 'individual',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
            'requires_guarantor' => true,
            'application_fee_amount' => 10_000,
        ]);
    }

    private function borrower(string $suffix, array $overrides = []): Customer
    {
        $user = User::factory()->create([
            'role' => 'borrower',
            'is_active' => true,
            'phone' => '25571'.$suffix,
        ]);

        return Customer::create(array_merge([
            'user_id' => $user->id,
            'customer_number' => 'C-'.$suffix,
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Albert',
            'last_name' => 'Mtawa',
            'phone' => '25571'.$suffix,
            'national_id' => null,
        ], $overrides));
    }

    private function draft(Customer $customer, LoanProduct $product, string $reference, array $fee = []): LoanApplicationDraft
    {
        return LoanApplicationDraft::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'phase' => 'application',
            'step' => 0,
            'draft_reference' => $reference,
            'saved_at' => now(),
            'payload' => [
                'application_started' => true,
                'draft_reference' => $reference,
                'step_key' => 'guarantor',
                'form' => [
                    'loan_product_id' => $product->id,
                    'requested_amount' => 500_000,
                    'requested_tenure_months' => 6,
                    'purpose' => 'business',
                ],
                'application_fee' => $fee,
            ],
        ]);
    }

    private function feePayment(Customer $customer, LoanProduct $product, string $reference, string $draftRef, string $status, ?LoanApplication $application = null): CustomerPayment
    {
        return CustomerPayment::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'payment_type' => 'application_fee',
            'payment_method' => 'mobile_money',
            'amount' => 10_000,
            'currency' => 'TZS',
            'status' => $status,
            'reference' => $reference,
            'source_type' => $application ? LoanApplication::class : null,
            'source_id' => $application?->id,
            'paid_at' => $status === 'verified' ? now() : null,
            'verified_at' => $status === 'verified' ? now() : null,
            'provider_meta' => [
                'apply_context' => [
                    'draft_reference' => $draftRef,
                    'loan_product_id' => $product->id,
                    'next_step_key' => 'guarantor',
                ],
            ],
        ]);
    }

    public function test_pay_bufvzk_repairs_only_its_withdrawn_obligation_and_is_idempotent(): void
    {
        $product = $this->product();
        $canonical = $this->borrower('111111', ['national_id' => '20051012171030000124']);
        $duplicate = $this->borrower('222222');

        $paidApp = LoanApplication::create([
            'customer_id' => $canonical->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-IL-FF24',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 6,
            'status' => 'withdrawn',
            'current_stage' => 'awaiting_guarantor',
            'application_fee_status' => 'paid',
            'application_fee_reference' => 'PAY-BUFVZK',
            'rejection_reason' => 'Withdrawn by borrower',
            'submitted_at' => now()->subHour(),
        ]);

        $verified = $this->feePayment($canonical, $product, 'PAY-BUFVZK', 'APP-IL-FF24', 'verified');
        $this->draft($canonical, $product, 'APP-IL-J8JX', ['status' => 'initiated', 'reference' => 'PAY-TIFZFM']);
        $restartPay = $this->feePayment($canonical, $product, 'PAY-TIFZFM', 'APP-IL-J8JX', 'awaiting_payment');

        $otherApp = LoanApplication::create([
            'customer_id' => $canonical->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-FC-YH39',
            'requested_amount' => 200_000,
            'requested_tenure_months' => 3,
            'status' => 'draft',
            'current_stage' => 'quote',
            'application_fee_status' => 'unpaid',
        ]);

        $this->draft($duplicate, $product, 'APP-IL-P4K4', ['status' => 'initiated', 'reference' => 'PAY-CLHGMB']);
        $otherPay = $this->feePayment($duplicate, $product, 'PAY-CLHGMB', 'APP-IL-P4K4', 'awaiting_payment');

        $integrity = app(ApplicationFeeIntegrityService::class);
        $classified = $integrity->classify($verified);
        $this->assertSame('B', $classified['class']);
        $this->assertSame('APP-IL-FF24', $classified['application_number']);

        $dry = $integrity->repair($verified, false);
        $this->assertFalse($dry['repaired']);
        $this->assertContains('restore_withdrawn', $dry['plan']);
        $this->assertContains('discard_restart_draft:APP-IL-J8JX', $dry['plan']);
        $this->assertSame('withdrawn', $paidApp->fresh()->status);

        $first = $integrity->repair($verified, true);
        $this->assertTrue($first['repaired']);
        $this->assertSame('ok', $first['class']);

        $paidApp->refresh();
        $verified->refresh();
        $this->assertSame('awaiting_guarantor', $paidApp->status);
        $this->assertSame('paid', $paidApp->application_fee_status);
        $this->assertSame(LoanApplication::class, $verified->source_type);
        $this->assertSame($paidApp->id, (int) $verified->source_id);
        $this->assertNull(LoanApplicationDraft::query()->where('draft_reference', 'APP-IL-J8JX')->first());
        $this->assertSame('cancelled', $restartPay->fresh()->status);
        $this->assertSame('unpaid', $otherApp->fresh()->application_fee_status);
        $this->assertSame('awaiting_payment', $otherPay->fresh()->status);
        $this->assertSame(1, CustomerPayment::query()->where('reference', 'PAY-BUFVZK')->count());

        $second = $integrity->repair($verified->fresh(), true);
        $this->assertFalse($second['repaired']);
        $this->assertSame('ok', $second['class']);
        $this->assertSame(1, CustomerPayment::query()->where('reference', 'PAY-BUFVZK')->count());
        $this->assertSame('awaiting_guarantor', $paidApp->fresh()->status);
        $this->assertSame('unpaid', $otherApp->fresh()->application_fee_status);
    }

    public function test_audit_classifies_exact_pending_as_a_and_orphans_as_c(): void
    {
        $product = $this->product();
        $customer = $this->borrower('333333');
        $this->draft($customer, $product, 'APP-IL-LIVE', ['status' => 'initiated', 'reference' => 'PAY-LIVE1']);
        $pendingLinked = $this->feePayment($customer, $product, 'PAY-LIVE1', 'APP-IL-LIVE', 'verified');
        $orphan = $this->feePayment($customer, $product, 'PAY-ORPHAN', '', 'verified');
        $orphan->update(['provider_meta' => []]);

        $audit = app(ApplicationFeeIntegrityService::class)->audit();
        $this->assertSame('A', app(ApplicationFeeIntegrityService::class)->classify($pendingLinked)['class']);
        $this->assertSame('C', app(ApplicationFeeIntegrityService::class)->classify($orphan->fresh())['class']);
        $this->assertNotEmpty($audit['class_a']);
        $this->assertNotEmpty($audit['class_c']);

        $skipped = app(ApplicationFeeIntegrityService::class)->repair($orphan->fresh(), true);
        $this->assertFalse($skipped['repaired']);
        $this->assertSame('ambiguous', $skipped['skipped'] ?? null);
    }

    public function test_opposite_audit_finds_paid_application_without_verified_payment(): void
    {
        $product = $this->product();
        $customer = $this->borrower('444444');
        LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-IL-FAKE',
            'requested_amount' => 400_000,
            'requested_tenure_months' => 6,
            'status' => 'submitted',
            'current_stage' => 'submitted',
            'application_fee_status' => 'paid',
            'application_fee_reference' => 'PAY-MISSING',
        ]);

        $audit = app(ApplicationFeeIntegrityService::class)->audit();
        $this->assertCount(1, $audit['opposite']);
        $this->assertSame('APP-IL-FAKE', $audit['opposite'][0]['application_number']);
    }

    public function test_empty_duplicate_is_retired_and_history_duplicate_is_merged(): void
    {
        $product = $this->product();
        $canonical = $this->borrower('555555', ['national_id' => '19900101123456789012']);
        $empty = $this->borrower('666666');
        $history = $this->borrower('777777');

        $admin = User::factory()->create(['role' => 'super_admin']);
        $resolver = app(DuplicateMemberResolutionService::class);

        $this->assertSame('delete_empty', $resolver->impact($empty)['action']);
        $retired = $resolver->resolve($empty, $canonical, $admin, 'empty duplicate phone');
        $this->assertSame('inactive', $retired->status);
        $this->assertSame($canonical->id, (int) $retired->merged_into_customer_id);
        $this->assertFalse($empty->user->fresh()->is_active);
        $this->assertNotEmpty($retired->merge_snapshot);

        $app = LoanApplication::create([
            'customer_id' => $history->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-IL-DUP',
            'requested_amount' => 300_000,
            'requested_tenure_months' => 4,
            'status' => 'submitted',
            'current_stage' => 'submitted',
            'application_fee_status' => 'unpaid',
        ]);
        $pay = $this->feePayment($history, $product, 'PAY-DUP1', 'APP-IL-DUP', 'awaiting_payment', $app);
        $this->assertSame('merge', $resolver->impact($history)['action']);

        $merged = $resolver->resolve($history, $canonical, $admin, 'same person different phone');
        $this->assertSame($canonical->id, (int) $app->fresh()->customer_id);
        $this->assertSame($canonical->id, (int) $pay->fresh()->customer_id);
        $this->assertSame('inactive', $merged->status);
        $this->assertSame($canonical->id, (int) $merged->merged_into_customer_id);
        $this->assertSame('same person different phone', $merged->merge_reason);
    }

    public function test_merge_does_not_replace_canonical_paid_application_with_unpaid_draft(): void
    {
        $product = $this->product();
        $canonical = $this->borrower('888888');
        $duplicate = $this->borrower('999999');
        $admin = User::factory()->create(['role' => 'super_admin']);

        LoanApplication::create([
            'customer_id' => $canonical->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-IL-CANON',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 6,
            'status' => 'awaiting_guarantor',
            'current_stage' => 'awaiting_guarantor',
            'application_fee_status' => 'paid',
            'application_fee_reference' => 'PAY-CANON',
        ]);
        $this->draft($duplicate, $product, 'APP-IL-NOISE', ['status' => 'initiated', 'reference' => 'PAY-NOISE']);
        $noise = $this->feePayment($duplicate, $product, 'PAY-NOISE', 'APP-IL-NOISE', 'awaiting_payment');

        app(DuplicateMemberResolutionService::class)->resolve($duplicate, $canonical, $admin, 'keep paid spine');

        $this->assertNull(LoanApplicationDraft::query()->where('draft_reference', 'APP-IL-NOISE')->first());
        $this->assertSame('cancelled', $noise->fresh()->status);
        $this->assertSame($canonical->id, (int) $noise->fresh()->customer_id);
        $this->assertSame('paid', LoanApplication::query()->where('application_number', 'APP-IL-CANON')->value('application_fee_status'));
    }

    public function test_admin_resolve_duplicate_route_merges_and_national_id_cannot_be_reused(): void
    {
        $canonical = $this->borrower('101010', ['national_id' => '19800101123456789012']);
        $duplicate = $this->borrower('202020');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.show', $duplicate))
            ->assertOk()
            ->assertSee('Resolve duplicate account');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.customers.resolve-duplicate', $duplicate), [
                'canonical_customer_id' => $canonical->id,
                'reason' => 'Albert second phone',
            ])
            ->assertRedirect(route('admin.customers.show', $canonical));

        $this->assertSame($canonical->id, (int) $duplicate->fresh()->merged_into_customer_id);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.show', $duplicate->fresh()))
            ->assertOk()
            ->assertSee('Merged / inactive duplicate');
    }

    public function test_profile_rejects_national_id_already_on_another_member(): void
    {
        $canonical = $this->borrower('505050', ['national_id' => '19800101123456789012']);
        $other = $this->borrower('606060');
        app(PinService::class)->setPin($other->user, '1234');

        $this->actingAs($other->user)
            ->from(route('site.borrower.profile', ['section' => 'personal']))
            ->put(route('site.borrower.profile.update', ['section' => 'personal']), [
                'focus' => 'identity',
                'national_id' => '19800101123456789012',
            ])
            ->assertSessionHasErrors('national_id');

        $this->assertSame(
            $canonical->id,
            (int) app(DuplicateMemberResolutionService::class)
                ->otherCustomerWithNationalId('19800101123456789012', (int) $other->id)
                ?->id
        );
    }

    public function test_reconcile_command_dry_run_does_not_write(): void
    {
        $product = $this->product();
        $customer = $this->borrower('303030');
        $app = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-IL-DRY',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 6,
            'status' => 'withdrawn',
            'current_stage' => 'awaiting_guarantor',
            'application_fee_status' => 'paid',
            'application_fee_reference' => 'PAY-DRY1',
        ]);
        $this->feePayment($customer, $product, 'PAY-DRY1', 'APP-IL-DRY', 'verified');

        $this->artisan('application-fees:reconcile', [
            '--reference' => 'PAY-DRY1',
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertSame('withdrawn', $app->fresh()->status);
    }
}
