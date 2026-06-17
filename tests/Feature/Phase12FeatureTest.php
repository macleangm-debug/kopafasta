<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Models\Vendor;
use App\Services\KycFreshnessService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Phase12FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_partner_edit_form_posts_to_partner_update_route(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $partner = Vendor::create([
            'vendor_number' => 'PTR-P12-001',
            'name'          => 'Phase 12 Partner',
            'category'      => 'valuer',
            'status'        => 'active',
            'phone'         => '255712345695',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.partners.edit', $partner))
            ->assertOk()
            ->assertSee(route('admin.partners.update', $partner), false);
    }

    public function test_partner_payments_route_alias_is_registered(): void
    {
        $this->assertTrue(Route::has('admin.partner-payments.index'));
        $this->assertSame('/admin/partner-payments', route('admin.partner-payments.index', [], false));
    }

    public function test_borrower_applications_view_mode_persists_in_preferences(): void
    {
        $user = $this->borrowerWithPin(['preferences' => []]);

        $this->actingAs($user)
            ->get(route('site.borrower.loans', ['tab' => 'applications', 'view' => 'cards']))
            ->assertOk();

        $this->assertSame('cards', $user->fresh()->preferences['applications_view'] ?? null);

        $this->actingAs($user)
            ->get(route('site.borrower.loans', ['tab' => 'applications']))
            ->assertOk()
            ->assertSee(__('borrower.applications_list.cards'), false);
    }

    public function test_dashboard_shows_kyc_reconfirm_banner_when_sections_are_stale(): void
    {
        $user = $this->borrowerWithPin();
        $customer = Customer::query()->where('user_id', $user->id)->firstOrFail();
        $customer->update([
            'region'        => 'Dar es Salaam',
            'district'      => 'Kinondoni',
            'street'        => 'Sample Street',
            'activity_type' => 'trader',
            'income_range'  => '500k_1m',
            'activity_details' => ['trade_type' => 'food'],
            'profile_section_confirmed_at' => [
                'activity'  => now()->subDays(120)->toIso8601String(),
                'residence' => now()->subDays(120)->toIso8601String(),
            ],
        ]);

        $this->assertNotEmpty(app(KycFreshnessService::class)->sectionsDueForRefresh($customer->fresh()));

        $this->actingAs($user)
            ->get(route('site.borrower.dashboard'))
            ->assertOk()
            ->assertSee(__('borrower.dashboard.kyc_reconfirm_title'), false);
    }

    private function borrowerWithPin(array $userAttrs = []): User
    {
        $user = User::factory()->create(array_merge(['role' => 'borrower'], $userAttrs));
        app(PinService::class)->setPin($user, '1234');

        Customer::create([
            'user_id'         => $user->id,
            'customer_number' => 'CU-P12-'.strtoupper(substr(md5((string) $user->id), 0, 6)),
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Phase',
            'last_name'       => 'Twelve',
            'phone'           => '255712345696',
        ]);

        return $user;
    }
}
