<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\Application360Presenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Application360FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_show_surfaces_application_360_with_canonical_next_action(): void
    {
        [$admin, $app] = $this->screeningFile();

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', $app))
            ->assertOk()
            ->assertSee('id="application-360"', false)
            ->assertSee('Needs Attention / Next Action', false)
            ->assertSee('People', false)
            ->assertSee('Journey / readiness', false)
            ->assertSee('Open Member 360', false)
            ->getContent();

        $this->assertStringContainsString($app->application_number, $html);
        $this->assertStringContainsString('Application 360', $html);
        $this->assertStringContainsString('What is missing?', $html);

        $panel = app(Application360Presenter::class)->forApplication($app, $admin);
        $this->assertNotSame('', (string) ($panel['next']['cta'] ?? ''));
        $this->assertNotSame('', (string) ($panel['next']['href'] ?? ''));
        $this->assertNotEmpty($panel['lifecycle']);
        $this->assertNotEmpty($panel['people']);
        $this->assertNotEmpty($panel['readiness']);
        $this->assertSame('screening', $panel['next']['source'] ?? null);
        $this->assertSame('Borrower', $panel['people'][0]['role'] ?? null);
    }

    public function test_application_360_keeps_member_link_separate_from_credit_file(): void
    {
        [$admin, $app] = $this->screeningFile();
        $panel = app(Application360Presenter::class)->forApplication($app, $admin);

        $this->assertStringContainsString('/admin/customers/', (string) $panel['member_url']);
        $this->assertStringContainsString('guided-screening', (string) ($panel['next']['href'] ?? ''));
    }

    /** @return array{0: User, 1: LoanApplication} */
    private function screeningFile(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $borrower = User::factory()->create(['role' => 'borrower']);
        $customer = Customer::create([
            'user_id' => $borrower->id,
            'customer_number' => 'CU-360-'.random_int(100, 999),
            'member_no' => 'M-360-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Steward',
            'last_name' => 'Amuli',
            'phone' => '25571'.random_int(1000000, 9999999),
            'national_id' => '19960815-12107-00005-21',
            'date_of_birth' => '1996-08-15',
            'nida_verification_status' => 'verified',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);
        $product = LoanProduct::query()->where('code', 'IL')->first()
            ?? LoanProduct::create([
                'code' => 'IL',
                'name' => 'Individual Loan',
                'category' => 'individual',
                'interest_rate' => 0.18,
                'min_amount' => 500_000,
                'max_amount' => 10_000_000,
                'tenure_min_months' => 3,
                'tenure_max_months' => 24,
                'is_active' => true,
                'status' => 'active',
                'application_fee_amount' => 10_000,
            ]);

        $app = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-360-'.random_int(1000, 9999),
            'status' => 'under_review',
            'current_stage' => 'screening',
            'requested_amount' => 2_000_000,
            'requested_tenure_months' => 12,
            'purpose' => 'business',
            'submitted_at' => now()->subDay(),
            'application_fee_status' => 'paid',
        ]);

        return [$admin, $app];
    }
}
