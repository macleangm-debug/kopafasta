<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\ApplicationEvidenceLockService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationEvidenceLockFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitted_application_locks_profile_sections_server_side(): void
    {
        [$borrower, $application] = $this->submittedBorrower();

        $lock = app(ApplicationEvidenceLockService::class);
        $this->assertTrue($lock->isProfileSectionLocked($borrower, 'kyc'));
        $this->assertTrue($lock->isProfileSectionLocked($borrower, 'personal'));
        $this->assertFalse($lock->isProfileSectionLocked($borrower, 'payment'));

        $this->actingAs($borrower->user)
            ->putJson(route('site.borrower.profile.update', 'kyc'), [
                'nida_number' => '19800101123456789012',
            ])
            ->assertStatus(423);

        unset($application);
    }

    public function test_return_for_correction_unlocks_only_selected_section(): void
    {
        [$borrower, $application] = $this->submittedBorrower();
        $admin = User::factory()->create(['role' => 'admin', 'roles' => ['admin']]);

        app(ApplicationEvidenceLockService::class)->returnForCorrection(
            $application,
            $admin,
            ApplicationEvidenceLockService::SECTION_IDENTITY,
            'NIDA image unclear',
        );

        $lock = app(ApplicationEvidenceLockService::class);
        $this->assertFalse($lock->isProfileSectionLocked($borrower->fresh(), 'kyc'));
        $this->assertTrue($lock->isProfileSectionLocked($borrower->fresh(), 'activity'));
        $this->assertSame(
            'identity_kyc',
            data_get($application->fresh()->screening_payload, 'return_for_correction.section')
        );
    }

    /** @return array{0: Customer, 1: LoanApplication} */
    private function submittedBorrower(): array
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');
        $borrower = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-LOCK-'.random_int(1000, 9999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Lock',
            'last_name' => 'Borrower',
            'phone' => '25573'.random_int(1000000, 9999999),
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);

        $product = LoanProduct::create([
            'code' => 'EM-LOCK-'.random_int(100, 999),
            'name' => 'Lock Product',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 2_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
            'requires_guarantor' => true,
        ]);

        $application = LoanApplication::create([
            'customer_id' => $borrower->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-LOCK-'.random_int(1000, 9999),
            'requested_amount' => 400_000,
            'requested_tenure_months' => 4,
            'status' => 'awaiting_guarantor',
            'current_stage' => 'awaiting_guarantor',
            'application_fee_status' => 'paid',
            'submitted_at' => now()->subDay(),
        ]);

        return [$borrower, $application];
    }
}
