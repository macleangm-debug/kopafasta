<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Models\User;
use App\Services\GuarantorDeadlineService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuarantorDeadlineFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_deadline_days_default_to_seven(): void
    {
        $this->assertSame(7, app(GuarantorDeadlineService::class)->deadlineDays());
    }

    public function test_expire_stale_closes_awaiting_guarantor_applications(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-GD-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Gua',
            'last_name' => 'Deadline',
            'phone' => '2557123488'.random_int(10, 99),
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);
        $product = LoanProduct::create([
            'code' => 'IL-GD-'.random_int(100, 999),
            'name' => 'Deadline Product',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 500_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
        ]);
        $app = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-GD-'.random_int(1000, 9999),
            'requested_amount' => 500_000,
            'requested_tenure_months' => 3,
            'status' => 'awaiting_guarantor',
            'current_stage' => 'awaiting_guarantor',
            'submitted_at' => now()->subDays(10),
            'guarantor_deadline_at' => now()->subDay(),
            'purpose' => 'business',
        ]);

        $expired = app(GuarantorDeadlineService::class)->expireStale();

        $this->assertTrue($expired->contains('id', $app->id));
        $this->assertSame('expired', $app->fresh()->status);
        $this->assertSame('expired', $app->fresh()->current_stage);
    }

    public function test_underwriting_settings_persist_deadline_days(): void
    {
        Setting::set('underwriting.awaiting_guarantor_deadline_days', 10);
        $this->assertSame(10, app(GuarantorDeadlineService::class)->deadlineDays());
    }
}
