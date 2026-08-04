<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerGuarantor;
use App\Models\Guarantor;
use App\Models\GuarantorInvitation;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\NotificationLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\GuarantorDeadlineService;
use App\Services\GuarantorSupplementService;
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
        [$borrower, $guarantorCustomer, $application, $link] = $this->heldApplicationPair(deadline: now()->subDay());

        $expired = app(GuarantorDeadlineService::class)->expireStale();

        $this->assertTrue($expired->contains('id', $application->id));
        $this->assertSame('expired', $application->fresh()->status);
        $this->assertSame('expired', $application->fresh()->current_stage);

        $this->assertTrue(
            NotificationLog::query()
                ->where('customer_id', $borrower->id)
                ->where('template', 'guarantor_deadline_expired')
                ->exists()
        );
        $this->assertTrue(
            NotificationLog::query()
                ->where('customer_id', $guarantorCustomer->id)
                ->where('template', 'guarantor_deadline_expired_guarantor')
                ->exists()
        );
        unset($link);
    }

    public function test_reminders_fire_for_seven_five_three_and_one_day_to_both_parties(): void
    {
        foreach ([7, 5, 3, 1] as $days) {
            NotificationLog::query()->delete();
            [$borrower, $guarantorCustomer, $application] = $this->heldApplicationPair(
                deadline: now()->addDays($days)->setTime(12, 0)
            );

            $sent = app(GuarantorDeadlineService::class)->sendReminders();

            $this->assertGreaterThanOrEqual(1, $sent);
            $this->assertTrue(
                NotificationLog::query()
                    ->where('customer_id', $borrower->id)
                    ->where('template', 'guarantor_deadline_reminder_'.$days)
                    ->exists(),
                "Borrower missing reminder for {$days} days"
            );
            $this->assertTrue(
                NotificationLog::query()
                    ->where('customer_id', $guarantorCustomer->id)
                    ->where('template', 'guarantor_deadline_reminder_'.$days.'_guarantor')
                    ->exists(),
                "Guarantor missing reminder for {$days} days"
            );
            unset($application);
        }
    }

    public function test_borrower_can_change_guarantor_while_held(): void
    {
        [$borrower, $guarantorCustomer, $application, $link] = $this->heldApplicationPair(deadline: now()->addDays(5));

        $url = app(GuarantorSupplementService::class)
            ->startBorrowerChangeWhileHeld($application, $borrower);

        $this->assertStringContainsString('guarantor_supplement=1', $url);
        $this->assertSame('awaiting_guarantor', $application->fresh()->status);
        $this->assertSame('rejected', $link->fresh()->status);
        $this->assertTrue(app(GuarantorSupplementService::class)->hasOpenRequest($application->fresh()));
        $this->assertNotNull($application->fresh()->guarantor_deadline_at);
        unset($guarantorCustomer);
    }

    public function test_underwriting_settings_persist_deadline_days(): void
    {
        Setting::set('underwriting.awaiting_guarantor_deadline_days', 10);
        $this->assertSame(10, app(GuarantorDeadlineService::class)->deadlineDays());
    }

    /**
     * @return array{0: Customer, 1: Customer, 2: LoanApplication, 3: CustomerGuarantor}
     */
    private function heldApplicationPair(\DateTimeInterface $deadline): array
    {
        $borrowerUser = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($borrowerUser, '1234');
        $borrower = Customer::create([
            'user_id' => $borrowerUser->id,
            'customer_number' => 'CU-GD-B-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Borrow',
            'last_name' => 'Deadline',
            'phone' => '2557123488'.random_int(10, 99),
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);

        $guarantorUser = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($guarantorUser, '1234');
        $guarantorCustomer = Customer::create([
            'user_id' => $guarantorUser->id,
            'customer_number' => 'CU-GD-G-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Gua',
            'last_name' => 'Rantor',
            'phone' => '2557133488'.random_int(10, 99),
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
            'requires_guarantor' => true,
        ]);

        $app = LoanApplication::create([
            'customer_id' => $borrower->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-GD-'.random_int(1000, 9999),
            'requested_amount' => 500_000,
            'requested_tenure_months' => 3,
            'status' => 'awaiting_guarantor',
            'current_stage' => 'awaiting_guarantor',
            'submitted_at' => now()->subDays(2),
            'guarantor_deadline_at' => $deadline,
            'purpose' => 'business',
        ]);

        $guarantorRecord = Guarantor::create([
            'first_name' => $guarantorCustomer->first_name,
            'last_name' => $guarantorCustomer->last_name,
            'phone' => $guarantorCustomer->phone,
            'relationship' => 'member',
        ]);

        $link = CustomerGuarantor::create([
            'customer_id' => $borrower->id,
            'guarantor_id' => $guarantorRecord->id,
            'loan_application_id' => $app->id,
            'status' => 'approved',
        ]);

        GuarantorInvitation::create([
            'customer_id' => $borrower->id,
            'loan_application_id' => $app->id,
            'loan_product_id' => $product->id,
            'customer_guarantor_id' => $link->id,
            'guarantor_customer_id' => $guarantorCustomer->id,
            'type' => 'internal',
            'channel' => 'in_app',
            'token' => 'gd-token-'.random_int(1000, 9999),
            'short_code' => 'GD'.random_int(100, 999),
            'contact' => $guarantorCustomer->phone,
            'status' => 'accepted',
            'expires_at' => now()->addDays(14),
        ]);

        return [$borrower, $guarantorCustomer, $app, $link];
    }
}
