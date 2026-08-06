<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase27FeatureTest extends TestCase
{
    use RefreshDatabase;

    private function completeBorrower(): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        return Customer::create([
            'user_id'               => $user->id,
            'customer_number'         => 'CU-P27-'.random_int(100, 999),
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Complete',
            'last_name'             => 'Borrower',
            'phone'                 => '2557123463'.random_int(10, 99),
            'membership_status'     => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);
    }

    public function test_swahili_post_approval_agreement_and_contract_strings_are_available(): void
    {
        $this->assertSame(
            'Ada baada ya idhini',
            __('borrower.post_approval_fees.page_title', [], 'sw')
        );
        $this->assertSame(
            'Ofa imekubaliwa kwa mafanikio.',
            __('borrower.agreement.accepted_success', [], 'sw')
        );
        $this->assertSame(
            'Mkataba wa mkopo',
            __('borrower.contract.page_title', [], 'sw')
        );
    }

    public function test_support_page_shows_translated_faq_and_assistant_copy(): void
    {
        $customer = $this->completeBorrower();

        $this->actingAs($customer->user)
            ->get(route('site.borrower.support'))
            ->assertOk()
            ->assertSee(__('borrower.support_page.assistant_title'), false)
            ->assertSee(__('borrower.support_page.faq_title'), false)
            ->assertSee(__('borrower.support_page.identity_help_title'), false);
    }

    public function test_membership_renew_page_uses_translated_promo_and_payment_labels(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        $customer = Customer::create([
            'user_id'         => $user->id,
            'customer_number' => 'CU-P27-R-'.random_int(100, 999),
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Renew',
            'last_name'       => 'Borrower',
            'phone'           => '2557123464'.random_int(10, 99),
        ]);

        $this->actingAs($customer->user)
            ->get(route('site.membership.renew'))
            ->assertOk()
            ->assertSee(__('borrower.membership.promo_inline_label'), false)
            ->assertSee(__('borrower.membership.payment_reference_label'), false)
            ->assertSee(__('borrower.payments_page.create.mobile_money'), false)
            ->assertDontSee(__('borrower.payments_page.create.mobile_allowed'), false);
    }

    public function test_membership_renew_shows_invalid_promo_feedback(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        Customer::create([
            'user_id'         => $user->id,
            'customer_number' => 'CU-P27-P-'.random_int(100, 999),
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Promo',
            'last_name'       => 'Borrower',
            'phone'           => '2557123465'.random_int(10, 99),
        ]);

        $this->actingAs($user)
            ->get(route('site.membership.renew', ['promo_code' => 'PROMO2026']))
            ->assertOk()
            ->assertSee(__('borrower.membership.promo_bad_title'), false)
            ->assertSee(__('borrower.membership.promo_invalid'), false)
            ->assertSee('PROMO2026', false);
    }

    public function test_face_verification_uses_wide_content_layout(): void
    {
        $customer = $this->completeBorrower();

        $this->actingAs($customer->user)
            ->get(route('site.borrower.face-verification'))
            ->assertOk()
            ->assertSee('max-w-7xl', false);
    }

    public function test_admin_loan_product_form_includes_post_approval_catalog_sync(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-products.create'))
            ->assertOk()
            ->assertSee('function syncCatalogRows()', false)
            ->assertSee('function updateCatalogCount(count)', false)
            ->assertSee('syncCatalogRows();', false);
    }
}
