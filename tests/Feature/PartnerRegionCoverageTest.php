<?php

namespace Tests\Feature;

use App\Models\Vendor;
use App\Services\PartnerRegionCoverage;
use App\Services\ScreeningPartnerAvailabilityService;
use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerRegionCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_nationwide_covers_any_region(): void
    {
        $partner = Vendor::query()->create([
            'name' => 'Nationwide CC',
            'category' => 'call_center',
            'status' => 'active',
            'vendor_number' => 'CC-NW',
            'phone' => '255700000401',
            'coverage_type' => 'nationwide',
            'regions' => [],
        ]);

        $coverage = app(PartnerRegionCoverage::class);

        $this->assertTrue($coverage->covers($partner, 'Dar es Salaam'));
        $this->assertTrue($coverage->covers($partner, 'Arusha'));
        $this->assertTrue($coverage->covers($partner, null));
    }

    public function test_regional_partner_only_matches_listed_region(): void
    {
        $partner = Vendor::query()->create([
            'name' => 'DSM Valuer',
            'category' => 'valuer',
            'status' => 'active',
            'vendor_number' => 'VL-DSM',
            'phone' => '255700000402',
            'coverage_type' => 'regions',
            'regions' => ['Dar es Salaam'],
        ]);

        $coverage = app(PartnerRegionCoverage::class);

        $this->assertTrue($coverage->covers($partner, 'Dar es Salaam'));
        $this->assertFalse($coverage->covers($partner, 'Arusha'));
        $this->assertFalse($coverage->covers($partner, null));
    }

    public function test_empty_regions_do_not_match_when_not_nationwide(): void
    {
        $partner = Vendor::query()->create([
            'name' => 'Unset Coverage',
            'category' => 'valuer',
            'status' => 'active',
            'vendor_number' => 'VL-EMPTY',
            'phone' => '255700000403',
            'coverage_type' => 'regions',
            'regions' => [],
        ]);

        $this->assertFalse(app(PartnerRegionCoverage::class)->covers($partner, 'Dar es Salaam'));
    }

    public function test_screening_availability_splits_available_and_unavailable(): void
    {
        $local = Vendor::query()->create([
            'name' => 'Local Valuer',
            'category' => 'valuer',
            'status' => 'active',
            'vendor_number' => 'VL-LOCAL',
            'phone' => '255700000404',
            'coverage_type' => 'regions',
            'regions' => ['Dar es Salaam'],
        ]);
        $other = Vendor::query()->create([
            'name' => 'Other Valuer',
            'category' => 'valuer',
            'status' => 'active',
            'vendor_number' => 'VL-OTHER',
            'phone' => '255700000405',
            'coverage_type' => 'regions',
            'regions' => ['Arusha'],
        ]);
        Vendor::query()->create([
            'name' => 'Nationwide Valuer',
            'category' => 'valuer',
            'status' => 'active',
            'vendor_number' => 'VL-NW',
            'phone' => '255700000406',
            'coverage_type' => 'nationwide',
            'regions' => [],
        ]);

        $product = LoanProduct::create([
            'code' => 'PL-RG-'.random_int(100, 999),
            'name' => 'Personal Loan',
            'is_active' => true,
            'interest_rate' => 0.18,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
            'application_fee_amount' => 20_000,
        ]);
        $customer = Customer::create([
            'customer_number' => 'CU-RG-'.random_int(1000, 9999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Region',
            'last_name' => 'Borrower',
            'phone' => '25571'.random_int(1000000, 9999999),
            'region' => 'Dar es Salaam',
        ]);
        $application = LoanApplication::query()->create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-RG-'.random_int(1000, 9999),
            'status' => 'under_review',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 6,
        ]);

        $result = app(ScreeningPartnerAvailabilityService::class)->forApplication($application);

        $availableIds = collect($result['available'])->where('type', 'valuer')->pluck('id')->all();
        $unavailableIds = collect($result['unavailable'])->where('type', 'valuer')->pluck('id')->all();

        $this->assertContains($local->id, $availableIds);
        $this->assertNotContains($other->id, $availableIds);
        $this->assertContains($other->id, $unavailableIds);
        $this->assertSame('Dar es Salaam', $result['region']);
    }
}
