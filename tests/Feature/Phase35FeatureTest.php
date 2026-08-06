<?php

namespace Tests\Feature;

use App\Models\AssetReservation;
use App\Models\Customer;
use App\Models\MarketplaceAsset;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase35FeatureTest extends TestCase
{
    use RefreshDatabase;

    private function completeBorrower(string $suffix = '001'): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        return Customer::create([
            'user_id'               => $user->id,
            'customer_number'       => 'CU-P35-'.$suffix,
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Complete',
            'last_name'             => 'Borrower',
            'phone'                 => '2557123480'.substr($suffix, -2),
            'membership_status'     => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);
    }

    public function test_swahili_notifications_and_membership_page_strings_are_available(): void
    {
        $this->assertSame('Arifa', __('borrower.notifications.fallback_title', [], 'sw'));
        $this->assertSame('Malipo', __('borrower.notifications.categories.payment', [], 'sw'));
        $this->assertSame('Historia ya uanachama', __('borrower.membership_page.history_title', [], 'sw'));
        $this->assertSame(
            'Tumia pochi ya rufaa (:balance inapatikana)',
            __('borrower.marketplace.use_referral_wallet', [], 'sw')
        );
    }

    public function test_membership_renew_page_uses_wide_layout_without_inner_narrow_wrapper(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        Customer::create([
            'user_id'         => $user->id,
            'customer_number' => 'CU-P35-R-001',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Renew',
            'last_name'       => 'Borrower',
            'phone'           => '255712348101',
        ]);

        $this->actingAs($user)
            ->get(route('site.membership.renew'))
            ->assertOk()
            ->assertSee('max-w-7xl', false)
            ->assertDontSee('max-w-2xl mx-auto', false)
            ->assertSee(__('borrower.membership.promo_inline_label'), false);
    }

    public function test_marketplace_reserve_page_uses_wide_layout(): void
    {
        $customer = $this->completeBorrower('010');
        $asset = MarketplaceAsset::create([
            'slug'                   => 'p35-asset',
            'title'                  => 'Phase 35 Asset',
            'category'               => 'vehicle',
            'supplier_name'          => 'Supplier',
            'asset_value'            => 4_000_000,
            'supplier_deposit'       => 800_000,
            'customer_deposit'       => 900_000,
            'weekly_installment'     => 90_000,
            'max_tenure_months'      => 18,
            'availability_status'    => 'available',
            'is_active'              => true,
        ]);

        AssetReservation::create([
            'customer_id'            => $customer->id,
            'marketplace_asset_id'   => $asset->id,
            'status'                 => 'interest_confirmed',
            'reservation_fee_amount' => 50_000,
            'reservation_fee_status' => 'pending',
            'deposit_amount'         => 500_000,
            'deposit_status'         => 'pending',
        ]);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.marketplace.reserve', $asset->id))
            ->assertOk()
            ->assertSee('max-w-3xl mx-auto', false)
            ->assertSee(__('borrower.marketplace.next_action'), false);
    }

    public function test_notifications_page_shows_translated_category_label(): void
    {
        $customer = $this->completeBorrower('020');

        NotificationLog::create([
            'customer_id' => $customer->id,
            'channel'     => 'in_app',
            'category'    => 'payment',
            'recipient'   => '/borrower/payments',
            'template'    => 'payment_due',
            'message'     => "Payment reminder\nYour installment is due soon.",
            'status'      => 'sent',
            'sent_at'     => now(),
        ]);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.notifications'))
            ->assertOk()
            ->assertSee(__('borrower.notifications.categories.payment'), false)
            ->assertSee(__('borrower.notifications.page_title'), false);
    }

    public function test_notifications_page_uses_fallback_title_for_blank_message(): void
    {
        $customer = $this->completeBorrower('021');

        NotificationLog::create([
            'customer_id' => $customer->id,
            'channel'     => 'in_app',
            'category'    => 'system',
            'recipient'   => '/borrower/dashboard',
            'template'    => 'system_ping',
            'message'     => '',
            'status'      => 'sent',
            'sent_at'     => now(),
        ]);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.notifications'))
            ->assertOk()
            ->assertSee(__('borrower.notifications.fallback_title'), false);
    }

    public function test_guarantor_requests_index_redirects_to_loans_tab(): void
    {
        $customer = $this->completeBorrower('030');

        $this->actingAs($customer->user)
            ->get(route('site.borrower.guarantor-requests'))
            ->assertRedirect(route('site.borrower.loans', ['tab' => 'guarantor']));
    }
}
