<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerGuarantor;
use App\Models\CustomerPayment;
use App\Models\Guarantor;
use App\Models\GuarantorInvitation;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\GuarantorSupplementService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationViewGuarantorControlCentreFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitted_awaiting_guarantor_view_has_single_status_surface_and_wadhamini(): void
    {
        [$borrower, $application] = $this->awaitingGuarantorPair(incomplete: true);

        $html = $this->actingAs($borrower->user)
            ->get(route('site.borrower.application', $application))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($application->application_number, $html);
        $this->assertStringContainsString('guarantor-progress', $html);
        // Post-submit hero CTA that implied editing the loan must be gone.
        $this->assertStringNotContainsString(__('borrower.loan_profile.back_to_loan'), $html);
        // Duplicate status eyebrow from the old large status card must be gone.
        $this->assertStringNotContainsString(__('borrower.loan_profile.current_status'), $html);
        // Section O/C: Wadhamini owns awaiting-guarantor copy — no duplicate waiting card.
        $this->assertStringNotContainsString(__('borrower.intake.passed_title'), $html);
        $this->assertStringNotContainsString(__('borrower.intake.part_submitted'), $html);
        $this->assertStringContainsString(__('borrower.loan_profile.guarantor_pending_acceptance_title'), $html);
        // Section D: Mohamed-shaped pending is replacement-capable, not "Add another".
        $this->assertStringNotContainsString(__('borrower.guarantor_supplement.borrower_banner'), $html);
        $this->assertStringNotContainsString(__('borrower.apply.submit_step.supplement_title'), $html);
    }

    public function test_declined_guarantor_shows_choose_another_without_invite_actions(): void
    {
        [$borrower, $application, $link] = $this->awaitingGuarantorPair(incomplete: true);
        $link->update(['status' => 'rejected']);
        \App\Models\GuarantorInvitation::query()
            ->where('loan_application_id', $application->id)
            ->update(['status' => 'rejected']);

        $html = $this->actingAs($borrower->user)
            ->get(route('site.borrower.application', $application))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(__('borrower.loan_profile.guarantor_declined_title'), $html);
        $this->assertStringContainsString(__('borrower.guarantor_supplement.change_cta'), $html);
        $this->assertStringNotContainsString(__('borrower.loan_profile.guarantor_nudge_whatsapp'), $html);
        $this->assertStringNotContainsString(__('borrower.loan_profile.guarantor_nudge_copy'), $html);
        $this->assertStringNotContainsString(__('borrower.guarantor_supplement.borrower_banner'), $html);
        $this->assertStringNotContainsString(__('borrower.apply.guarantor_status.pending_acceptance'), $html);
        // Duplicate bottom waiting card removed.
        $this->assertEquals(1, substr_count($html, 'id="guarantor-progress"'));
    }

    public function test_incomplete_awaiting_guarantor_can_be_replaced_without_new_fee(): void
    {
        [$borrower, $application, $link] = $this->awaitingGuarantorPair(incomplete: true);

        $url = app(GuarantorSupplementService::class)
            ->startBorrowerChangeWhileHeld($application, $borrower);

        $this->assertStringContainsString('guarantor_supplement=1', $url);
        $this->assertSame('awaiting_guarantor', $application->fresh()->status);
        $this->assertSame('rejected', $link->fresh()->status);
        $this->assertSame('paid', $application->fresh()->application_fee_status);
        $this->assertSame(
            1,
            CustomerPayment::query()
                ->where('customer_id', $borrower->id)
                ->where('payment_type', 'application_fee')
                ->whereIn('status', ['verified', 'paid'])
                ->count()
        );
    }

    public function test_completed_guarantor_cannot_be_silently_replaced(): void
    {
        [$borrower, $application] = $this->awaitingGuarantorPair(incomplete: false);

        $this->assertFalse(
            app(GuarantorSupplementService::class)->borrowerMayReplaceIncompleteGuarantor($application)
        );

        $this->expectException(\InvalidArgumentException::class);
        app(GuarantorSupplementService::class)->startBorrowerChangeWhileHeld($application, $borrower);
    }

    /**
     * @return array{0: Customer, 1: LoanApplication, 2?: CustomerGuarantor}
     */
    private function awaitingGuarantorPair(bool $incomplete): array
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');
        $borrower = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-AV-'.random_int(1000, 9999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'View',
            'last_name' => 'Borrower',
            'phone' => '25571'.random_int(1000000, 9999999),
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);

        $product = LoanProduct::create([
            'code' => 'EM-AV-'.random_int(100, 999),
            'name' => 'Emergency View',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
            'requires_guarantor' => true,
        ]);

        $payment = CustomerPayment::create([
            'customer_id' => $borrower->id,
            'loan_product_id' => $product->id,
            'payment_type' => 'application_fee',
            'payment_method' => 'mobile_money',
            'amount' => 10_000,
            'currency' => 'TZS',
            'status' => 'verified',
            'reference' => 'PAY-AV-'.random_int(1000, 9999),
            'paid_at' => now()->subDay(),
            'verified_at' => now()->subDay(),
            'source_type' => null,
            'source_id' => null,
        ]);

        $application = LoanApplication::create([
            'customer_id' => $borrower->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-AV-'.random_int(1000, 9999),
            'requested_amount' => 600_000,
            'requested_tenure_months' => 6,
            'status' => 'awaiting_guarantor',
            'current_stage' => 'awaiting_guarantor',
            'application_fee_status' => 'paid',
            'application_fee_amount' => 10_000,
            'application_fee_reference' => $payment->reference,
            'application_fee_paid_at' => now()->subDay(),
            'submitted_at' => now()->subDay(),
            'guarantor_deadline_at' => now()->addDays(5),
        ]);

        $gUser = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($gUser, '1234');
        $gCustomer = Customer::create([
            'user_id' => $gUser->id,
            'customer_number' => 'CU-AV-G-'.random_int(1000, 9999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Old',
            'last_name' => 'Guarantor',
            'phone' => '25572'.random_int(1000000, 9999999),
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);

        $guarantor = Guarantor::create([
            'first_name' => $gCustomer->first_name,
            'last_name' => $gCustomer->last_name,
            'phone' => $gCustomer->phone,
            'relationship' => 'friend',
        ]);

        $link = CustomerGuarantor::create([
            'customer_id' => $borrower->id,
            'guarantor_id' => $guarantor->id,
            'loan_application_id' => $application->id,
            'status' => $incomplete ? 'pending' : 'approved',
        ]);

        GuarantorInvitation::create([
            'customer_id' => $borrower->id,
            'loan_application_id' => $application->id,
            'loan_product_id' => $product->id,
            'customer_guarantor_id' => $link->id,
            'guarantor_customer_id' => $gCustomer->id,
            'type' => 'internal',
            'channel' => 'whatsapp',
            'token' => 'av-'.random_int(10000, 99999),
            'short_code' => 'AV'.random_int(100, 999),
            'contact' => $gCustomer->phone,
            'invitee_name' => $gCustomer->first_name.' '.$gCustomer->last_name,
            'status' => $incomplete ? 'pending' : 'accepted',
            'expires_at' => now()->addDays(14),
            'responded_at' => $incomplete ? null : now()->subHour(),
        ]);

        return [$borrower, $application, $link];
    }
}
