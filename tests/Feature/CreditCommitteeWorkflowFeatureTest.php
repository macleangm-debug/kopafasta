<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDocumentRequest;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Models\User;
use App\Services\LoanApplicationWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditCommitteeWorkflowFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function branch(): Branch
    {
        return Branch::create([
            'code'      => 'CRC'.random_int(10, 99),
            'name'      => 'CRC Branch',
            'region'    => 'Dar',
            'is_active' => true,
        ]);
    }

    private function application(User $actor, array $overrides = []): LoanApplication
    {
        $branch = $actor->branch_id
            ? Branch::query()->findOrFail($actor->branch_id)
            : $this->branch();

        if (! $actor->branch_id) {
            $actor->forceFill(['branch_id' => $branch->id])->save();
        }

        $customer = Customer::create([
            'customer_number' => 'CU-CRC-'.random_int(100, 999),
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Committee',
            'last_name'       => 'Borrower',
            'phone'           => '25571234'.random_int(1000, 9999),
            'branch_id'       => $branch->id,
        ]);

        $product = LoanProduct::create([
            'code'              => 'IL-CRC-'.random_int(100, 999),
            'name'              => 'CRC Test Loan',
            'is_active'         => true,
            'interest_rate'     => 0.15,
            'min_amount'        => 100_000,
            'max_amount'        => 2_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
        ]);

        return LoanApplication::create(array_merge([
            'customer_id'             => $customer->id,
            'loan_product_id'         => $product->id,
            'branch_id'               => $branch->id,
            'application_number'      => 'APP-CRC-'.random_int(1000, 9999),
            'status'                  => 'pre_approved',
            'current_stage'           => 'pre_approval',
            'recommendation_type'     => 'counter',
            'recommended_amount'      => 200_000,
            'requested_amount'        => 250_000,
            'requested_tenure_months' => 6,
            'offer_status'            => null,
        ], $overrides));
    }

    public function test_committee_can_issue_offer_when_counter_recommended(): void
    {
        Setting::set('underwriting.enable_counter_offers', true);

        $branch = $this->branch();
        $committee = User::factory()->create([
            'role'      => 'credit_committee',
            'branch_id' => $branch->id,
        ]);
        $application = $this->application($committee);

        $actions = app(LoanApplicationWorkflowService::class)
            ->availableActions($application, $committee)
            ->pluck('key')
            ->all();

        $this->assertContains('issue_offer', $actions);
        $this->assertSame(
            'applications.pre_approve',
            LoanApplicationWorkflowService::ACTIONS['issue_offer']['permission']
        );
    }

    public function test_committee_can_final_approve_when_analyst_approved(): void
    {
        $branch = $this->branch();
        $committee = User::factory()->create([
            'role'      => 'credit_committee',
            'branch_id' => $branch->id,
        ]);
        $application = $this->application($committee, [
            'recommendation_type' => 'approve',
            'recommended_amount'  => 250_000,
        ]);

        $actions = app(LoanApplicationWorkflowService::class)
            ->availableActions($application, $committee)
            ->pluck('key')
            ->all();

        $this->assertContains('approve', $actions);
        $this->assertNotContains('issue_offer', $actions);
    }

    public function test_committee_can_validate_screening_approve(): void
    {
        $branch = $this->branch();
        $committee = User::factory()->create([
            'role'      => 'credit_committee',
            'branch_id' => $branch->id,
        ]);
        $application = $this->application($committee, [
            'recommendation_type' => 'approve',
            'recommended_amount'  => 250_000,
            'offer_status'        => null,
        ]);

        $actions = app(LoanApplicationWorkflowService::class)
            ->availableActions($application, $committee)
            ->pluck('key')
            ->all();

        $this->assertContains('validate_screening', $actions);
        $this->assertTrue(
            app(\App\Services\ApplicationOfferService::class)->canValidateScreening($application, $committee)
        );
    }

    public function test_complete_screening_is_hidden_from_available_actions(): void
    {
        $branch = $this->branch();
        $analyst = User::factory()->create([
            'role'      => 'credit_analyst',
            'branch_id' => $branch->id,
        ]);
        $application = $this->application($analyst, [
            'current_stage'       => 'screening',
            'status'              => 'under_review',
            'recommendation_type' => null,
        ]);

        $actions = app(LoanApplicationWorkflowService::class)
            ->availableActions($application, $analyst)
            ->pluck('key')
            ->all();

        $this->assertNotContains('complete_screening', $actions);
        $this->assertContains('submit_recommendation', $actions);
    }

    public function test_submit_recommendation_does_not_persist_when_requested_documents_are_open(): void
    {
        $branch = $this->branch();
        $analyst = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        $application = $this->application($analyst, [
            'current_stage' => 'screening',
            'status' => 'under_review',
            'recommendation_type' => null,
            'recommended_amount' => null,
        ]);

        foreach (['National ID (front)', 'Business licence', 'Proof of residence'] as $label) {
            LoanApplicationDocumentRequest::create([
                'loan_application_id' => $application->id,
                'requested_by' => $analyst->id,
                'label' => $label,
                'type' => 'document',
                'status' => 'pending',
            ]);
        }

        $this->actingAs($analyst, 'admin')
            ->from(route('admin.loan-applications.show', ['loan_application' => $application, 'workspace' => 'decision']))
            ->post(route('admin.loan-applications.workflow', $application), [
                'action' => 'submit_recommendation',
                'recommendation_type' => 'approve',
                'remarks' => 'Approved they are okay.',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('action');

        $application->refresh();
        $this->assertNull($application->recommendation_type);
        $this->assertNull($application->recommended_at);
        $this->assertSame('screening', $application->current_stage);
    }

    public function test_open_document_requests_are_listed_as_screening_blockers(): void
    {
        $branch = $this->branch();
        $analyst = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        $application = $this->application($analyst, [
            'current_stage' => 'screening',
            'status' => 'under_review',
            'recommendation_type' => null,
        ]);

        LoanApplicationDocumentRequest::create([
            'loan_application_id' => $application->id,
            'requested_by' => $analyst->id,
            'label' => 'Updated National ID',
            'type' => 'document',
            'status' => 'pending',
        ]);

        $blockers = app(LoanApplicationWorkflowService::class)->screeningDocumentBlockers($application);

        $this->assertTrue(collect($blockers)->contains(fn ($line) => str_contains((string) $line, 'Updated National ID')));
    }
}
