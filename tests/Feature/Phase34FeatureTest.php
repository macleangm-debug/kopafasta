<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanAgreement;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\MarketplaceAsset;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase34FeatureTest extends TestCase
{
    use RefreshDatabase;

    private function completeBorrower(string $suffix = '001'): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        return Customer::create([
            'user_id'               => $user->id,
            'customer_number'       => 'CU-P34-'.$suffix,
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Complete',
            'last_name'             => 'Borrower',
            'phone'                 => '2557123472'.substr($suffix, -2),
            'membership_status'     => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);
    }

    private function loanProduct(): LoanProduct
    {
        return LoanProduct::create([
            'code'              => 'IL-P34',
            'name'              => 'Phase 34 Product',
            'is_active'         => true,
            'interest_rate'     => 0.15,
            'min_amount'        => 100_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);
    }

    public function test_swahili_loan_contract_pdf_and_marketplace_strings_are_available(): void
    {
        $this->assertSame(
            'Mkopo',
            __('borrower.loan_contract.pdf.facility_heading', [], 'sw')
        );
        $this->assertSame(
            '← Rudi kwenye soko',
            __('borrower.marketplace.back_to_marketplace', [], 'sw')
        );
        $this->assertSame(
            'Arifa',
            __('borrower.guarantor_notifications.fallback_title', [], 'sw')
        );
        $this->assertSame(
            'Haikuwezekana kutumia mdhamini wa awali.',
            __('borrower.apply.previous_guarantor.failed', [], 'sw')
        );
    }

    public function test_public_marketplace_show_uses_wide_layout_and_translated_back_link(): void
    {
        $asset = MarketplaceAsset::create([
            'slug'                   => 'p34-truck',
            'title'                  => 'Phase 34 Truck',
            'category'               => 'vehicle',
            'supplier_name'          => 'Supplier',
            'asset_value'            => 5_000_000,
            'supplier_deposit'       => 1_000_000,
            'deposit_markup_percent' => 10,
            'customer_deposit'       => 1_100_000,
            'weekly_installment'     => 120_000,
            'max_tenure_months'      => 24,
            'is_active'              => true,
        ]);

        $this->get(route('site.marketplace.show', $asset->slug))
            ->assertOk()
            ->assertSee('max-w-7xl', false)
            ->assertSee(__('borrower.marketplace.back_to_marketplace'), false)
            ->assertSee(__('borrower.marketplace.public_apply_hint'), false);
    }

    public function test_guarantor_notifications_page_shows_notification_item(): void
    {
        $customer = $this->completeBorrower('020');

        NotificationLog::create([
            'customer_id' => $customer->id,
            'channel'     => 'in_app',
            'recipient'   => '/borrower/guarantor-requests',
            'template'    => 'guarantor_request',
            'message'     => "Guarantor request\nComplete Borrower needs your guarantee.",
            'status'      => 'sent',
            'sent_at'     => now(),
        ]);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.guarantor-notifications'))
            ->assertOk()
            ->assertSee(__('borrower.guarantor_notifications.title'), false)
            ->assertSee(__('borrower.guarantor_notifications.new_badge'), false)
            ->assertSee(__('borrower.guarantor_notifications.view_request'), false)
            ->assertSee('Guarantor request', false);
    }

    public function test_apply_wizard_uses_narrow_layout_and_translated_guarantor_placeholder(): void
    {
        $customer = $this->completeBorrower('030');
        $product = $this->loanProduct();
        $product->update(['requires_guarantor' => true]);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.apply', ['product' => $product->id]))
            ->assertOk()
            ->assertSee('max-w-3xl', false)
            ->assertSee(__('borrower.apply.title'), false)
            ->assertSee(__('borrower.profile.fields.full_name'), false);
    }

    public function test_loan_contract_pdf_template_uses_translated_labels(): void
    {
        $customer = $this->completeBorrower('040');
        $product = $this->loanProduct();

        $application = LoanApplication::create([
            'customer_id'             => $customer->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-P34-PDF',
            'requested_amount'        => 500_000,
            'requested_tenure_months' => 12,
            'status'                  => 'approved',
        ]);

        $agreement = LoanAgreement::create([
            'loan_application_id' => $application->id,
            'customer_id'         => $customer->id,
            'document_type'       => 'loan_contract',
            'reference'           => 'LC-P34TEST',
            'status'              => 'sent',
        ]);

        $html = view('pdf.loan-contract', [
            'application' => $application->load('product'),
            'agreement'   => $agreement,
            'snapshot'    => [
                'customer_name'          => 'Complete Borrower',
                'application_number'   => $application->application_number,
                'principal'              => 450_000,
                'displayed_monthly_rate' => 0.15,
                'tenure_months'          => 12,
                'repayment_cadence'      => 'monthly',
                'installment_count'      => 12,
                'estimated_emi'          => 45_000,
                'total_repayable'        => 540_000,
                'legal_clauses'          => [],
                'contract_sections'      => [
                    'definitions' => true,
                    'loan_terms' => true,
                    'repayment_obligations' => true,
                ],
            ],
        ])->render();

        $this->assertStringContainsString(__('borrower.loan_contract.pdf.facility_heading'), $html);
        $this->assertStringContainsString(__('borrower.loan_contract.pdf.borrower_heading'), $html);
        $this->assertStringContainsString(__('borrower.loan_contract.pdf.repayment_heading'), $html);
    }

    public function test_loan_profile_application_page_uses_wide_layout(): void
    {
        $customer = $this->completeBorrower('050');
        $product = $this->loanProduct();

        $application = LoanApplication::create([
            'customer_id'             => $customer->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-P34-DET',
            'requested_amount'        => 500_000,
            'requested_tenure_months' => 12,
            'status'                  => 'submitted',
            'submitted_at'            => now(),
        ]);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.application', $application))
            ->assertOk()
            ->assertSee('max-w-7xl', false)
            ->assertSee(__('borrower.loan_profile.label'), false)
            ->assertSee(__('borrower.loan_profile.back'), false);
    }
}
