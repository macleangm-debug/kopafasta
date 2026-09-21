<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerAsset;
use App\Models\CustomerDisbursementAccount;
use App\Models\Setting;
use App\Models\User;
use App\Services\ProfileCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileCompletionEligibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::setMany([
            'kyc.require_income_proof' => false,
            'kyc.require_residence_letter' => false,
            'identity_verification.require_nida' => false,
            'identity_verification.require_facial' => false,
            'identity_verification.verification_stage' => 'underwriting',
        ]);
    }

    public function test_business_owner_reaches_100_without_collateral_and_assets_do_not_dilute(): void
    {
        $customer = $this->completeBusinessOwnerProfile();
        $svc = app(ProfileCompletionService::class);

        $before = $svc->calculate($customer);
        $this->assertSame(100, $before['percent'], json_encode($before));
        $this->assertTrue($svc->isFullyComplete($customer));
        $this->assertTrue($svc->isActivityFieldsComplete($customer));
        $this->assertFalse((bool) ($svc->tabStatuses($customer)['assets']['required'] ?? true));
        $this->assertNotContains('kyc', collect($before['sections'])->pluck('key')->all());
        $this->assertNotContains('assets', collect($before['sections'])->pluck('key')->all());

        $summary = $svc->completionSummary($customer);
        $this->assertSame(100, $summary['percent']);
        $this->assertSame(0, $summary['remaining_count']);
        $this->assertSame([], $summary['actionable']);

        CustomerAsset::query()->create([
            'customer_id' => $customer->id,
            'asset_type' => 'land',
            'label' => 'Optional plot',
            'is_active' => true,
            'photo_paths' => ['assets/front.jpg'],
            'metadata' => [],
        ]);

        $after = $svc->calculate($customer->fresh());
        $this->assertSame(100, $after['percent']);
        $this->assertSame(
            collect($before['sections'])->pluck('key')->all(),
            collect($after['sections'])->pluck('key')->all()
        );
    }

    public function test_activity_gaps_list_applicable_business_owner_fields_only(): void
    {
        $customer = $this->baseCustomer([
            'activity_type' => 'business_owner',
            'employment_type' => 'business_owner',
            'income_range' => '500000_1000000',
            'activity_details' => ['business_name' => 'Kiosk'],
        ]);

        $gaps = collect(app(ProfileCompletionService::class)->activityGaps($customer))->pluck('key')->all();

        $this->assertContains('region', $gaps);
        $this->assertContains('district', $gaps);
        $this->assertContains('street', $gaps);
        $this->assertContains('employee_count', $gaps);
        $this->assertNotContains('employment_contract', $gaps);
        $this->assertNotContains('employer_name', $gaps);

        $summary = app(ProfileCompletionService::class)->completionSummary($customer);
        $this->assertLessThan(100, $summary['percent']);
        $this->assertNotEmpty($summary['actionable']);
        $this->assertTrue(
            collect($summary['actionable'])->contains(fn (array $item) => ($item['key'] ?? '') === 'region')
        );
    }

    public function test_employed_without_contract_blocks_activity_completion(): void
    {
        $customer = $this->completeBusinessOwnerProfile();
        $customer->forceFill([
            'activity_type' => 'employed',
            'employment_type' => 'employed',
            'activity_details' => [
                'employer_name' => 'Acme',
                'job_title' => 'Clerk',
            ],
            'income_range' => '500000_1000000',
        ])->save();

        $svc = app(ProfileCompletionService::class);
        $this->assertFalse($svc->isActivityFieldsComplete($customer->fresh()));
        $this->assertFalse($svc->isActivityComplete($customer->fresh()));
        $this->assertContains(
            'employment_contract',
            collect($svc->activityGaps($customer->fresh()))->pluck('key')->all()
        );
        $this->assertLessThan(100, $svc->calculate($customer->fresh())['percent']);
    }

    public function test_percent_below_100_always_has_actionable_gaps(): void
    {
        $customer = $this->baseCustomer([
            'activity_type' => 'business_owner',
            'activity_details' => ['business_name' => 'Partial'],
        ]);

        $summary = app(ProfileCompletionService::class)->completionSummary($customer);
        $this->assertLessThan(100, $summary['percent']);
        $this->assertGreaterThan(0, $summary['remaining_count']);
        foreach ($summary['actionable'] as $item) {
            $this->assertNotSame('', (string) ($item['label'] ?? ''));
            $this->assertNotEmpty($item['url'] ?? null);
        }
    }

    public function test_percent_moves_with_individual_requirements_not_only_full_categories(): void
    {
        Setting::setMany(['kyc.require_income_proof' => false]);

        $customer = $this->baseCustomer([
            'activity_type' => null,
            'employment_type' => null,
            'income_range' => null,
            'activity_details' => null,
            'region' => null,
            'district' => null,
            'street' => null,
            'lga_officer_name' => null,
            'lga_officer_position' => null,
            'lga_officer_phone' => null,
            'marital_status' => null,
            'number_of_children' => null,
            'nok_first_name' => null,
            'nok_last_name' => null,
            'nok_phone' => null,
            'nok_relationship' => null,
            'nok_region' => null,
            'nok_district' => null,
            'nok_street' => null,
        ]);

        $svc = app(ProfileCompletionService::class);
        $before = $svc->calculate($customer);
        $this->assertGreaterThan(0, $before['percent'], 'DOB/name already saved must move % above 0');
        $this->assertLessThan(100, $before['percent']);

        $gaps = collect($svc->sectionGaps($customer, 'personal'))->pluck('key')->all();
        $this->assertNotContains('dob', $gaps);
        $this->assertNotContains('name', $gaps);
    }

    public function test_activity_card_complete_independent_of_income_proof(): void
    {
        Setting::setMany(['kyc.require_income_proof' => true]);

        $customer = $this->completeBusinessOwnerProfile();
        $svc = app(ProfileCompletionService::class);

        $this->assertTrue($svc->isActivityFieldsComplete($customer));
        $this->assertFalse($svc->isActivityComplete($customer));
        $gapKeys = collect($svc->sectionGaps($customer, 'activity'))->pluck('key')->all();
        $this->assertNotEmpty($gapKeys);
        $this->assertNotContains('region', collect($svc->activityGaps($customer))->pluck('key')->all());
    }

    public function test_business_owner_tin_and_licence_follow_settings_and_skip_employed(): void
    {
        Setting::setMany([
            'kyc.require_tin' => true,
            'kyc.require_business_licence' => true,
        ]);

        $svc = app(ProfileCompletionService::class);
        $owner = $this->completeBusinessOwnerProfile();
        $ownerGaps = collect($svc->activityGaps($owner))->pluck('key')->all();

        $this->assertContains('tin_number', $ownerGaps);
        $this->assertContains('tin_certificate', $ownerGaps);
        $this->assertContains('licence_number', $ownerGaps);
        $this->assertContains('business_license', $ownerGaps);
        $this->assertLessThan(100, $svc->calculate($owner)['percent']);

        $employed = $this->baseCustomer([
            'activity_type' => 'employed',
            'employment_type' => 'employed',
            'income_range' => '500000_1000000',
            'activity_details' => [
                'employer_name' => 'Acme',
                'job_title' => 'Clerk',
            ],
        ]);
        $employedGaps = collect($svc->activityGaps($employed))->pluck('key')->all();
        $this->assertNotContains('tin_number', $employedGaps);
        $this->assertNotContains('business_license', $employedGaps);
    }

    public function test_satisfied_business_tin_restores_completion_when_licence_is_off(): void
    {
        Setting::set('kyc.require_tin', true);

        $owner = $this->completeBusinessOwnerProfile();
        $details = $owner->activity_details;
        $details['tin_number'] = '123456789';
        $owner->forceFill(['activity_details' => $details])->save();

        $type = \App\Models\DocumentType::create([
            'code' => 'tin_certificate',
            'name' => 'TIN certificate',
            'is_active' => true,
        ]);
        \App\Models\CustomerDocument::create([
            'customer_id' => $owner->id,
            'document_type_id' => $type->id,
            'loan_application_id' => null,
            'file_path' => 'kyc/tin.pdf',
            'status' => 'pending_review',
        ]);

        $svc = app(ProfileCompletionService::class);
        $gaps = collect($svc->activityGaps($owner->fresh()))->pluck('key')->all();
        $this->assertNotContains('tin_number', $gaps);
        $this->assertNotContains('tin_certificate', $gaps);
        $this->assertSame(100, $svc->calculate($owner->fresh())['percent']);
    }

    public function test_policy_notice_is_pre_screening_only(): void
    {
        Setting::setMany([
            'kyc.require_tin' => true,
            'kyc.policy_changed_at' => now()->toIso8601String(),
        ]);

        $owner = $this->completeBusinessOwnerProfile();
        $product = \App\Models\LoanProduct::create([
            'code' => 'IL-TIN-'.bin2hex(random_bytes(2)),
            'name' => 'TIN notice',
            'is_active' => true,
            'interest_rate' => 0.05,
            'min_amount' => 100000,
            'max_amount' => 5000000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
        ]);

        \App\Models\LoanApplication::create([
            'customer_id' => $owner->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-TIN-SCREEN',
            'requested_amount' => 500000,
            'requested_tenure_months' => 6,
            'status' => 'submitted',
            'current_stage' => 'screening',
            'submitted_at' => now()->subDay(),
        ]);

        $svc = app(ProfileCompletionService::class);
        $this->assertNull($svc->policyUpdateNotice($owner->fresh()));
        $this->assertLessThan(100, $svc->calculate($owner->fresh())['percent']);

        \App\Models\LoanApplication::create([
            'customer_id' => $owner->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-TIN-HOLD',
            'requested_amount' => 500000,
            'requested_tenure_months' => 6,
            'status' => 'awaiting_guarantor',
            'current_stage' => 'awaiting_guarantor',
            'submitted_at' => now()->subDay(),
        ]);

        $notice = $svc->policyUpdateNotice($owner->fresh());
        $this->assertNotNull($notice);
        $this->assertSame('Profile requirements updated', $notice['title']);
        $this->assertContains('tin_number', collect($notice['items'])->pluck('key')->all());
        $this->assertContains('tin_certificate', collect($notice['items'])->pluck('key')->all());
    }

    public function test_persisted_dob_within_profile_save_window_is_not_a_remaining_gap(): void
    {
        Setting::setMany([
            'kyc.min_age' => 18,
            'kyc.max_age' => 75, // lending setting must not stale-gap a Profile-persisted DOB
        ]);

        $customer = $this->baseCustomer([
            'date_of_birth' => '1940-01-31',
        ]);

        $svc = app(ProfileCompletionService::class);
        $validation = app(\App\Services\ProfileValidationService::class);

        $this->assertTrue($validation->dateOfBirthValid($customer->date_of_birth));
        $this->assertNotContains(
            'dob',
            collect($svc->sectionGaps($customer, 'personal'))->pluck('key')->all()
        );
        $progress = $svc->sectionProgress($customer, 'personal');
        $this->assertGreaterThan(0, $progress['done']);
    }

    /** @param  array<string, mixed>  $overrides */
    private function baseCustomer(array $overrides = []): Customer
    {
        $branch = Branch::create([
            'code' => 'PC'.bin2hex(random_bytes(3)),
            'name' => 'Profile Completion',
            'region' => 'Dar',
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'role' => 'customer',
            'email' => 'completion.'.uniqid().'@example.com',
        ]);

        return Customer::query()->create(array_merge([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'customer_number' => 'C'.random_int(100000, 999999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Uat',
            'last_name' => 'Borrower',
            'phone' => '2557'.random_int(10000000, 99999999),
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'marital_status' => 'single',
            'number_of_children' => 0,
            'nok_first_name' => 'Kin',
            'nok_last_name' => 'Person',
            'nok_phone' => '255711111111',
            'nok_relationship' => 'sibling',
            'nok_region' => 'Dar es Salaam',
            'nok_district' => 'Ilala',
            'nok_street' => 'Kin Street',
            'region' => 'Dar es Salaam',
            'district' => 'Ilala',
            'street' => 'Home Street',
            'lga_officer_name' => 'Officer',
            'lga_officer_position' => 'VEO',
            'lga_officer_phone' => '255722222222',
            'no_physical_nida_card' => true,
        ], $overrides));
    }

    private function completeBusinessOwnerProfile(): Customer
    {
        $customer = $this->baseCustomer([
            'activity_type' => 'business_owner',
            'employment_type' => 'business_owner',
            'income_range' => '500000_1000000',
            'activity_details' => [
                'business_name' => 'Uat Shop',
                'region' => 'Dar es Salaam',
                'district' => 'Ilala',
                'street' => 'Market Street',
                'employee_count' => '1',
            ],
        ]);

        CustomerDisbursementAccount::create([
            'customer_id' => $customer->id,
            'type' => 'mobile_money',
            'mobile_provider' => 'mpesa',
            'mobile_number' => '255700000099',
            'account_name' => 'Uat Borrower',
            'is_default' => true,
        ]);

        return $customer->fresh();
    }
}
