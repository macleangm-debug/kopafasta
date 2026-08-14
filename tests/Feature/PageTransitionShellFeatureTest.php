<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\MarketplaceAsset;
use App\Models\User;
use App\Services\PinRecoveryChallengeService;
use App\Services\PinService;
use Database\Seeders\PartnerDemoAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageTransitionShellFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function completeBorrower(): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');
        app(PinRecoveryChallengeService::class)->enroll($user, [
            'mother_first_name' => 'Asha',
            'primary_school' => 'Uhuru Primary',
            'nida_middle4' => '4582',
        ]);

        return Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-VT-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Motion',
            'last_name' => 'Borrower',
            'phone' => '25571234'.random_int(1000, 9999),
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);
    }

    public function test_borrower_shell_chrome_and_sidebar_use_tab_motion(): void
    {
        $customer = $this->completeBorrower();

        $this->actingAs($customer->user)
            ->get(route('site.borrower.dashboard'))
            ->assertOk()
            ->assertSee('kf-chrome-sidebar', false)
            ->assertSee('kf-chrome-page', false)
            ->assertSee('data-kf-motion="tab"', false);
    }

    public function test_borrower_loans_tabs_use_tab_motion(): void
    {
        $customer = $this->completeBorrower();

        $this->actingAs($customer->user)
            ->get(route('site.borrower.loans'))
            ->assertOk()
            ->assertSee('data-kf-motion="tab"', false);
    }

    public function test_marketplace_cards_and_detail_share_an_element_name(): void
    {
        $customer = $this->completeBorrower();
        $asset = MarketplaceAsset::create([
            'slug' => 'vt-share-pickup',
            'title' => 'View Transition Pickup',
            'category' => 'vehicle',
            'supplier_name' => 'Supplier',
            'asset_value' => 5_000_000,
            'supplier_deposit' => 1_000_000,
            'customer_deposit' => 1_100_000,
            'weekly_installment' => 120_000,
            'max_tenure_months' => 12,
            'is_active' => true,
            'availability_status' => 'available',
        ]);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.marketplace'))
            ->assertOk()
            ->assertSee('data-kf-share="kf-mp-'.$asset->slug.'"', false);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.marketplace.show', $asset->slug))
            ->assertOk()
            ->assertSee('view-transition-name: kf-mp-'.$asset->slug, false)
            ->assertSee('data-kf-motion="pop"', false);
    }

    public function test_affiliate_and_partner_shells_opt_into_view_transitions(): void
    {
        $this->seed(PartnerDemoAccountsSeeder::class);

        $affiliate = User::query()->where('email', 'affiliate@kopafasta.local')->firstOrFail();
        $this->actingAs($affiliate)
            ->get(route('site.affiliate.dashboard'))
            ->assertOk()
            ->assertSee('kf-chrome-sidebar', false)
            ->assertSee('kf-chrome-page', false)
            ->assertSee('data-kf-motion="tab"', false);

        $this->actingAs($affiliate)
            ->get(route('site.affiliate.profile'))
            ->assertOk()
            ->assertSee('data-kf-share="kf-psec-personal"', false);

        $partner = User::query()->where('email', 'collection@kopafasta.local')->firstOrFail();
        $this->actingAs($partner)
            ->get(route('site.partner.dashboard'))
            ->assertOk()
            ->assertSee('kf-chrome-sidebar', false)
            ->assertSee('kf-chrome-page', false)
            ->assertSee('data-kf-motion="tab"', false);

        $this->actingAs($partner)
            ->get(route('site.partner.tasks'))
            ->assertOk()
            ->assertSee('data-kf-motion="tab"', false);
    }
}
