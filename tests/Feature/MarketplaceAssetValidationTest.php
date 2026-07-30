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
                    0 => \Illuminate\Http\UploadedFile::fake()->image('one.jpg'),
                    1 => \Illuminate\Http\UploadedFile::fake()->image('two.jpg'),
                ],
                'cover_path'          => '__new_1',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $asset->refresh();
        $this->assertCount(2, $asset->photos ?? []);
        foreach ($asset->photos as $path) {
            \Illuminate\Support\Facades\Storage::disk('public')->assertExists($path);
        }

        // Re-run with known paths to prove cover_path=__new_1 promotes slot 1.
        $slot0 = $asset->photos[0];
        $slot1 = $asset->photos[1];
        $asset->update(['photos' => [$slot0, $slot1]]);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.marketplace-assets.update', $asset), [
                'vendor_id'           => $supplier->id,
                'category'            => 'vehicle',
                'title'               => 'Multi Photo Asset',
                'insurance_available' => '0',
                'asset_value'         => '2,000,000.00',
                'deposit_percent'     => '20',
                'max_tenure_months'   => 12,
                'is_active'           => '1',
                'cover_path'          => $slot1,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $asset->refresh();
        $this->assertSame([$slot1, $slot0], $asset->photos);
    }

    public function test_admin_asset_update_sets_existing_cover(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);
        $supplier = Vendor::create([
            'vendor_number' => 'PT-SP-TZ-COVER',
            'name'          => 'Cover Photo Supplier',
            'category'      => 'supplier',
            'status'        => 'active',
        ]);

        $first = 'marketplace/cover-a.jpg';
        $second = 'marketplace/cover-b.jpg';
        \Illuminate\Support\Facades\Storage::disk('public')->put($first, 'a');
        \Illuminate\Support\Facades\Storage::disk('public')->put($second, 'b');

        $asset = \App\Models\MarketplaceAsset::create([
            'vendor_id'         => $supplier->id,
            'slug'              => 'cover-photo-asset',
            'category'          => 'vehicle',
            'title'             => 'Cover Photo Asset',
            'supplier_name'     => $supplier->name,
            'asset_value'       => 2_000_000,
            'supplier_deposit'  => 400_000,
            'customer_deposit'  => 440_000,
            'max_tenure_months' => 12,
            'is_active'         => true,
            'photos'            => [$first, $second],
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->put(route('admin.marketplace-assets.update', $asset), [
                'vendor_id'           => $supplier->id,
                'category'            => 'vehicle',
                'title'               => 'Cover Photo Asset',
                'insurance_available' => '0',
                'asset_value'         => '2,000,000.00',
                'deposit_percent'     => '20',
                'max_tenure_months'   => 12,
                'is_active'           => '1',
                'cover_path'          => $second,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $asset->refresh();
        $this->assertSame([$second, $first], $asset->photos);
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
            ->assertSee('Cover', false)
            ->assertSee('Set as cover', false);
    }

    public function test_admin_asset_update_keeps_existing_photos_when_no_new_files_uploaded(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);
        $supplier = Vendor::create([
            'vendor_number' => 'PT-SP-TZ-KEEP',
            'name'          => 'Keep Photo Supplier',
            'category'      => 'supplier',
            'status'        => 'active',
        ]);

        $existing = 'https://images.example.com/keep-me.jpg';
        $asset = \App\Models\MarketplaceAsset::create([
            'vendor_id'         => $supplier->id,
            'slug'              => 'keep-photos-asset',
            'category'          => 'vehicle',
            'title'             => 'Keep Photos Asset',
            'supplier_name'     => $supplier->name,
            'asset_value'       => 2_000_000,
            'supplier_deposit'  => 400_000,
            'customer_deposit'  => 440_000,
            'max_tenure_months' => 12,
            'is_active'         => true,
            'photos'            => [$existing],
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->put(route('admin.marketplace-assets.update', $asset), [
                'vendor_id'           => $supplier->id,
                'category'            => 'vehicle',
                'title'               => 'Keep Photos Asset',
                'insurance_available' => '0',
                'asset_value'         => '2,000,000.00',
                'deposit_percent'     => '20',
                'max_tenure_months'   => 12,
                'is_active'           => '1',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $asset->refresh();
        $this->assertSame([$existing], $asset->photos);
    }
}
