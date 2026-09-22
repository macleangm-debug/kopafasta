<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Models\User;
use App\Services\CapacityAutoRejectService;
use App\Services\LoanAgreementService;
use App\Services\ScreeningReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoRejectionOperatorUiFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('underwriting.enable_automatic_rejection', true);
        Setting::set('underwriting.enable_capacity_auto_reject', true);
        Setting::set('underwriting.capacity_auto_reject_delay_hours', 12);
    }

    public function test_pending_capacity_park_dominates_readiness_status_not_review_in_progress(): void
    {
        $application = $this->parkedApplication();

        $readiness = app(ScreeningReadinessService::class)->forApplication(
            $application->fresh(['customer', 'product']),
            [
                'customer' => $application->customer,
                'affordability' => ['verdict' => 'fail'],
                'crb' => ['score' => null, 'recommendation' => 'refer'],
            ],
            null,
            [],
            User::factory()->create(['role' => 'admin']),
        );

        $this->assertTrue($readiness['pending_rejection'] ?? false);
        $this->assertSame('pending_rejection', $readiness['status']);
        $this->assertSame('Pending automatic rejection', $readiness['status_label']);
        $this->assertSame('pending_rejection', $readiness['decision_status']['state'] ?? null);
        $this->assertSame('Pending automatic rejection', $readiness['decision_status']['headline'] ?? null);
        $this->assertStringContainsString('re-evaluat', strtolower((string) ($readiness['detail'] ?? '')));
        $this->assertSame([], $readiness['next_steps'] ?? ['x']);
    }

    public function test_system_sorted_table_shows_full_borrower_name_link(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $application = $this->parkedApplication();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.pipeline.system-sorted'))
            ->assertOk()
            ->assertSee('Automatic rejections', false)
            ->assertSee('GateOne Uat', false)
            ->assertSee(route('admin.customers.show', $application->customer), false);
    }

    public function test_rejection_letter_uses_offer_identity_stamp_flags(): void
    {
        $application = $this->parkedApplication();
        app(CapacityAutoRejectService::class)->fireNow($application->fresh(['customer', 'product']));

        $letter = app(LoanAgreementService::class)->generateRejectionLetter(
            $application->fresh(['customer', 'product']),
            regenerate: true,
        );

        $this->assertSame('rejection_letter', $letter->document_type);
        $this->assertSame('rejection', $letter->snapshot['letter_kind'] ?? null);
        $this->assertTrue((bool) ($letter->snapshot['show_legal_stamp'] ?? false));
        $this->assertArrayHasKey('company_legal_name', $letter->snapshot ?? []);
        $this->assertArrayHasKey('company_signatory_name', $letter->snapshot ?? []);
        $this->assertArrayHasKey('company_stamp_path', $letter->snapshot ?? []);
    }

    public function test_opened_workspace_sticky_shows_pending_not_national_id_cta(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $application = $this->parkedApplication();

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'overview',
            ]))
            ->assertOk()
            ->assertSee('Pending automatic rejection', false)
            ->assertSee('No analyst action is required right now', false)
            ->assertSee('Waiting for scheduled re-evaluation', false);

        // Sticky / guided primary must not push ordinary NIDA while parked.
        $this->assertStringNotContainsString(
            'bg-brand-gold text-brand">National ID not provided',
            $html->getContent(),
        );
    }

    public function test_rejection_letter_preview_has_no_workflow_side_effects(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $application = $this->parkedApplication();
        $beforeStatus = $application->status;
        $beforePayload = $application->screening_payload;

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.rejection-letter.preview', $application));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));

        $fresh = $application->fresh();
        $this->assertSame($beforeStatus, $fresh->status);
        $this->assertSame(
            data_get($beforePayload, 'capacity_auto_reject.status'),
            data_get($fresh->screening_payload, 'capacity_auto_reject.status'),
        );
        $this->assertSame(0, \App\Models\LoanAgreement::query()
            ->where('loan_application_id', $application->id)
            ->where('document_type', 'rejection_letter')
            ->count());
    }

    public function test_staging_sim_make_due_then_fire_uses_scheduled_path(): void
    {
        if (! app()->environment(['local', 'testing', 'staging'])) {
            $this->markTestSkipped('Staging sim only.');
        }

        $admin = User::factory()->create(['role' => 'admin']);
        $application = $this->parkedApplication();
        $application->update(['application_number' => 'APP-UAT-CAPACITY-SIM-TEST']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.staging.capacity-auto-reject.make-due', $application))
            ->assertRedirect();

        $dueAt = data_get($application->fresh()->screening_payload, 'capacity_auto_reject.auto_reject_at');
        $this->assertNotNull($dueAt);
        $this->assertTrue(now()->gte(\Illuminate\Support\Carbon::parse($dueAt)));

        $this->actingAs($admin, 'admin')
            ->post(route('admin.staging.capacity-auto-reject.fire-due', $application->fresh()))
            ->assertRedirect();

        $fresh = $application->fresh();
        $this->assertSame('rejected', $fresh->status);
        $this->assertSame(
            CapacityAutoRejectService::STATUS_FIRED,
            data_get($fresh->screening_payload, 'capacity_auto_reject.status'),
        );
    }

    private function parkedApplication(): LoanApplication
    {
        $customer = Customer::create([
            'customer_number' => 'CU-PARK-UI',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'GateOne',
            'last_name' => 'Uat',
            'phone' => '255799000918',
            'monthly_income' => 100_000,
        ]);

        $product = LoanProduct::create([
            'code' => 'IL-PARK',
            'name' => 'Park UI',
            'is_active' => true,
            'interest_rate' => 0.05,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
        ]);

        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-PARK-UI-1',
            'requested_amount' => 2_000_000,
            'requested_tenure_months' => 6,
            'status' => 'submitted',
            'current_stage' => 'screening',
            'submitted_at' => now(),
        ]);

        $state = app(CapacityAutoRejectService::class)->evaluateAndPark($application->fresh(['customer', 'product']));
        $this->assertSame(CapacityAutoRejectService::STATUS_PENDING, $state['status'] ?? null);

        return $application->fresh(['customer', 'product']);
    }
}
