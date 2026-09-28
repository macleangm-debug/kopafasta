<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerGuarantor;
use App\Models\Guarantor;
use App\Models\GuarantorInvitation;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Models\User;
use App\Services\CapacityAutoRejectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadyForScreeningStartFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('underwriting.enable_automatic_rejection', true);
        Setting::set('underwriting.enable_capacity_auto_reject', true);
        Setting::set('underwriting.capacity_auto_reject_delay_hours', 12);
    }

    public function test_ready_file_shows_one_start_screening_action_at_the_top(): void
    {
        [$admin, $ready] = $this->readyFile();

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', $ready))
            ->assertOk()
            ->assertSee(__('admin.intake.start_screening'), false)
            ->assertSee('Ready for Screening', false)
            ->assertSee(route('admin.loan-applications.start-screening', $ready), false)
            ->getContent();

        $this->assertSame(1, substr_count($html, 'name="confirmed"'));
        $this->assertLessThan(
            strpos($html, 'id="application-360"') + 4000,
            strpos($html, __('admin.intake.start_screening')),
            'Start Screening should sit in the Application 360 hero, not after a long scroll'
        );
        $this->assertStringNotContainsString('Acknowledge receipt', $html);
    }

    public function test_viewing_ready_file_or_wizard_url_does_not_start_screening(): void
    {
        [$admin, $ready] = $this->readyFile();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', $ready))
            ->assertOk();
        $this->assertSame('ready_for_screening', $ready->fresh()->current_stage);

        $redirect = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.guided-screening', $ready));
        $redirect->assertRedirect();
        $this->assertStringStartsWith(
            route('admin.loan-applications.show', $ready),
            (string) $redirect->headers->get('Location')
        );
        $this->assertSame('ready_for_screening', $ready->fresh()->current_stage);
        $this->assertSame('submitted', $ready->fresh()->status);
    }

    public function test_confirmed_start_moves_to_screening_and_opens_existing_wizard(): void
    {
        [$admin, $ready] = $this->readyFile();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.start-screening', $ready), ['confirmed' => '1'])
            ->assertRedirect(route('admin.loan-applications.guided-screening', $ready));

        $fresh = $ready->fresh();
        $this->assertSame('submitted', $fresh->status);
        $this->assertSame('screening', $fresh->current_stage);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.guided-screening', $fresh))
            ->assertOk()
            ->assertSee('Gate', false)
            ->assertSee('What happens next', false);
    }

    public function test_parked_and_awaiting_files_do_not_get_start_screening(): void
    {
        [$admin] = $this->readyFile();
        $parked = $this->parkedFile();
        $awaiting = $this->awaitingFile();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', $parked))
            ->assertOk()
            ->assertDontSee(route('admin.loan-applications.start-screening', $parked), false)
            ->assertDontSee(__('admin.intake.start_screening_confirm'), false);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', $awaiting))
            ->assertOk()
            ->assertDontSee(route('admin.loan-applications.start-screening', $awaiting), false);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.start-screening', $parked), ['confirmed' => '1'])
            ->assertSessionHasErrors('screening');
        $this->assertSame('initial_decision_hold', $parked->fresh()->current_stage);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.start-screening', $awaiting), ['confirmed' => '1'])
            ->assertSessionHasErrors('screening');
        $this->assertSame('awaiting_guarantor', $awaiting->fresh()->current_stage);
    }

    /** @return array{0: User, 1: LoanApplication} */
    private function readyFile(): array
    {
        [$admin, $product, $customer] = $this->people(2_000_000);
        $app = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'branch_id' => $admin->branch_id,
            'application_number' => 'APP-IL-LQU6',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 6,
            'status' => 'submitted',
            'current_stage' => 'ready_for_screening',
            'submitted_at' => now(),
            'screening_payload' => [
                'intake' => ['initial_gate' => ['result' => 'passed']],
            ],
        ]);

        return [$admin, $app];
    }

    private function parkedFile(): LoanApplication
    {
        [, $product, $customer] = $this->people(750_000, 'Parked');
        return LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-IL-FF24',
            'requested_amount' => 40_800_000,
            'requested_tenure_months' => 12,
            'status' => 'submitted',
            'current_stage' => 'initial_decision_hold',
            'submitted_at' => now(),
            'screening_payload' => [
                'intake' => [
                    'initial_gate' => [
                        'result' => 'failed',
                        'reason' => 'Borrower failed initial affordability. A guarantor cannot rescue this loan.',
                    ],
                ],
                'capacity_auto_reject' => [
                    'status' => CapacityAutoRejectService::STATUS_PENDING,
                    'parked_at' => now()->toIso8601String(),
                    'auto_reject_at' => now()->addHours(12)->toIso8601String(),
                ],
            ],
        ]);
    }

    private function awaitingFile(): LoanApplication
    {
        [, $product, $customer] = $this->people(2_000_000, 'Await');
        $app = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-IL-RZNT',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 6,
            'status' => 'awaiting_guarantor',
            'current_stage' => 'awaiting_guarantor',
            'submitted_at' => now(),
            'screening_payload' => [
                'intake' => ['initial_gate' => ['result' => 'passed']],
            ],
        ]);
        $contact = Guarantor::create([
            'first_name' => 'Lilian',
            'last_name' => 'Declined',
            'phone' => '25576'.random_int(1000000, 9999999),
            'relationship' => 'sibling',
        ]);
        $link = CustomerGuarantor::create([
            'customer_id' => $customer->id,
            'guarantor_id' => $contact->id,
            'loan_application_id' => $app->id,
            'status' => 'rejected',
        ]);
        GuarantorInvitation::create([
            'customer_id' => $customer->id,
            'customer_guarantor_id' => $link->id,
            'loan_application_id' => $app->id,
            'loan_product_id' => $product->id,
            'type' => 'external',
            'status' => 'declined',
            'contact' => $contact->phone,
            'invitee_name' => 'Lilian Declined',
            'token' => 'tok-rdy-'.random_int(10000, 99999),
        ]);

        return $app;
    }

    /** @return array{0: User, 1: LoanProduct, 2: Customer} */
    private function people(int $income, string $tag = 'Ready'): array
    {
        $branch = Branch::query()->first() ?? Branch::create([
            'code' => 'RD'.random_int(10, 99),
            'name' => 'Ready Branch',
            'region' => 'Dar',
            'is_active' => true,
        ]);
        $admin = User::query()->where('role', 'admin')->first() ?? User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        $product = LoanProduct::create([
            'code' => 'RD-'.random_int(100, 999),
            'name' => 'Ready Product',
            'is_active' => true,
            'interest_rate' => 0.18,
            'min_amount' => 100_000,
            'max_amount' => 50_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
            'requires_guarantor' => true,
        ]);
        $customer = Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-RD-'.random_int(1000, 9999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => $tag,
            'last_name' => 'Steward',
            'phone' => '25571'.random_int(1000000, 9999999),
            'monthly_income' => $income,
            'branch_id' => $branch->id,
        ]);

        return [$admin, $product, $customer];
    }
}
