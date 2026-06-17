<?php

namespace Tests\Feature;

use App\Models\ChargesFee;
use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase28FeatureTest extends TestCase
{
    use RefreshDatabase;

    private function completeBorrower(): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        return Customer::create([
            'user_id'               => $user->id,
            'customer_number'       => 'CU-P28-'.random_int(100, 999),
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Complete',
            'last_name'             => 'Borrower',
            'phone'                 => '2557123465'.random_int(10, 99),
            'membership_status'     => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);
    }

    public function test_swahili_handover_payments_and_guarantor_notification_strings_are_available(): void
    {
        $this->assertSame(
            'Maendeleo ya kukabidhi mali',
            __('borrower.handover_milestones.title', [], 'sw')
        );
        $this->assertSame(
            'Malipo',
            __('borrower.payments_page.title', [], 'sw')
        );
        $this->assertSame(
            'Angalia ombi →',
            __('borrower.guarantor_notifications.view_request', [], 'sw')
        );
        $this->assertSame(
            'Ingia ili kuomba',
            __('borrower.marketplace.public_login_cta', [], 'sw')
        );
    }

    public function test_apply_success_page_uses_wide_layout_and_reference_label(): void
    {
        $customer = $this->completeBorrower();

        $product = LoanProduct::create([
            'code'              => 'IL-P28',
            'name'              => 'Phase 28 Product',
            'is_active'         => true,
            'interest_rate'     => 0.15,
            'min_amount'        => 100_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);

        $application = LoanApplication::create([
            'customer_id'             => $customer->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-P28-SUCCESS',
            'requested_amount'        => 500_000,
            'requested_tenure_months' => 6,
            'status'                  => 'submitted',
            'current_stage'           => 'submitted',
        ]);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.apply.success', $application))
            ->assertOk()
            ->assertSee('max-w-7xl', false)
            ->assertSee(__('borrower.apply.success.reference_label'), false);
    }

    public function test_payments_index_uses_wide_layout_and_translated_copy(): void
    {
        $customer = $this->completeBorrower();

        $this->actingAs($customer->user)
            ->get(route('site.borrower.payments'))
            ->assertOk()
            ->assertSee('max-w-7xl', false)
            ->assertSee(__('borrower.payments_page.title'), false)
            ->assertSee(__('borrower.payments_page.empty_title'), false);
    }

    public function test_guarantor_notifications_page_shows_translated_action_labels(): void
    {
        $customer = $this->completeBorrower();

        $this->actingAs($customer->user)
            ->get(route('site.borrower.guarantor-notifications'))
            ->assertOk()
            ->assertSee(__('borrower.guarantor_notifications.title'), false)
            ->assertSee(__('borrower.guarantor_notifications.empty'), false);
    }

    public function test_public_marketplace_shows_translated_guest_cta(): void
    {
        $this->get(route('site.marketplace'))
            ->assertOk()
            ->assertSee(__('borrower.marketplace.public_login_cta'), false)
            ->assertSee(__('borrower.marketplace.public_eyebrow', ['brand' => brand_name()]), false);
    }

    public function test_charges_fee_post_approval_value_persists_on_sqlite(): void
    {
        $fee = ChargesFee::create([
            'code'        => 'DOC-FEE-P28',
            'name'        => 'Documentation fee',
            'type'        => 'processing',
            'basis'       => 'fixed',
            'amount'      => 50_000,
            'charge_when' => 'post_approval',
            'is_active'   => true,
        ]);

        $this->assertSame('post_approval', $fee->fresh()->charge_when);

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-products.create'))
            ->assertOk()
            ->assertSee('Documentation fee', false)
            ->assertSee('post-approval-catalog-count', false);
    }
}
