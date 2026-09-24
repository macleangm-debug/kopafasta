<?php

namespace Tests\Feature;

use App\Models\LoanProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductConfigurationUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_edit_guides_staff_with_helpers_and_asset_lending_tiers(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = LoanProduct::query()->where('code', 'AL')->first()
            ?? LoanProduct::create([
                'code' => 'AL',
                'name' => 'Asset Lending',
                'category' => 'asset_finance',
                'interest_rate' => 0.15,
                'min_amount' => 500_000,
                'max_amount' => 50_000_000,
                'tenure_min_months' => 3,
                'tenure_max_months' => 6,
                'is_active' => true,
                'status' => 'active',
            ]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-products.edit', $product))
            ->assertOk()
            ->assertSee('The percentage the customer must pay before the financed amount is calculated.', false)
            ->assertSee('The longest repayment period available for this product.', false)
            ->assertSee('Charged before the borrower can proceed beyond the application-fee gate.', false)
            ->assertSee('Deposit tiers', false)
            ->assertSee('Financing tiers', false)
            ->assertSee('Funding / supplier arrangement', false)
            ->assertSee('Search and sharing', false)
            ->getContent();

        $this->assertStringContainsString('requiresGuarantor', $html);
        $this->assertStringContainsString('usesCapitalPartner', $html);
        $this->assertStringNotContainsString('data-step-label="Tiered monthly rates"', $html);
        $this->assertStringNotContainsString('name="rate_tiers', $html);
        $this->assertStringNotContainsString('bot_regulated_rate', $html);
    }

    public function test_individual_product_still_uses_shared_rate_tiers(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = LoanProduct::query()->where('code', 'IL')->first()
            ?? LoanProduct::create([
                'code' => 'IL',
                'name' => 'Individual Loan',
                'category' => 'individual',
                'interest_rate' => 0.18,
                'min_amount' => 500_000,
                'max_amount' => 10_000_000,
                'tenure_min_months' => 3,
                'tenure_max_months' => 24,
                'is_active' => true,
                'status' => 'active',
            ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-products.edit', $product))
            ->assertOk()
            ->assertSee('Tiered monthly rates', false)
            ->assertDontSee('Deposit tiers');
    }
}
