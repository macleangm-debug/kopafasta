<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanAgreement;
use App\Models\LoanApplication;
use App\Models\LoanApplicationAsset;
use App\Models\LoanProduct;
use App\Models\Partner;
use App\Models\PartnerTask;
use App\Models\Setting;
use App\Models\User;
use App\Services\GuidedApprovalService;
use App\Services\LoanAgreementService;
use App\Services\PostApprovalNextActionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PostApprovalJourneyFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_hub_exposes_natural_language_timing_not_enums(): void
    {
        $admin = $this->admin();
        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.underwriting'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Must be completed before agreement', $html);
        $this->assertStringContainsString('Must be completed before disbursement', $html);
        $this->assertStringNotContainsString('BEFORE_CONTRACT', $html);
        $this->assertStringNotContainsString('BEFORE_DISBURSEMENT', $html);
    }

    public function test_settings_hub_saves_post_approval_condition_timing(): void
    {
        $admin = $this->admin();
        $payload = $this->underwritingPayload([
            'post_approval_conditions' => collect(PostApprovalNextActionService::defaultCatalog())
                ->values()
                ->map(function (array $row, int $i) {
                    if ($row['key'] === 'gps_installation') {
                        $row['timing'] = PostApprovalNextActionService::TIMING_BEFORE_CONTRACT;
                    }

                    return [
                        'key' => $row['key'],
                        'required' => $row['required'] ? '1' : '0',
                        'applies_to' => $row['applies_to'],
                        'responsible_party' => $row['responsible_party'],
                        'timing' => $row['timing'],
                        'deadline_days' => $row['deadline_days'] ?? '',
                        'blocking' => $row['blocking'] ? '1' : '0',
                        'customer_reminders' => $row['customer_reminders'] ? '1' : '0',
                    ];
                })
                ->all(),
        ]);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.settings.underwriting.save'), $payload)
            ->assertRedirect();

        $gps = collect(app(PostApprovalNextActionService::class)->catalog())
            ->firstWhere('key', 'gps_installation');
        $this->assertSame(PostApprovalNextActionService::TIMING_BEFORE_CONTRACT, $gps['timing'] ?? null);
    }

    public function test_contract_generation_is_blocked_until_before_agreement_conditions_are_met(): void
    {
        [$admin, $app] = $this->approvedCashFile();
        $app->update(['offer_status' => 'pending_borrower']);

        $ready = app(PostApprovalNextActionService::class)->contractReadiness($app->fresh());
        $this->assertFalse($ready['ready']);
        $this->assertSame('Agreement not ready', $ready['headline']);
        $this->assertStringContainsString('Offer accepted', $ready['detail']);

        try {
            app(LoanAgreementService::class)->generateLoanContract($app);
            $this->fail('Expected contract generation to be blocked.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Agreement not ready', collect($e->errors())->flatten()->first() ?? '');
        }
    }

    public function test_agreement_ready_after_offer_acceptance_when_no_fees_apply(): void
    {
        [$admin, $app] = $this->approvedCashFile();
        $app->update(['offer_status' => 'accepted']);

        $ready = app(PostApprovalNextActionService::class)->contractReadiness($app->fresh());
        $this->assertTrue($ready['ready']);
        $this->assertSame('Agreement ready', $ready['headline']);
    }

    public function test_gps_partner_handoff_moves_waiting_to_continue_post_approval(): void
    {
        Setting::set('underwriting.post_approval_conditions', collect(PostApprovalNextActionService::defaultCatalog())
            ->map(function (array $row) {
                if ($row['key'] === 'gps_installation') {
                    $row['applies_to'] = 'all';
                    $row['timing'] = PostApprovalNextActionService::TIMING_BEFORE_DISBURSEMENT;
                }

                return $row;
            })
            ->values()
            ->all());

        [$admin, $app] = $this->approvedCashFile();
        $app->update(['offer_status' => 'accepted']);

        $partner = Partner::create([
            'partner_number' => 'PTR-GPS-'.random_int(100, 999),
            'name' => 'GPS Test Installer',
            'category' => 'gps_installer',
            'status' => 'active',
        ]);
        PartnerTask::query()->create([
            'partner_id' => $partner->id,
            'loan_application_id' => $app->id,
            'task_type' => 'gps_install',
            'status' => 'assigned',
            'customer_name' => 'Guided Borrower',
        ]);

        $waiting = app(PostApprovalNextActionService::class)->forApplication($app->fresh());
        $this->assertSame(PostApprovalNextActionService::BUCKET_WAITING, $waiting['bucket']);
        $this->assertSame('gps_partner', $waiting['waiting_on']);
        $this->assertSame('gps_installation', $waiting['condition']['key'] ?? null);

        PartnerTask::query()
            ->where('loan_application_id', $app->id)
            ->update(['status' => 'completed', 'completed_at' => now()]);

        $after = app(PostApprovalNextActionService::class)->forApplication($app->fresh());
        $gps = collect($after['conditions'])->firstWhere('key', 'gps_installation');
        $this->assertTrue((bool) ($gps['complete'] ?? false));
        $this->assertNotSame('gps_installation', $after['condition']['key'] ?? null);
        $this->assertContains($after['cta_kind'], ['continue', 'start', 'waiting']);
    }

    public function test_management_list_and_wizard_share_the_same_next_action(): void
    {
        [$admin, $app] = $this->approvedCashFile();
        $app->update(['offer_status' => 'accepted']);

        $a = app(PostApprovalNextActionService::class)->forApplication($app);
        $b = app(GuidedApprovalService::class)->managementNext($app->fresh());
        $this->assertSame($a['cta'], $b['cta']);
        $this->assertSame($a['bucket'], $b['bucket']);
        $this->assertSame($a['contract_ready'], $b['contract_ready']);
    }

    public function test_secured_contract_snapshot_includes_collateral_and_enforcement_is_not_automatic(): void
    {
        [$admin, $app] = $this->approvedCashFile();
        $app->update(['offer_status' => 'accepted']);
        LoanApplicationAsset::create([
            'loan_application_id' => $app->id,
            'asset_type' => 'motorcycle',
            'description' => 'Honda Ace 125, red, chassis AB12CD',
            'market_value' => 3_000_000,
            'forced_sale_value' => 2_100_000,
            'gps_required' => true,
            'is_primary' => true,
        ]);

        $method = new \ReflectionMethod(LoanAgreementService::class, 'snapshotFromApplication');
        $snapshot = $method->invoke(app(LoanAgreementService::class), $app->fresh(['collateralAssets', 'customer', 'product']));

        $this->assertSame('Honda Ace 125, red, chassis AB12CD', $snapshot['collateral_description'] ?? null);
        $this->assertSame('motorcycle', $snapshot['collateral_asset_type'] ?? null);
        $this->assertNotEmpty($snapshot['collateral_market_value'] ?? null);

        $blade = file_get_contents(resource_path('views/pdf/loan-agreement/body.blade.php'));
        $this->assertStringContainsString('Immediate repossession is not automatic upon a single missed payment', $blade);
        $this->assertStringContainsString('pledged collateral', $blade);
    }

    public function test_signing_a_contract_does_not_activate_the_loan(): void
    {
        [$admin, $app] = $this->approvedCashFile();
        $app->update(['offer_status' => 'accepted']);
        $loan = Loan::create([
            'customer_id' => $app->customer_id,
            'loan_product_id' => $app->loan_product_id,
            'loan_application_id' => $app->id,
            'loan_number' => 'LN-PA-'.random_int(1000, 9999),
            'principal_amount' => 500_000,
            'approved_amount' => 500_000,
            'outstanding_balance' => 500_000,
            'interest_rate' => 0.15,
            'tenure_months' => 6,
            'status' => 'pending',
        ]);
        LoanAgreement::create([
            'loan_application_id' => $app->id,
            'customer_id' => $app->customer_id,
            'document_type' => 'loan_contract',
            'reference' => 'LC-PA-1',
            'status' => 'signed',
            'signed_at' => now(),
        ]);

        $this->assertSame('pending', $loan->fresh()->status);
        $this->assertNotSame('active', $loan->fresh()->status);
        $this->assertFalse(app(PostApprovalNextActionService::class)->forApplication($app->fresh(['loan']))['disbursement_ready']);
    }

    public function test_guided_post_approval_shows_start_not_management_review(): void
    {
        [$admin, $app] = $this->approvedCashFile();
        $app->update(['offer_status' => 'accepted']);
        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.guided-post-approval', $app))
            ->assertOk()
            ->assertSee('Post-approval')
            ->assertDontSee('Start Management Review', false)
            ->assertDontSee('BEFORE_CONTRACT', false);
        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.guided-post-approval', $app))
            ->getContent();
        $this->assertTrue(
            str_contains($html, 'Start Post-Approval')
            || str_contains($html, 'Continue Post-Approval')
            || str_contains($html, 'Waiting ·')
        );
    }

    /** @return array{0: User, 1: LoanApplication} */
    private function approvedCashFile(): array
    {
        $branch = Branch::create([
            'code' => 'PA'.random_int(1000, 9999),
            'name' => 'Post Approval Branch',
            'region' => 'Dar',
            'is_active' => true,
        ]);
        $admin = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        $product = LoanProduct::create([
            'code' => 'PA-'.random_int(100, 999),
            'name' => 'Post Approval Product',
            'is_active' => true,
            'interest_rate' => 0.18,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
        ]);
        $customer = Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-PA-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Post',
            'last_name' => 'Borrower',
            'phone' => '25571'.random_int(1000000, 9999999),
            'branch_id' => $branch->id,
            'monthly_income' => 2_000_000,
        ]);
        $app = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'branch_id' => $branch->id,
            'application_number' => 'APP-PA-'.random_int(1000, 9999),
            'requested_amount' => 500_000,
            'requested_tenure_months' => 6,
            'status' => 'approved',
            'current_stage' => 'approval',
            'offer_status' => 'pending_borrower',
            'approved_at' => now(),
        ]);

        return [$admin, $app];
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'pin_hash' => bcrypt('1234'),
        ]);
    }

    /** @param  array<string, mixed>  $overrides */
    private function underwritingPayload(array $overrides = []): array
    {
        return array_merge([
            'guarantor_invitation_expiry_days' => 14,
            'awaiting_guarantor_deadline_days' => 7,
            'document_request_default_due_days' => 7,
            'stage_sla_days' => 5,
            'default_rate_tier_count' => 4,
            'default_rate_discount_fraction' => 0.30,
            'capacity_auto_reject_delay_hours' => 12,
            'verified_capacity_auto_reject_delay_hours' => 6,
            'group_member_hard_fail_action' => 'replace_member',
            'guarantor_hard_fail_action' => 'replace',
            'guarantor_replacement_hours' => 48,
            'collateral_secure_decision_days' => 3,
            'insurance_expiry_buffer_months' => 2,
            'insurance_renewal_decision_days' => 5,
            'collateral_secure_grace_days' => 3,
            'collateral_insurance_rate_percent' => 3.5,
            'collateral_insurance_markup_percent' => 0,
            'disbursement_sla_working_days' => 2,
            'disbursement_fast_track_business_hours' => 12,
            'disbursement_fast_track_fee_amount' => 25000,
        ], $overrides);
    }
}
