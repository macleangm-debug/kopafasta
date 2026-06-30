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
    }
}
