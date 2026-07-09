<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use App\Support\MoneyFormat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceAssetValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_asset_value_accepts_formatted_money_strings(): void
    {
        $this->assertSame(1500000.5, MoneyFormat::toNumber('1,500,000.50'));
    }

    public function test_admin_asset_store_normalizes_comma_separated_values(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $supplier = Vendor::create([
            'vendor_number' => 'PT-SP-TZ-TEST',
            'name'          => 'Test Supplier',
            'category'      => 'supplier',
            'status'        => 'active',
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.marketplace-assets.store'), [
                'vendor_id'        => $supplier->id,
                'category'         => 'vehicle',
                'title'            => 'Test Asset',
                'insurance_available' => '0',
                'asset_value'      => '1,500,000.00',
                'deposit_percent'  => '20',
                'max_tenure_months'=> 12,
                'is_active'        => '1',
                'photos'           => [
                    \Illuminate\Http\UploadedFile::fake()->image('asset.jpg'),
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('marketplace_assets', [
            'title'            => 'Test Asset',
            'asset_value'      => 1500000.00,
            'supplier_deposit' => 300000.00,
        ]);

        $asset = \App\Models\MarketplaceAsset::query()->where('title', 'Test Asset')->first();
        $this->assertNotNull($asset);
        $this->assertIsArray($asset->photos);
        $this->assertCount(1, $asset->photos);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($asset->photos[0]);
    }

    public function test_admin_asset_update_can_add_multiple_photos(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);
        $supplier = Vendor::create([
            'vendor_number' => 'PT-SP-TZ-MULTI',
            'name'          => 'Multi Photo Supplier',
            'category'      => 'supplier',
            'status'        => 'active',
        ]);

        $asset = \App\Models\MarketplaceAsset::create([
            'vendor_id'         => $supplier->id,
            'slug'              => 'multi-photo-asset',
            'category'          => 'vehicle',
            'title'             => 'Multi Photo Asset',
            'supplier_name'     => $supplier->name,
            'asset_value'       => 2_000_000,
            'supplier_deposit'  => 400_000,
            'customer_deposit'  => 440_000,
            'max_tenure_months' => 12,
            'is_active'         => true,
            'photos'            => [],
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->put(route('admin.marketplace-assets.update', $asset), [
                'vendor_id'           => $supplier->id,
                'category'            => 'vehicle',
                'title'               => 'Multi Photo Asset',
                'insurance_available' => '0',
                'asset_value'         => '2,000,000.00',
                'deposit_percent'     => '20',
                'max_tenure_months'   => 12,
                'is_active'           => '1',
                'photos'              => [
                    \Illuminate\Http\UploadedFile::fake()->image('one.jpg'),
                    \Illuminate\Http\UploadedFile::fake()->image('two.jpg'),
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $asset->refresh();
        $this->assertCount(2, $asset->photos ?? []);
        foreach ($asset->photos as $path) {
            \Illuminate\Support\Facades\Storage::disk('public')->assertExists($path);
        }
    }

    public function test_admin_edit_page_shows_existing_photo_urls(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $supplier = Vendor::create([
            'vendor_number' => 'PT-SP-TZ-EDIT',
            'name'          => 'Edit Photo Supplier',
            'category'      => 'supplier',
            'status'        => 'active',
        ]);

        $photoUrl = 'https://images.example.com/delivery-motorcycle.jpg';
        $asset = \App\Models\MarketplaceAsset::create([
            'vendor_id'         => $supplier->id,
            'slug'              => 'delivery-motorcycle-edit',
            'category'          => 'vehicle',
            'title'             => 'Delivery Motorcycle',
            'supplier_name'     => $supplier->name,
            'asset_value'       => 3_000_000,
            'supplier_deposit'  => 600_000,
            'customer_deposit'  => 660_000,
            'max_tenure_months' => 12,
            'is_active'         => true,
            'photos'            => [$photoUrl],
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.marketplace-assets.edit', $asset))
            ->assertOk()
            ->assertSee($photoUrl, false)
            ->assertSee('data-multi-image-upload', false)
            ->assertSee('Cover', false);
    }
}
