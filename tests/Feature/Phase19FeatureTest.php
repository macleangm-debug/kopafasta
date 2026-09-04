<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\GuarantorInvitation;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\PartnerApplication;
use App\Models\User;
use App\Services\AdminAlertService;
use App\Services\ApplicationRequirementsService;
use App\Services\ApplicationTrackingShareService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase19FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_profile_payload_derives_kin_name_from_structured_fields(): void
    {
        $customer = Customer::create([
            'customer_number' => 'CU-P19-001',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Kin',
            'last_name' => 'Borrower',
            'phone' => '255712345806',
            'nok_first_name' => 'Jane',
            'nok_middle_name' => 'Mary',
            'nok_last_name' => 'Doe',
            'nok_relationship' => 'Spouse',
            'nok_phone' => '+255712345807',
            'nok_region' => 'Dar es Salaam',
            'nok_district' => 'Kinondoni',
            'activity_type' => 'employed',
            'income_range' => '500k_1m',
        ]);

        $payload = app(ApplicationRequirementsService::class)->submitProfilePayload($customer);

        $this->assertSame('Jane Mary Doe', $payload['nok_name']);
        $this->assertSame('Jane', $payload['nok_first_name']);
        $this->assertSame('Spouse', $payload['nok_relationship']);
    }

    public function test_combined_whatsapp_share_includes_tracking_and_guarantor_links(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-P19-002',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Share',
            'last_name' => 'Test',
            'phone' => '255712345808',
        ]);

        $product = LoanProduct::create([
            'code' => 'IL-P19-001',
            'name' => 'Individual Loan',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);

        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-P19-001',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 12,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $invitation = GuarantorInvitation::create([
            'customer_id' => $customer->id,
            'loan_application_id' => $application->id,
            'type' => 'external',
            'channel' => 'whatsapp',
            'token' => 'test-token-p19',
            'short_code' => 'P19TEST1',
            'contact' => '+255712345809',
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        $guarantorUrl = 'https://example.test/g/abc123';
        $url = app(ApplicationTrackingShareService::class)->combinedWhatsAppShareUrl(
            $application,
            $invitation,
            $guarantorUrl,
        );

        $this->assertStringStartsWith('https://wa.me/?text=', $url);
        $decoded = urldecode(substr($url, strlen('https://wa.me/?text=')));
        $this->assertStringContainsString('APP-P19-001', $decoded);
        $this->assertStringContainsString($guarantorUrl, $decoded);
    }

    public function test_admin_alerts_include_pending_affiliate_applications(): void
    {
        PartnerApplication::create([
            'type' => 'affiliate',
            'full_name' => 'Pending Affiliate',
            'email' => 'pending@example.com',
            'phone' => '+255712345810',
            'status' => 'pending',
        ]);

        $alerts = app(AdminAlertService::class)->alerts();

        $this->assertTrue($alerts->contains(fn (array $alert) => $alert['key'] === 'affiliate_applications' && $alert['count'] === 1));
    }

    public function test_apply_success_page_shows_combined_whatsapp_when_guarantor_pending(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-P19-003',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Success',
            'last_name' => 'Page',
            'phone' => '255712345811',
        ]);

        $product = LoanProduct::create([
            'code' => 'IL-P19-002',
            'name' => 'Test Product',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);

        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-P19-002',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 12,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        GuarantorInvitation::create([
            'customer_id' => $customer->id,
            'loan_application_id' => $application->id,
            'type' => 'external',
            'channel' => 'whatsapp',
            'token' => 'success-token-p19',
            'short_code' => 'P19TEST2',
            'contact' => '+255712345812',
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($user)
            ->get(route('site.borrower.apply.success', $application))
            ->assertOk()
            ->assertSee($application->application_number, false);
    }

    public function test_loan_profile_page_uses_wide_content_width(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-P19-004',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Loan',
            'last_name' => 'Profile',
            'phone' => '255712345813',
        ]);

        $product = LoanProduct::create([
            'code' => 'IL-P19-003',
            'name' => 'Profile Product',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);

        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-P19-003',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 12,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('site.borrower.application', $application))
            ->assertOk()
            ->assertSee('max-w-3xl', false);
    }
}
