<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\MarketplaceAsset;
use App\Models\User;
use App\Services\AssetLendingService;
use App\Services\LoanApplicationReviewService;
use App\Services\LoanRateTierTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase4FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_insurance_status_flags_expired_policy(): void
    {
        $status = app(AssetLendingService::class)->insuranceStatus(now()->subDay());

        $this->assertSame('expired', $status['status']);
        $this->assertSame('red', $status['tone']);
    }

    public function test_rate_tier_generation_normalizes_percent_interest_rate(): void
    {
        $product = LoanProduct::create([
            'code'               => 'IL-P4',
            'name'               => 'Test',
            'is_active'          => true,
            'interest_rate'      => 19,
            'min_amount'         => 100_000,
            'max_amount'         => 10_000_000,
            'tenure_min_months'  => 3,
            'tenure_max_months'  => 24,
        ]);

        $tiers = app(LoanRateTierTemplateService::class)->tiersForProduct($product);

        $this->assertNotEmpty($tiers);
        $this->assertLessThanOrEqual(0.35, $tiers[0]['monthly_rate']);
    }

    public function test_underwriting_review_flags_asset_insurance_for_lending_application(): void
    {
        $product = LoanProduct::create([
            'code'               => 'AL',
            'name'               => 'Asset Lending',
            'is_active'          => true,
            'interest_rate'      => 0.155,
            'min_amount'         => 500_000,
            'max_amount'         => 5_000_000,
            'tenure_min_months'  => 3,
            'tenure_max_months'  => 24,
        ]);

        $customer = Customer::create([
            'customer_number' => 'CU-P4-001',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Asset',
            'last_name'       => 'Borrower',
            'phone'           => '255712345681',
        ]);

        $asset = MarketplaceAsset::create([
            'slug'                   => 'p4-truck',
            'title'                  => 'Isuzu Truck',
            'category'               => 'vehicle',
            'supplier_name'          => 'Supplier',
            'asset_value'            => 5_000_000,
            'supplier_deposit'       => 1_000_000,
            'deposit_markup_percent' => 10,
            'customer_deposit'       => 1_100_000,
            'weekly_installment'     => 120_000,
            'max_tenure_months'      => 24,
            'insurance_policy_number'=> 'POL-123',
            'insurance_expires_at'   => now()->subDays(3)->toDateString(),
            'is_active'              => true,
        ]);

        $application = LoanApplication::create([
            'customer_id'        => $customer->id,
            'loan_product_id'    => $product->id,
            'application_number' => 'APP-P4-001',
            'status'             => 'submitted',
            'current_stage'      => 'credit_appraisal',
            'requested_amount'   => 3_000_000,
            'requested_tenure_months' => 12,
        ]);

        \App\Models\AssetReservation::create([
            'customer_id'          => $customer->id,
            'loan_application_id'  => $application->id,
            'marketplace_asset_id' => $asset->id,
            'status'               => 'deposit_paid',
            'deposit_amount'       => 1_100_000,
            'deposit_status'       => 'paid',
        ]);

        $review = app(LoanApplicationReviewService::class)->dossier($application->fresh(['product', 'assetReservation.asset']));

        $this->assertSame('expired', $review['asset']['insurance_status']['status']);

        $insuranceItem = collect($review['checklist'])->firstWhere('key', 'asset_insurance');
        $this->assertNotNull($insuranceItem);
        $this->assertSame('blocked', $insuranceItem['status']);
    }

    public function test_public_member_verification_page_shows_active_member(): void
    {
        $user = User::factory()->create();

        Customer::create([
            'user_id'               => $user->id,
            'customer_number'       => 'CU-P4-002',
            'type'                => 'individual',
            'status'              => 'active',
            'first_name'          => 'Member',
            'last_name'           => 'Card',
            'phone'               => '255712345682',
            'member_no'           => 'KPF-TZ-TEST01',
            'membership_issued_at'=> now()->subMonth(),
            'membership_expires_at'=> now()->addMonths(11),
        ]);

        $response = $this->get(route('site.short.member', ['memberNo' => 'KPF-TZ-TEST01']));

        $response->assertOk();
        $response->assertSee('MEMBER CARD', false);
        $response->assertSeeText(__('site.member_verify.verified_badge'));
        $response->assertSeeText(__('site.member_verify.join_cta'));
    }
}