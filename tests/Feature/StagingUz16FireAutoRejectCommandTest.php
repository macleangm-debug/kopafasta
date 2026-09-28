<?php

namespace Tests\Feature;

use App\Console\Commands\StagingUz16ParkUatCommand;
use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\NotificationLog;
use App\Models\Setting;
use App\Services\CapacityAutoRejectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StagingUz16FireAutoRejectCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_makes_only_uat_park_due_and_fires_scheduled_path(): void
    {
        Setting::set('underwriting.enable_automatic_rejection', true);
        Setting::set('underwriting.enable_capacity_auto_reject', true);
        Setting::set('underwriting.capacity_auto_reject_delay_hours', 12);

        $product = LoanProduct::create([
            'code' => 'IL-UAT-FIRE',
            'name' => 'Individual Loan',
            'is_active' => true,
            'interest_rate' => 0.05,
            'min_amount' => 100_000,
            'max_amount' => 50_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
        ]);

        $uat = $this->parked(StagingUz16ParkUatCommand::APPLICATION_NUMBER, $product, 'Asha', 'Uat Parked', 100_000, 2_000_000);
        $productionShaped = $this->parked('APP-IL-UZ16', $product, 'Elia', 'Mgonda', 100_000, 2_000_000);

        $this->artisan('staging:uz16-fire-auto-reject')->assertSuccessful();

        $this->assertSame('12', (string) Setting::get('underwriting.capacity_auto_reject_delay_hours'));
        $this->assertSame('rejected', $uat->fresh()->status);
        $this->assertSame('rejected', $uat->fresh()->current_stage);
        $this->assertSame('submitted', $productionShaped->fresh()->status);
        $this->assertTrue(app(CapacityAutoRejectService::class)->isPending($productionShaped->fresh()));
        $this->assertTrue(
            NotificationLog::query()
                ->where('customer_id', $uat->customer_id)
                ->where('template', 'application_rejected')
                ->exists()
        );
    }

    public function test_command_refuses_outside_staging_local_testing(): void
    {
        $this->app['env'] = 'production';
        $this->artisan('staging:uz16-fire-auto-reject')->assertFailed();
    }

    private function parked(
        string $number,
        LoanProduct $product,
        string $first,
        string $last,
        float $income,
        float $amount,
    ): LoanApplication {
        $customer = Customer::create([
            'customer_number' => 'CU-'.$number,
            'type' => 'individual',
            'status' => 'active',
            'first_name' => $first,
            'last_name' => $last,
            'phone' => '25571'.random_int(1000000, 9999999),
            'monthly_income' => $income,
        ]);
        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => $number,
            'requested_amount' => $amount,
            'requested_tenure_months' => 6,
            'status' => 'submitted',
            'current_stage' => 'screening',
            'submitted_at' => now(),
        ]);
        app(CapacityAutoRejectService::class)->evaluateAndPark($application->fresh(['customer', 'product']));

        return $application->fresh();
    }
}
