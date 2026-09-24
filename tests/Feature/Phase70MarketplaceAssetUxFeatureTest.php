<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureBorrowerPin;
use App\Models\AssetReservation;
use App\Models\Customer;
use App\Models\MarketplaceAsset;
use App\Models\User;
use App\Models\Vendor;
use App\Services\MarketplaceAssetService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase70MarketplaceAssetUxFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureBorrowerPin::class);
    }

    public function test_deposit_percent_is_derived_from_asset_value_on_save(): void
    {
        $prepared = app(MarketplaceAssetService::class)->prepareForSave([
            'category'          => 'vehicle',
            'title'             => 'Deposit Percent Truck',
            'asset_value'       => 10_000_000,
            'deposit_percent'   => 25,
            'max_tenure_months' => 12,
            'is_active'         => true,
        ]);

        $this->assertSame(2_500_000.0, (float) $prepared['supplier_deposit']);
        $this->assertSame(2_750_000.0, (float) $prepared['customer_deposit']);
    }

    public function test_admin_can_edit_asset_by_slug(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $asset = MarketplaceAsset::create([
            'slug'               => 'edit-by-slug-truck',
            'title'              => 'Slug Truck',
            'category'           => 'vehicle',
            'supplier_name'      => 'Supplier',
            'asset_value'        => 5_000_000,
            'supplier_deposit'   => 1_000_000,
            'customer_deposit'   => 1_100_000,
            'weekly_installment' => 120_000,
            'max_tenure_months'  => 12,
            'is_active'          => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.marketplace-assets.edit', $asset->slug))
            ->assertOk()
            ->assertSee('Deposit (% of asset value)', false);
    }

    public function test_starting_application_keeps_asset_listed_until_deposit(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');
        Customer::create([
            'user_id'               => $user->id,
            'customer_number'       => 'CU-P70-002',
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Locked',
            'last_name'             => 'Borrower',
            'phone'                 => '255712340071',
            'membership_status'     => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);

        $asset = MarketplaceAsset::create([
            'slug'               => 'locked-truck-001',
            'title'              => 'Locked Truck',
            'category'           => 'vehicle',
            'supplier_name'      => 'Supplier',
            'asset_value'        => 8_000_000,
            'supplier_deposit'   => 1_600_000,
            'customer_deposit'   => 1_760_000,
            'weekly_installment' => 150_000,
            'max_tenure_months'  => 18,
            'is_active'          => true,
            'availability_status'=> 'available',
        ]);

        $response = $this->actingAs($user)
            ->post(route('site.borrower.marketplace.apply', $asset->slug));

        $reservation = AssetReservation::query()->where('marketplace_asset_id', $asset->id)->first();
        $this->assertNotNull($reservation);
        $response->assertRedirect(route('site.borrower.apply', [
            'product' => config('asset_marketplace.asset_loan_product_code', 'AL'),
            'reservation' => $reservation->id,
        ]));

        $asset->refresh();
        $this->assertTrue($asset->isAvailable());

        $this->actingAs($user)
            ->get(route('site.borrower.marketplace.reserve', $asset->slug))
            ->assertOk()
            ->assertSee('Locked Truck', false);
    }

    public function test_borrower_can_apply_for_marketplace_asset(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');
        Customer::create([
            'user_id'               => $user->id,
            'customer_number'       => 'CU-P70-001',
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Apply',
            'last_name'             => 'Borrower',
            'phone'                 => '255712340070',
            'membership_status'     => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);

        $asset = MarketplaceAsset::create([
            'slug'               => 'apply-truck-001',
            'title'              => 'Apply Truck',
            'category'           => 'vehicle',
            'supplier_name'      => 'Supplier',
            'asset_value'        => 8_000_000,
            'supplier_deposit'   => 1_600_000,
            'customer_deposit'   => 1_760_000,
            'weekly_installment' => 150_000,
            'max_tenure_months'  => 18,
            'is_active'          => true,
            'availability_status'=> 'available',
            'photos'             => ['marketplace/test.jpg'],
        ]);

        $response = $this->actingAs($user)
            ->post(route('site.borrower.marketplace.apply', $asset->slug));

        $reservation = AssetReservation::query()->where('marketplace_asset_id', $asset->id)->first();
        $this->assertNotNull($reservation);
        $response->assertRedirect(route('site.borrower.apply', [
            'product' => config('asset_marketplace.asset_loan_product_code', 'AL'),
            'reservation' => $reservation->id,
        ]));
    }

    public function test_marketplace_card_shows_supplier_and_region(): void
    {
        $supplier = Vendor::create([
            'vendor_number' => 'SUP-P70',
            'name'          => 'Dar Motors',
            'category'      => 'supplier',
            'status'        => 'active',
            'phone'         => '255712340070',
            'coverage_type' => 'nationwide',
        ]);

        MarketplaceAsset::create([
            'slug'               => 'card-truck',
            'title'              => 'Card Truck',
            'category'           => 'vehicle',
            'supplier_name'      => 'Dar Motors',
            'partner_id'         => $supplier->id,
            'asset_value'        => 4_000_000,
            'supplier_deposit'   => 800_000,
            'customer_deposit'   => 880_000,
            'weekly_installment' => 90_000,
            'max_tenure_months'  => 12,
            'is_active'          => true,
        ]);

        $this->get(route('site.marketplace'))
            ->assertOk()
            ->assertSee('Dar Motors', false)
            ->assertSee('Nationwide', false)
            ->assertSee('kf-premium-panel', false)
            ->assertSee(__('borrower.marketplace.request_collapsed_title'), false);
    }

    public function test_borrower_asset_request_takes_name_only_and_get_link_opens_form(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');
        Customer::create([
            'user_id'               => $user->id,
            'customer_number'       => 'CU-P70-REQ',
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Asha',
            'last_name'             => 'Mushi',
            'phone'                 => '255712340072',
            'membership_status'     => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);

        $this->actingAs($user)
            ->get(route('site.borrower.marketplace'))
            ->assertOk()
            ->assertSee('name="asset_name"', false)
            ->assertDontSee('name="photo"', false)
            ->assertDontSee('singleImageDocumentUpload', false);

        $this->actingAs($user)
            ->get('/borrower/marketplace/request')
            ->assertRedirect(route('site.borrower.marketplace', ['request' => 1]));

        $this->actingAs($user)
            ->post(route('site.borrower.marketplace.request'), [
                'asset_name' => 'Bajaji ya mzigo',
            ])
            ->assertRedirect(route('site.borrower.marketplace'));

        $this->assertDatabaseHas('asset_requests', [
            'asset_name' => 'Bajaji ya mzigo',
            'photo_path' => null,
            'status' => 'sourcing',
        ]);
    }

    public function test_marketplace_detail_shows_price_deposit_financed_without_tenure(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');
        Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-P70-DETAIL',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Asha',
            'last_name' => 'Mushi',
            'phone' => '255712340073',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);

        $asset = MarketplaceAsset::create([
            'slug' => 'simple-detail-truck',
            'title' => 'Simple Detail Truck',
            'category' => 'vehicle',
            'supplier_name' => 'Dar Motors',
            'asset_value' => 8_000_000,
            'supplier_deposit' => 1_600_000,
            'customer_deposit' => 1_760_000,
            'weekly_installment' => 150_000,
            'max_tenure_months' => 12,
            'is_active' => true,
            'availability_status' => 'available',
        ]);

        $this->actingAs($user)
            ->get(route('site.borrower.marketplace.show', $asset->slug))
            ->assertOk()
            ->assertSee('Simple Detail Truck', false)
            ->assertSee(__('borrower.marketplace.request_expand'), false)
            ->assertSee(__('borrower.marketplace.asset_value'), false)
            ->assertSee(__('borrower.marketplace.deposit'), false)
            ->assertSee(__('borrower.marketplace.loan_amount'), false)
            ->assertDontSee('Choose duration', false)
            ->assertDontSee('name="tenure_months"', false)
            ->assertDontSee(__('borrower.marketplace.weekly_installment'), false);

        $this->get(route('site.marketplace.show', $asset->slug))
            ->assertOk()
            ->assertDontSee(__('borrower.marketplace.weekly_installment'), false)
            ->assertDontSee(__('borrower.marketplace.duration_range_label'), false);
    }

    public function test_request_asset_opens_application_overview_with_duration_quotes(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');
        Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-P70-QUOTE',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Asha',
            'last_name' => 'Mushi',
            'phone' => '255712340074',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);

        \App\Models\LoanProduct::query()->create([
            'code' => 'AL',
            'name' => 'Asset Lending',
            'is_active' => true,
            'interest_rate' => 0.12,
            'min_amount' => 100_000,
            'max_amount' => 20_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
        ]);

        $asset = MarketplaceAsset::create([
            'slug' => 'quote-overview-truck',
            'title' => 'Quote Overview Truck',
            'category' => 'vehicle',
            'supplier_name' => 'Dar Motors',
            'asset_value' => 8_000_000,
            'supplier_deposit' => 1_600_000,
            'customer_deposit' => 1_760_000,
            'weekly_installment' => 150_000,
            'max_tenure_months' => 12,
            'is_active' => true,
            'availability_status' => 'available',
        ]);

        $response = $this->actingAs($user)
            ->post(route('site.borrower.marketplace.apply', $asset->slug));

        $reservation = AssetReservation::query()->where('marketplace_asset_id', $asset->id)->first();
        $this->assertNotNull($reservation);
        $response->assertRedirect(route('site.borrower.apply', [
            'product' => config('asset_marketplace.asset_loan_product_code', 'AL'),
            'reservation' => $reservation->id,
        ]));
        $this->assertStringNotContainsString('tenure=', parse_url($response->headers->get('Location'), PHP_URL_QUERY) ?? '');

        $this->actingAs($user)
            ->get(route('site.borrower.apply', [
                'product' => 'AL',
                'reservation' => $reservation->id,
            ]))
            ->assertOk()
            ->assertSee('Quote Overview Truck', false)
            ->assertSee(__('borrower.apply.quote.tenure'), false)
            ->assertSee(__('borrower.apply.asset_tenure.installment_preview'), false)
            ->assertSee(__('borrower.apply.quote.total_repayment_tzs'), false)
            ->assertSee('quotes');
    }
}
