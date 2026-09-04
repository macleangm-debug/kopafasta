<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanApplicationDraft;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\BorrowerApplicationsDashboardService;
use App\Services\LoanApplicationProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase7FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_application_row_includes_view_and_continue_actions(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-P7-001',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Draft',
            'last_name' => 'Borrower',
            'phone' => '255712345688',
        ]);

        $product = LoanProduct::create([
            'code' => 'IL-P7',
            'name' => 'Personal Loan',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 2_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);

        LoanApplicationDraft::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'phase' => 'application',
            'step' => 1,
            'draft_reference' => 'DR-P7-001',
            'payload' => ['form' => ['requested_amount' => 500_000, 'requested_tenure_months' => 12]],
        ]);

        $rows = app(BorrowerApplicationsDashboardService::class)->applicationsForCustomer($customer);
        $draftRow = collect($rows)->firstWhere('is_draft', true);

        $this->assertNotNull($draftRow);
        $this->assertSame(__('borrower.applications_list.continue_application'), $draftRow['action_label']);
        $this->assertNotEmpty($draftRow['continue_url']);
        $this->assertNotEmpty($draftRow['preview_url']);
        $this->assertSame(__('borrower.applications_list.view_application'), $draftRow['preview_label']);
    }

    public function test_loan_profile_draft_includes_completion_snapshot(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-P7-002',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Snapshot',
            'last_name' => 'Borrower',
            'phone' => '255712345689',
        ]);

        $product = LoanProduct::create([
            'code' => 'IL-P7B',
            'name' => 'Business Loan',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 2_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);

        $draft = LoanApplicationDraft::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'phase' => 'application',
            'step' => 1,
            'draft_reference' => 'DR-P7-002',
            'payload' => [],
        ]);

        $profile = app(LoanApplicationProfileService::class)->forDraft($customer, $draft);

        $this->assertArrayHasKey('snapshot', $profile);
        $this->assertArrayHasKey('personal', $profile['snapshot']);
        $this->assertSame('Snapshot Borrower', $profile['snapshot']['personal']['name'] ?? null);
    }

    public function test_partner_portal_aliases_redirect(): void
    {
        $this->get('/partner/login')->assertRedirect('/login/partner');

        $this->get('/partner')->assertRedirect(route('site.partner.start'));
    }
}
