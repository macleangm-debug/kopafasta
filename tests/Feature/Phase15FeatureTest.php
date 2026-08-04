<?php

namespace Tests\Feature;

use App\Models\AssetReservation;
use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\MarketplaceAsset;
use App\Models\User;
use App\Services\ApplicationTrackingShareService;
use App\Services\AssetReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase15FeatureTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{customer: Customer, asset: MarketplaceAsset} */
    private function marketplaceFixtures(): array
    {
        $customer = Customer::create([
            'customer_number' => 'CU-P15-001',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Phase',
            'last_name'       => 'Fifteen',
            'phone'           => '255712345690',
        ]);

        $asset = MarketplaceAsset::create([
            'slug'                => 'p15-asset',
            'title'               => 'Phase 15 Asset',
            'category'            => 'vehicle',
            'supplier_name'       => 'Supplier',
            'asset_value'         => 3_000_000,
            'supplier_deposit'    => 600_000,
            'customer_deposit'    => 700_000,
            'weekly_installment'  => 80_000,
            'max_tenure_months'   => 18,
            'availability_status' => 'available',
            'is_active'           => true,
        ]);

        return compact('customer', 'asset');
    }

    public function test_viewing_is_scheduled_only_after_application_fee_paid(): void
    {
        ['customer' => $customer, 'asset' => $asset] = $this->marketplaceFixtures();

        $reservation = AssetReservation::create([
            'customer_id'            => $customer->id,
            'marketplace_asset_id'   => $asset->id,
            'status'                 => 'application_started',
            'reservation_fee_amount' => 50_000,
            'reservation_fee_status' => 'pending',
            'deposit_amount'         => 500_000,
            'deposit_status'         => 'pending',
        ]);

        $service = app(AssetReservationService::class);

        $this->assertFalse($service->canScheduleViewing($reservation));

        $this->expectException(\InvalidArgumentException::class);
        $service->scheduleViewing($reservation, now()->addDay()->format('Y-m-d'), '10:00');
    }

    public function test_viewing_can_be_scheduled_after_fee_paid(): void
    {
        ['customer' => $customer, 'asset' => $asset] = $this->marketplaceFixtures();

        $reservation = AssetReservation::create([
            'customer_id'            => $customer->id,
            'marketplace_asset_id'   => $asset->id,
            'status'                 => 'reservation_fee_paid',
            'reservation_fee_amount' => 50_000,
            'reservation_fee_status' => 'paid',
            'deposit_amount'         => 500_000,
            'deposit_status'         => 'pending',
        ]);

        $service = app(AssetReservationService::class);

        $this->assertTrue($service->canScheduleViewing($reservation));

        $updated = $service->scheduleViewing(
            $reservation,
            now()->addDays(2)->format('Y-m-d'),
            '14:30',
        );

        $this->assertSame('viewing_scheduled', $updated->status);
        $this->assertNotNull($updated->viewing_date);
    }

    public function test_mark_viewing_completed_after_fee_keeps_reservation_fee_paid_status(): void
    {
        ['customer' => $customer, 'asset' => $asset] = $this->marketplaceFixtures();

        $reservation = AssetReservation::create([
            'customer_id'            => $customer->id,
            'marketplace_asset_id'   => $asset->id,
            'status'                 => 'viewing_scheduled',
            'reservation_fee_amount' => 50_000,
            'reservation_fee_status' => 'paid',
            'deposit_amount'         => 500_000,
            'deposit_status'         => 'pending',
            'viewing_date'           => now()->addDay(),
            'viewing_time'           => '10:00',
        ]);

        $updated = app(AssetReservationService::class)->markViewingCompleted($reservation);

        $this->assertSame('reservation_fee_paid', $updated->status);
        $this->assertNotNull($updated->viewing_completed_at);
    }

    public function test_application_tracking_whatsapp_url_contains_reference(): void
    {
        $product = LoanProduct::create([
            'code'              => 'IL-P15',
            'name'              => 'Test Product',
            'is_active'         => true,
            'interest_rate'     => 0.15,
            'min_amount'        => 100_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);

        $customer = Customer::create([
            'customer_number' => 'CU-P15-002',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Track',
            'last_name'       => 'Share',
            'phone'           => '255712345691',
        ]);

        $application = LoanApplication::create([
            'customer_id'             => $customer->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-P15-001',
            'requested_amount'        => 1_000_000,
            'requested_tenure_months' => 12,
            'status'                  => 'submitted',
            'current_stage'           => 'submitted',
        ]);

        $service = app(ApplicationTrackingShareService::class);
        $url = $service->whatsAppShareUrl($application);

        $this->assertStringStartsWith('https://wa.me/?text=', $url);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $message = $query['text'] ?? '';

        $this->assertStringContainsString('APP-P15-001', $message);
        $this->assertStringContainsString(route('site.borrower.application', $application->id), $message);
    }

    public function test_fc_artisan_details_live_on_profile_activity_not_loan_questions(): void
    {
        $this->assertArrayNotHasKey('FC', config('loan_product_questions', []));

        $artisanFields = collect(config('activity_profiles.fields.artisan', []));
        $this->assertNotNull($artisanFields->firstWhere('key', 'skill_type'));
        $this->assertNotNull($artisanFields->firstWhere('key', 'region'));
        $this->assertNotNull($artisanFields->firstWhere('key', 'district'));
        $this->assertNotNull($artisanFields->firstWhere('key', 'street'));
    }

    public function test_borrower_layout_supports_content_width_prop(): void
    {
        $user = User::factory()->create();

        Customer::create([
            'user_id'         => $user->id,
            'customer_number' => 'CU-P15-003',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Layout',
            'last_name'       => 'Test',
            'phone'           => '255712345692',
        ]);

        $this->actingAs($user)
            ->get(route('site.borrower.dashboard'))
            ->assertOk()
            ->assertSee('max-w-7xl', false);

        $product = LoanProduct::create([
            'code'              => 'IL-P15B',
            'name'              => 'Layout Test',
            'is_active'         => true,
            'interest_rate'     => 0.15,
            'min_amount'        => 100_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);

        $application = LoanApplication::create([
            'customer_id'             => $user->fresh()->customer->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-P15-LAYOUT',
            'requested_amount'        => 500_000,
            'requested_tenure_months' => 6,
            'status'                  => 'submitted',
            'current_stage'           => 'submitted',
        ]);

        $this->actingAs($user)
            ->get(route('site.borrower.apply.success', $application))
            ->assertOk()
            ->assertSee('max-w-7xl', false)
            ->assertSee(__('borrower.apply.success.tracking_share_title'), false);
    }
}
