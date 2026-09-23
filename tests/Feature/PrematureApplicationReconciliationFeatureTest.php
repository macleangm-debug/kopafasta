<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDraft;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\BorrowerApplicationsDashboardService;
use App\Services\LoanApplicationDraftService;
use App\Services\PrematureApplicationReconciliationService;
use App\Services\ProfileCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrematureApplicationReconciliationFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_reclassify_moves_application_to_incomplete_without_losing_the_number_or_fee(): void
    {
        [$customer, $product, $application] = $this->heldApplication('APP-IL-TEST1');

        $this->mock(ProfileCompletionService::class, function ($mock) use ($customer): void {
            $mock->shouldReceive('completionSummary')->andReturn([
                'percent' => 95,
                'remaining' => ['Residence verification letter'],
                'completed' => [],
                'remaining_count' => 1,
                'actionable' => [[
                    'key' => 'residence_letter',
                    'label' => 'Residence verification letter',
                    'url' => '/borrower/profile/residence?focus=verification',
                ]],
            ]);
            $mock->shouldReceive('calculate')->andReturn([
                'percent' => 95,
                'sections' => [],
            ]);
            $mock->shouldReceive('isFullyComplete')->andReturn(false);
        });

        $results = app(PrematureApplicationReconciliationService::class)->reconcile(['APP-IL-TEST1']);
        $this->assertTrue($results[0]['ok']);

        $fresh = $application->fresh();
        $this->assertSame('draft', $fresh->status);
        $this->assertSame('draft', $fresh->current_stage);
        $this->assertSame('paid', $fresh->application_fee_status);
        $this->assertSame('FEE-TEST-1', $fresh->application_fee_reference);
        $this->assertNull($fresh->guarantor_deadline_at);

        $draft = LoanApplicationDraft::query()
            ->where('customer_id', $customer->id)
            ->where('loan_product_id', $product->id)
            ->first();
        $this->assertNotNull($draft);
        $this->assertSame('APP-IL-TEST1', $draft->draft_reference);
        $this->assertSame('paid', $draft->payload['application_fee']['status'] ?? null);

        $this->assertFalse(app(LoanApplicationDraftService::class)->isSubmittedSpine($customer, 'APP-IL-TEST1'));
        $this->assertSame(1, app(LoanApplicationDraftService::class)->countIncomplete());
        $this->assertSame(0, LoanApplication::query()->whereNotIn('status', LoanApplication::PRE_SUBMIT_STATUSES)->count());

        $rows = app(BorrowerApplicationsDashboardService::class)->applicationsForCustomer($customer);
        $this->assertCount(1, $rows);
        $this->assertTrue($rows[0]['is_draft']);
        $this->assertSame('APP-IL-TEST1', $rows[0]['application_number']);
        $this->assertSame(['Residence verification letter'], $rows[0]['missing_profile_items']);
        $this->assertStringContainsString('/borrower/apply', $rows[0]['action_url']);
        $this->assertFalse($rows[0]['profile_complete']);
    }

    public function test_existing_spine_draft_becomes_the_single_resumable_journey(): void
    {
        [$customer, $product, $application] = $this->heldApplication('APP-IL-ZR93');
        $draft = LoanApplicationDraft::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'phase' => 'application',
            'step' => 2,
            'draft_reference' => 'APP-IL-ZR93',
            'saved_at' => now(),
            'payload' => [
                'application_started' => true,
                'form' => ['requested_amount' => 500000],
            ],
        ]);

        $this->mock(ProfileCompletionService::class, function ($mock): void {
            $mock->shouldReceive('completionSummary')->andReturn([
                'percent' => 79,
                'actionable' => [
                    ['key' => 'nida_front', 'label' => 'NIDA front', 'url' => '/borrower/profile/personal'],
                ],
            ]);
            $mock->shouldReceive('calculate')->andReturn(['percent' => 79, 'sections' => []]);
        });

        app(PrematureApplicationReconciliationService::class)->reconcile(['APP-IL-ZR93']);

        $this->assertSame(1, LoanApplicationDraft::query()->where('customer_id', $customer->id)->count());
        $this->assertSame($draft->id, LoanApplicationDraft::query()->where('customer_id', $customer->id)->value('id'));
        $this->assertSame('APP-IL-ZR93', LoanApplicationDraft::query()->where('customer_id', $customer->id)->value('draft_reference'));
        $this->assertSame('draft', $application->fresh()->status);
        $this->assertSame(1, app(LoanApplicationDraftService::class)->countIncomplete());
    }

    /** @return array{0: Customer, 1: LoanProduct, 2: LoanApplication} */
    private function heldApplication(string $number): array
    {
        $user = User::factory()->create(['role' => 'borrower']);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-PRE-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Pre',
            'last_name' => 'Mature',
            'phone' => '25571'.random_int(1000000, 9999999),
        ]);
        $product = LoanProduct::create([
            'code' => 'IL',
            'name' => 'Individual Loan',
            'is_active' => true,
            'interest_rate' => 0.18,
            'min_amount' => 100000,
            'max_amount' => 5000000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
            'requires_guarantor' => true,
            'application_fee_amount' => 10000,
        ]);
        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => $number,
            'requested_amount' => 500000,
            'requested_tenure_months' => 6,
            'purpose' => 'business',
            'status' => 'awaiting_guarantor',
            'current_stage' => 'awaiting_guarantor',
            'submitted_at' => now()->subDays(2),
            'application_fee_amount' => 10000,
            'application_fee_status' => 'paid',
            'application_fee_reference' => 'FEE-TEST-1',
            'application_fee_channel' => 'mobile_money',
            'application_fee_paid_at' => now()->subDays(2),
            'guarantor_deadline_at' => now()->addDays(5),
        ]);

        return [$customer, $product, $application];
    }
}
