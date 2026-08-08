<?php

namespace Tests\Feature;

use App\Models\LoanProduct;
use App\Services\DisplayedRateService;
use Database\Seeders\LoanProductRateTierSeeder;
use Database\Seeders\PublicLoanProductsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShariaLoanProductFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_sharia_loan_is_published_like_individual_loan_without_interest_wording(): void
    {
        $this->seed(PublicLoanProductsSeeder::class);
        $this->seed(LoanProductRateTierSeeder::class);

        $product = LoanProduct::query()->where('code', 'SL')->first();
        $this->assertNotNull($product);
        $this->assertTrue($product->is_active);
        $this->assertTrue($product->hidesInterest());
        $this->assertSame((float) LoanProduct::query()->where('code', 'IL')->value('interest_rate'), (float) $product->interest_rate);

        $lines = app(DisplayedRateService::class)->borrowerDisclosureLines($product);
        $joined = implode(' ', $lines);
        $this->assertStringNotContainsStringIgnoringCase('interest', $joined);
        $this->assertStringNotContainsStringIgnoringCase('riba', $joined);

        $this->assertStringContainsString('Monthly charge', loan_product_rate_field_label($product));

        $this->get(route('site.home'))
            ->assertOk()
            ->assertSee('Sharia Loan', false);

        $this->get(route('site.products'))
            ->assertOk()
            ->assertSee('Sharia Loan', false);
    }
}
