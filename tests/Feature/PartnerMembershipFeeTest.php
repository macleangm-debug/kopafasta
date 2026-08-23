<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
use App\Services\PartnerMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerMembershipFeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_valuer_fee_uses_individual_and_company_amounts(): void
    {
        Setting::set('partners.membership', [
            'enabled' => true,
            'default_fee_amount' => 0,
            'categories_requiring_payment' => ['valuer' => true],
            'category_fees' => [
                'valuer' => [
                    'individual' => 1500,
                    'company' => 2000,
                ],
            ],
        ]);

        $service = app(PartnerMembershipService::class);

        $individual = Vendor::create([
            'user_id' => User::factory()->create(['role' => 'vendor'])->id,
            'vendor_number' => 'V-FEE-IND',
            'name' => 'Solo Valuer',
            'category' => 'valuer',
            'applicant_category' => 'individual',
            'status' => 'active',
        ]);

        $company = Vendor::create([
            'user_id' => User::factory()->create(['role' => 'vendor'])->id,
            'vendor_number' => 'V-FEE-CO',
            'name' => 'Valuer Ltd',
            'category' => 'valuer',
            'applicant_category' => 'company',
            'status' => 'active',
        ]);

        $this->assertSame(1500.0, $service->feeFor($individual));
        $this->assertSame(2000.0, $service->feeFor($company));
    }

    public function test_legacy_single_valuer_fee_still_applies_to_both_applicants(): void
    {
        Setting::set('partners.membership', [
            'enabled' => true,
            'default_fee_amount' => 0,
            'categories_requiring_payment' => ['valuer' => true],
            'category_fees' => ['valuer' => 15000],
        ]);

        $service = app(PartnerMembershipService::class);

        $individual = Vendor::create([
            'user_id' => User::factory()->create(['role' => 'vendor'])->id,
            'vendor_number' => 'V-FEE-LEGACY',
            'name' => 'Legacy Valuer',
            'category' => 'valuer',
            'applicant_category' => 'individual',
            'status' => 'active',
        ]);

        $this->assertSame(15000.0, $service->feeFor($individual));
    }
}
