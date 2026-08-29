<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerGuarantor;
use App\Models\Guarantor;
use App\Models\GuarantorInvitation;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuarantorCreditReviewFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $branch = Branch::create([
            'code'      => 'GR'.random_int(10, 99),
            'name'      => 'Guarantor Review Branch',
            'region'    => 'Dar',
            'is_active' => true,
        ]);

        return User::factory()->create([
            'role'      => 'admin',
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
    }

    /** @return array{0: LoanApplication, 1: CustomerGuarantor, 2: Customer} */
    private function applicationWithGuarantor(User $actor, bool $profileComplete = false): array
    {
        $product = LoanProduct::create([
            'code'               => 'GR-'.random_int(100, 999),
            'name'               => 'Guarantor Product',
            'is_active'          => true,
            'interest_rate'      => 0.18,
            'min_amount'         => 100_000,
            'max_amount'         => 5_000_000,
            'tenure_min_months'  => 1,
            'tenure_max_months'  => 12,
            'requires_guarantor' => true,
        ]);

        $borrower = Customer::create([
            'user_id'         => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-GRB-'.random_int(100, 999),
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Borrower',
            'last_name'       => 'One',
            'phone'           => '25571'.random_int(1000000, 9999999),
            'branch_id'       => $actor->branch_id,
        ]);

        $guarantorCustomer = Customer::create([
            'user_id'                  => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number'          => 'CU-GRG-'.random_int(100, 999),
            'type'                     => 'individual',
            'status'                   => 'active',
            'first_name'               => 'Grace',
            'last_name'                => 'Guarantor',
            'phone'                    => '25576'.random_int(1000000, 9999999),
            'branch_id'                => $actor->branch_id,
            'nida_verification_status' => $profileComplete ? 'verified' : 'pending',
            'face_verification_status' => $profileComplete ? 'verified' : 'incomplete',
            'national_id'              => $profileComplete ? '19900101123456789012' : null,
            'date_of_birth'            => now()->subYears(35)->toDateString(),
            'gender'                   => 'female',
            'region'                   => 'Dar es Salaam',
            'district'                 => 'Ilala',
            'membership_status'        => 'active',
            'membership_expires_at'    => now()->addYear(),
        ]);

        $app = LoanApplication::create([
            'customer_id'             => $borrower->id,
            'loan_product_id'         => $product->id,
            'branch_id'               => $actor->branch_id,
            'application_number'      => 'APP-GR-'.random_int(1000, 9999),
            'requested_amount'        => 500_000,
            'requested_tenure_months' => 6,
            'status'                  => 'under_review',
            'current_stage'           => 'screening',
            'submitted_at'            => now(),
        ]);

        $contact = Guarantor::create([
            'first_name'   => 'Grace',
            'last_name'    => 'Guarantor',
            'phone'        => $guarantorCustomer->phone,
            'relationship' => 'sibling',
        ]);

        $link = CustomerGuarantor::create([
            'customer_id'         => $borrower->id,
            'guarantor_id'        => $contact->id,
            'loan_application_id' => $app->id,
            'status'              => 'approved',
        ]);

        GuarantorInvitation::create([
            'customer_id'            => $borrower->id,
            'customer_guarantor_id'  => $link->id,
            'loan_application_id'    => $app->id,
            'loan_product_id'        => $product->id,
            'guarantor_customer_id'  => $guarantorCustomer->id,
            'type'                   => 'member',
            'status'                 => 'accepted',
            'contact'                => $guarantorCustomer->phone,
            'invitee_name'           => 'Grace Guarantor',
            'token'                  => 'tok-gr-'.random_int(10000, 99999),
        ]);

        return [$app, $link, $guarantorCustomer];
    }

    public function test_screening_shows_guarantor_column_and_incomplete_profile_message(): void
    {
        $admin = $this->staff();
        [$app] = $this->applicationWithGuarantor($admin, profileComplete: false);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', $app))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Gate 3 — Credit bureau', $html);
        $this->assertStringContainsString('person=guarantor', $html);

        $decision = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', ['loan_application' => $app, 'workspace' => 'decision']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Borrower CRB', $decision);
        $this->assertStringContainsString('Open guarantor file', $decision);

        $tab = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $app,
                'workspace' => 'checklist',
                'person' => 'guarantor',
                'tab' => 'overview',
                'g' => $app->customerGuarantors()->first()?->id,
            ]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Waiting for guarantor profile', $tab);
        $this->assertStringContainsString('Ask borrower to change guarantor', $tab);
        $this->assertStringNotContainsString('Recall CRB', $tab);
        $this->assertStringContainsString('person=guarantor', $html);
    }

    public function test_admin_can_request_guarantor_change_without_blacklisting_person(): void
    {
        $admin = $this->staff();
        [$app, $link, $guarantorCustomer] = $this->applicationWithGuarantor($admin, profileComplete: false);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.guarantor-change', [$app, $link]), [
                'notes' => 'CRB too weak for this file',
            ])
            ->assertRedirect();

        $this->assertSame('rejected', $link->fresh()->status);
        $this->assertNotNull(data_get($app->fresh()->screening_payload, 'guarantor_supplement.requested_at'));
        $this->assertSame('change', data_get($app->fresh()->screening_payload, 'guarantor_supplement.kind'));
        // Guarantor customer account remains active / reusable
        $this->assertSame('active', $guarantorCustomer->fresh()->status);
    }

    public function test_dossier_and_guarantor_file_include_customer_assets(): void
    {
        $admin = $this->staff();
        [$app, $link, $guarantorCustomer] = $this->applicationWithGuarantor($admin, profileComplete: false);

        $borrowerAsset = \App\Models\CustomerAsset::create([
            'customer_id'         => $app->customer_id,
            'asset_type'          => 'vehicle',
            'label'               => 'Borrower Vitz',
            'registration_number' => 'B-100',
            'is_active'           => true,
        ]);

        $guarantorAsset = \App\Models\CustomerAsset::create([
            'customer_id'         => $guarantorCustomer->id,
            'asset_type'          => 'vehicle',
            'label'               => 'Vitz',
            'registration_number' => '1234',
            'is_active'           => true,
        ]);

        $review = app(\App\Services\LoanApplicationReviewService::class)->dossier($app->fresh([
            'customer.kyc',
            'product.requirements',
            'customerGuarantors.guarantor',
        ]));

        $this->assertTrue(
            collect($review['customer_assets'])->contains(fn ($a) => (int) $a->id === (int) $borrowerAsset->id)
        );
        $this->assertFalse(
            collect($review['customer_assets'])->contains(fn ($a) => (int) $a->id === (int) $guarantorAsset->id)
        );

        $method = new \ReflectionMethod(\App\Services\LoanApplicationReviewService::class, 'subjectFile');
        $guarantorFile = $method->invoke(
            app(\App\Services\LoanApplicationReviewService::class),
            $guarantorCustomer
        );

        $this->assertTrue(
            collect($guarantorFile['customer_assets'])->contains(fn ($a) => (int) $a->id === (int) $guarantorAsset->id)
        );
        $this->assertFalse(
            collect($guarantorFile['customer_assets'])->contains(fn ($a) => (int) $a->id === (int) $borrowerAsset->id)
        );
    }
}
