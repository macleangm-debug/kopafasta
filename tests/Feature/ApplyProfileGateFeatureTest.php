<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\ApplicationRequirementsService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplyProfileGateFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function borrower(array $overrides = []): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        return Customer::create(array_merge([
            'user_id'                  => $user->id,
            'customer_number'          => 'CU-GATE-'.random_int(100, 999),
            'type'                     => 'individual',
            'status'                   => 'active',
            'first_name'               => 'Gate',
            'last_name'                => 'Borrower',
            'phone'                    => '2557123490'.random_int(10, 99),
            'date_of_birth'            => now()->subYears(30)->toDateString(),
            'national_id'              => '19800101123456789012',
            'nida_verification_status' => 'verified',
            'membership_status'        => 'active',
            'membership_expires_at'    => now()->addYear(),
            'face_verification_status' => 'pending',
            'region'                   => null,
            'district'                 => null,
            'street'                   => null,
            'activity_type'            => null,
            'income_range'             => null,
        ], $overrides));
    }

    public function test_checklist_requires_granular_profile_sections(): void
    {
        $customer = $this->borrower();
        $checklist = app(ApplicationRequirementsService::class)->checklist($customer);

        $this->assertFalse($checklist['can_apply']);
        $this->assertNotNull(collect($checklist['items'])->firstWhere('key', 'residence'));
        $this->assertNotNull(collect($checklist['items'])->firstWhere('key', 'activity'));
        $this->assertNotNull(collect($checklist['items'])->firstWhere('key', 'kin'));
        $this->assertNotNull(collect($checklist['items'])->firstWhere('key', 'legal_signature'));
        $this->assertNull(collect($checklist['items'])->firstWhere('key', 'profile'));
        $this->assertStringContainsString('/borrower/profile', (string) $checklist['first_action_url']);
    }

    public function test_legal_signature_gates_can_apply(): void
    {
        $customer = $this->borrower([
            'region'                   => 'Dar es Salaam',
            'district'                 => 'Kinondoni',
            'street'                   => 'Samora Avenue',
            'activity_type'            => 'employed',
            'income_range'             => '500k_1m',
            'face_verification_status' => 'verified',
            'nok_first_name'           => 'Next',
            'nok_last_name'            => 'Kin',
            'nok_relationship'         => 'spouse',
            'nok_phone'                => '255712340099',
            'nok_region'               => 'Dar es Salaam',
            'nok_district'             => 'Kinondoni',
            'nok_street'               => 'Kin Street',
        ]);

        $checklist = app(ApplicationRequirementsService::class)->checklist($customer);
        $signatureItem = collect($checklist['items'])->firstWhere('key', 'legal_signature');

        $this->assertNotNull($signatureItem);
        $this->assertFalse($signatureItem['complete']);
        $this->assertStringContainsString('focus=signature', (string) $signatureItem['action_url']);
    }

    public function test_checklist_for_apply_appends_return_url(): void
    {
        $customer = $this->borrower([
            'region'   => 'Dar es Salaam',
            'district' => 'Kinondoni',
            'street'   => 'Samora Avenue',
        ]);
        $returnUrl = 'https://example.test/borrower/apply?product=1&resume=1&step_key=submit';

        $checklist = app(ApplicationRequirementsService::class)->checklistForApply($customer, $returnUrl);

        $this->assertNotNull($checklist['first_action_url']);
        $this->assertStringContainsString('return=', (string) $checklist['first_action_url']);
        $this->assertStringContainsString(urlencode($returnUrl), (string) $checklist['first_action_url']);
    }

    public function test_submit_keeps_borrower_on_submit_step_when_profile_incomplete(): void
    {
        $customer = $this->borrower();
        $product = LoanProduct::create([
            'code'              => 'IL-GATE-'.random_int(100, 999),
            'name'              => 'Gate Product',
            'is_active'         => true,
            'interest_rate'     => 0.15,
            'min_amount'        => 100_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
        ]);

        $response = $this->actingAs($customer->user)
            ->post(route('site.borrower.apply.submit'), [
                'loan_product_id'         => $product->id,
                'requested_amount'        => 100_000,
                'requested_tenure_months' => 3,
                'purpose'                 => 'business',
                'signer_name'             => 'Gate Borrower',
                'signature_data'          => 'data:image/png;base64,'.base64_encode('fake'),
                'consent'                 => '1',
            ]);

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertStringContainsString('/borrower/apply', $location);
        $this->assertStringContainsString('step_key=submit', $location);
        $this->assertStringContainsString('profile_gate=1', $location);
        $this->assertStringNotContainsString('/borrower/profile/', $location);
        $response->assertSessionHas('error');
        $response->assertSessionHas('show_profile_gate', true);
    }

    public function test_with_return_url_preserves_hash_fragment(): void
    {
        $service = app(ApplicationRequirementsService::class);
        $url = 'https://example.test/borrower/profile/personal?focus=kin#next-of-kin';
        $return = 'https://example.test/borrower/apply?resume=1&step_key=submit';

        $result = $service->withReturnUrl($url, $return);

        $this->assertStringEndsWith('#next-of-kin', $result);
        $this->assertStringContainsString('return=', $result);
        $this->assertStringContainsString('focus=kin', $result);
    }
}
