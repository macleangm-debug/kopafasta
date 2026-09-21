<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDraft;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\BorrowerApplicationsDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BorrowerApplicationsListDraftVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function borrower(): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);

        return Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-IL-LIST-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'List',
            'last_name' => 'Borrower',
            'phone' => '2557188'.random_int(10000, 99999),
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);
    }

    private function product(string $code): LoanProduct
    {
        return LoanProduct::create([
            'code' => $code,
            'name' => $code === 'IL' ? 'Individual Loan' : $code.' Loan',
            'name_sw' => $code === 'IL' ? 'Mkopo wa Mdau' : $code,
            'category' => 'individual',
            'is_active' => true,
            'interest_rate' => 0.19,
            'min_amount' => 500_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
            'application_fee_amount' => 10_000,
        ]);
    }

    private function draft(Customer $customer, LoanProduct $product, string $reference): LoanApplicationDraft
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
                'step_key' => 'quote',
                'form' => [
                    'loan_product_id' => $product->id,
                    'requested_amount' => 500_000,
                    'requested_tenure_months' => 6,
                    'purpose' => 'business',
                ],
            ],
        ]);
    }

    public function test_individual_loan_draft_appears_beside_other_product_drafts(): void
    {
        $customer = $this->borrower();
        $il = $this->product('IL');
        $fc = $this->product('FC');
        $this->draft($customer, $il, 'APP-IL-LIST1');
        $this->draft($customer, $fc, 'APP-FC-LIST1');

        $rows = app(BorrowerApplicationsDashboardService::class)->applicationsForCustomer($customer);
        $numbers = collect($rows)->pluck('application_number')->all();

        $this->assertContains('APP-IL-LIST1', $numbers);
        $this->assertContains('APP-FC-LIST1', $numbers);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.loans', ['tab' => 'applications', 'view' => 'cards']))
            ->assertOk()
            ->assertSee('APP-IL-LIST1', false)
            ->assertSee('Mkopo wa Mdau', false);
    }

    public function test_new_individual_draft_shows_after_a_withdrawn_individual_application(): void
    {
        $customer = $this->borrower();
        $il = $this->product('IL');

        LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $il->id,
            'application_number' => 'APP-IL-OLD1',
            'status' => 'withdrawn',
            'current_stage' => 'screening',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 6,
            'submitted_at' => now()->subWeek(),
        ]);

        $this->draft($customer, $il, 'APP-IL-NEW1');

        $rows = app(BorrowerApplicationsDashboardService::class)->applicationsForCustomer($customer);
        $active = collect($rows)->firstWhere('application_number', 'APP-IL-NEW1');

        $this->assertNotNull($active);
        $this->assertTrue($active['is_draft']);
    }

    public function test_new_individual_draft_shows_after_a_disbursed_individual_application(): void
    {
        $customer = $this->borrower();
        $il = $this->product('IL');

        LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $il->id,
            'application_number' => 'APP-IL-PAID1',
            'status' => 'disbursed',
            'current_stage' => 'disbursement',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 6,
            'submitted_at' => now()->subMonths(2),
        ]);

        $this->draft($customer, $il, 'APP-IL-NEW2');

        $rows = app(BorrowerApplicationsDashboardService::class)->applicationsForCustomer($customer);

        $this->assertContains('APP-IL-NEW2', collect($rows)->pluck('application_number')->all());
        $this->assertNotContains('APP-IL-PAID1', collect($rows)->pluck('application_number')->all());
    }

    public function test_in_flight_individual_application_still_hides_the_duplicate_draft(): void
    {
        $customer = $this->borrower();
        $il = $this->product('IL');

        LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $il->id,
            'application_number' => 'APP-IL-LIVE1',
            'status' => 'submitted',
            'current_stage' => 'screening',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 6,
            'submitted_at' => now(),
        ]);

        $this->draft($customer, $il, 'APP-IL-DUP1');

        $rows = app(BorrowerApplicationsDashboardService::class)->applicationsForCustomer($customer);
        $numbers = collect($rows)->pluck('application_number')->all();

        $this->assertContains('APP-IL-LIVE1', $numbers);
        $this->assertNotContains('APP-IL-DUP1', $numbers);
    }

    public function test_successful_submission_cannot_resume_or_recreate_the_originating_draft(): void
    {
        $customer = $this->borrower();
        $il = $this->product('IL');
        $savedAt = now()->subHour();

        LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $il->id,
            'application_number' => 'APP-IL-ZR93',
            'status' => 'awaiting_guarantor',
            'current_stage' => 'awaiting_guarantor',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 6,
            'submitted_at' => now()->subHour(),
        ]);

        $origin = $this->draft($customer, $il, 'APP-IL-ZR93');
        $origin->forceFill(['saved_at' => $savedAt, 'updated_at' => $savedAt])->save();

        $drafts = app(\App\Services\LoanApplicationDraftService::class);

        $this->assertNull($drafts->find($customer, $il->id));
        $this->assertNull($drafts->payloadForWizard($customer, $il->id));

        $drafts->save($customer, [
            'phase' => 'application',
            'step' => 4,
            'loan_product_id' => $il->id,
            'draft_reference' => 'APP-IL-ZR93',
            'form' => ['requested_amount' => 900_000],
        ]);
        $drafts->saveApplicationFee($customer, $il->id, ['status' => 'paid', 'reference' => 'FEE-1']);

        $origin->refresh();
        $this->assertSame('APP-IL-ZR93', $origin->draft_reference);
        $this->assertEquals($savedAt->toDateTimeString(), $origin->saved_at->toDateTimeString());
        $this->assertSame(1, LoanApplicationDraft::query()->where('customer_id', $customer->id)->count());
        $this->assertSame(0, $drafts->countIncomplete());

        $drafts->save($customer, [
            'phase' => 'application',
            'step' => 1,
            'loan_product_id' => $il->id,
            'draft_reference' => 'APP-IL-ZR93',
        ]);

        $this->assertSame(1, LoanApplicationDraft::query()->where('customer_id', $customer->id)->count());
        $this->assertNull($drafts->find($customer, $il->id));

        $origin->delete();

        $drafts->save($customer, [
            'phase' => 'application',
            'step' => 2,
            'loan_product_id' => $il->id,
            'draft_reference' => 'APP-IL-ZR93',
            'form' => ['requested_amount' => 700_000],
        ]);

        $this->assertSame(0, LoanApplicationDraft::query()->where('customer_id', $customer->id)->count());
    }
}
