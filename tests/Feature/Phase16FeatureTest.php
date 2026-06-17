<?php

namespace Tests\Feature;

use App\Models\AssetReservation;
use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\MarketplaceAsset;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorTask;
use App\Services\AssetHandoverMilestoneService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase16FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_loan_product_localized_name_uses_swahili_when_locale_is_sw(): void
    {
        $product = LoanProduct::create([
            'code'              => 'IL-P16',
            'name'              => 'Business Loan',
            'name_sw'           => 'Mkopo wa Biashara',
            'is_active'         => true,
            'interest_rate'     => 0.15,
            'min_amount'        => 100_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);

        app()->setLocale('sw');
        $this->assertSame('Mkopo wa Biashara', $product->localizedName());

        app()->setLocale('en');
        $this->assertSame('Business Loan', $product->localizedName());
    }

    public function test_handover_milestones_include_gps_for_vehicle_asset_lending_application(): void
    {
        $product = LoanProduct::create([
            'code'              => 'AL',
            'name'              => 'Asset Lending',
            'is_active'         => true,
            'interest_rate'     => 0.155,
            'min_amount'        => 500_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);

        $customer = Customer::create([
            'customer_number' => 'CU-P16-001',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Asset',
            'last_name'       => 'Borrower',
            'phone'           => '255712345700',
        ]);

        $asset = MarketplaceAsset::create([
            'slug'                   => 'p16-truck',
            'title'                  => 'Isuzu Truck',
            'category'               => 'vehicle',
            'supplier_name'          => 'Supplier',
            'asset_value'            => 5_000_000,
            'supplier_deposit'       => 1_000_000,
            'customer_deposit'       => 1_100_000,
            'weekly_installment'     => 120_000,
            'max_tenure_months'      => 24,
            'insurance_expires_at'   => now()->addYear()->toDateString(),
            'availability_status'    => 'available',
            'is_active'              => true,
        ]);

        $application = LoanApplication::create([
            'customer_id'             => $customer->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-P16-001',
            'requested_amount'        => 3_000_000,
            'requested_tenure_months' => 12,
            'status'                  => 'approved',
            'current_stage'           => 'approval',
            'offer_status'            => 'accepted',
        ]);

        AssetReservation::create([
            'customer_id'          => $customer->id,
            'loan_application_id'  => $application->id,
            'marketplace_asset_id' => $asset->id,
            'status'               => 'post_approval_fees_paid',
            'deposit_amount'       => 1_100_000,
            'deposit_status'       => 'paid',
            'reservation_fee_status' => 'paid',
        ]);

        $result = app(AssetHandoverMilestoneService::class)->forApplication($application->fresh());

        $this->assertNotNull($result);
        $keys = collect($result['milestones'])->pluck('key')->all();
        $this->assertContains('gps_installed', $keys);
        $this->assertContains('insurance_active', $keys);
        $this->assertContains('registration_complete', $keys);
    }

    public function test_handover_milestones_mark_gps_complete_when_vendor_task_done(): void
    {
        $product = LoanProduct::create([
            'code'              => 'AL',
            'name'              => 'Asset Lending',
            'is_active'         => true,
            'interest_rate'     => 0.155,
            'min_amount'        => 500_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);

        $customer = Customer::create([
            'customer_number' => 'CU-P16-002',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'GPS',
            'last_name'       => 'Done',
            'phone'           => '255712345701',
        ]);

        $asset = MarketplaceAsset::create([
            'slug'                => 'p16-bike',
            'title'               => 'Motorbike',
            'category'            => 'motorcycle',
            'supplier_name'       => 'Supplier',
            'asset_value'         => 2_000_000,
            'supplier_deposit'    => 400_000,
            'customer_deposit'    => 500_000,
            'weekly_installment'  => 50_000,
            'max_tenure_months'   => 12,
            'insurance_expires_at'=> now()->addMonths(6)->toDateString(),
            'availability_status' => 'available',
            'is_active'           => true,
        ]);

        $application = LoanApplication::create([
            'customer_id'             => $customer->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-P16-002',
            'requested_amount'        => 1_500_000,
            'requested_tenure_months' => 12,
            'status'                  => 'approved',
            'current_stage'           => 'approval',
            'offer_status'            => 'accepted',
        ]);

        AssetReservation::create([
            'customer_id'          => $customer->id,
            'loan_application_id'  => $application->id,
            'marketplace_asset_id' => $asset->id,
            'status'               => 'post_approval_fees_paid',
            'deposit_amount'       => 500_000,
            'deposit_status'       => 'paid',
            'reservation_fee_status' => 'paid',
        ]);

        $vendor = Vendor::create([
            'vendor_number' => 'PTR-P16-GPS',
            'name'          => 'GPS Partner',
            'category'      => 'gps',
            'status'        => 'active',
            'phone'         => '255712345799',
        ]);

        VendorTask::create([
            'vendor_id'           => $vendor->id,
            'loan_application_id' => $application->id,
            'task_type'           => 'gps_install',
            'status'              => 'completed',
        ]);

        $result = app(AssetHandoverMilestoneService::class)->forApplication($application->fresh());
        $gps = collect($result['milestones'] ?? [])->firstWhere('key', 'gps_installed');

        $this->assertSame('completed', $gps['status'] ?? null);
    }

    public function test_profile_page_shows_member_card(): void
    {
        $user = User::factory()->create();

        Customer::create([
            'user_id'               => $user->id,
            'customer_number'       => 'CU-P16-003',
            'type'                => 'individual',
            'status'              => 'active',
            'first_name'          => 'Member',
            'last_name'           => 'Card',
            'phone'               => '255712345702',
            'membership_status'   => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);

        $this->actingAs($user)
            ->get(route('site.borrower.profile'))
            ->assertOk()
            ->assertSee(__('borrower.profile.member_active'), false)
            ->assertSee('CU-P16-003', false);
    }

    public function test_admin_loan_product_form_includes_swahili_name_field(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-products.create'))
            ->assertOk()
            ->assertSee('Name (Swahili)', false);
    }

    public function test_loan_profile_shows_handover_milestones_for_asset_lending_application(): void
    {
        $user = User::factory()->create();

        $customer = Customer::create([
            'user_id'         => $user->id,
            'customer_number' => 'CU-P16-004',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Handover',
            'last_name'       => 'UI',
            'phone'           => '255712345703',
        ]);

        $product = LoanProduct::create([
            'code'              => 'AL',
            'name'              => 'Asset Lending',
            'is_active'         => true,
            'interest_rate'     => 0.155,
            'min_amount'        => 500_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);

        $asset = MarketplaceAsset::create([
            'slug'                => 'p16-ui-truck',
            'title'               => 'Handover Truck',
            'category'            => 'vehicle',
            'supplier_name'       => 'Supplier',
            'asset_value'         => 4_000_000,
            'supplier_deposit'    => 800_000,
            'customer_deposit'    => 900_000,
            'weekly_installment'  => 90_000,
            'max_tenure_months'   => 18,
            'insurance_expires_at'=> now()->addYear()->toDateString(),
            'availability_status' => 'available',
            'is_active'           => true,
        ]);

        $application = LoanApplication::create([
            'customer_id'             => $customer->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-P16-UI',
            'requested_amount'        => 2_500_000,
            'requested_tenure_months' => 12,
            'status'                  => 'approved',
            'current_stage'           => 'approval',
            'offer_status'            => 'accepted',
        ]);

        AssetReservation::create([
            'customer_id'          => $customer->id,
            'loan_application_id'  => $application->id,
            'marketplace_asset_id' => $asset->id,
            'status'               => 'post_approval_fees_paid',
            'deposit_amount'       => 900_000,
            'deposit_status'       => 'paid',
            'reservation_fee_status' => 'paid',
        ]);

        $this->actingAs($user)
            ->get(route('site.borrower.application', $application->id))
            ->assertOk()
            ->assertSee(__('borrower.handover_milestones.title'), false)
            ->assertSee('Handover Truck', false);
    }
}
