<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use App\Services\PartnerActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerActivationPhoneAndAdminLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_partner_surface_stays_english_when_public_site_is_swahili(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $partner = $this->invitedSupplier();

        $html = $this->actingAs($admin, 'admin')
            ->withSession(['locale' => 'sw'])
            ->get(route('admin.partners.show', $partner))
            ->assertOk()
            ->assertSee(__('admin.partners.awaiting_activation', [], 'en'), false)
            ->assertSee(__('admin.partners.copy_link', [], 'en'), false)
            ->assertSee(__('admin.partners.copied', [], 'en'), false)
            ->assertSee('Activate &amp; set PIN', false)
            ->assertDontSee(__('admin.partners.copy_link', [], 'sw'), false)
            ->assertDontSee(__('admin.partners.copied', [], 'sw'), false)
            ->getContent();

        $this->assertStringContainsString(__('admin.chrome.search', [], 'en'), $html);
        $this->assertStringNotContainsString(__('admin.chrome.search', [], 'sw'), $html);
    }

    public function test_admin_partner_surface_is_swahili_when_admin_locale_is_sw(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $partner = $this->invitedSupplier();

        $this->actingAs($admin, 'admin')
            ->withSession(['admin_locale' => 'sw'])
            ->get(route('admin.partners.show', $partner))
            ->assertOk()
            ->assertSee(__('admin.partners.awaiting_activation', [], 'sw'), false)
            ->assertSee(__('admin.partners.copy_link', [], 'sw'), false)
            ->assertSee(__('admin.partners.copied', [], 'sw'), false)
            ->assertSee(__('admin.partners.or_activate_here', [], 'sw'), false)
            ->assertSee(__('admin.partners.resend_activation', [], 'sw'), false)
            ->assertDontSee(__('admin.partners.copy_link', [], 'en'), false)
            ->assertDontSee('Copy activation link', false)
            ->assertDontSee('Awaiting activation', false);
    }

    public function test_activation_link_carries_partner_code_but_not_phone(): void
    {
        $partner = $this->invitedSupplier();
        $url = app(PartnerActivationService::class)->publicActivateUrl($partner);

        $this->assertStringContainsString('partner_code='.$partner->partner_number, $url);
        $this->assertStringNotContainsString('phone=', $url);
        $this->assertStringNotContainsString('715000111', $url);
    }

    public function test_partner_start_does_not_prefill_or_reveal_the_full_phone(): void
    {
        $partner = $this->invitedSupplier();
        $url = app(PartnerActivationService::class)->publicActivateUrl($partner);

        $html = $this->get($url)
            ->assertOk()
            ->assertSee($partner->name, false)
            ->assertSee(__('site.auth.partner_activate_heading_supplier'), false)
            ->assertSee(__('site.auth.partner_activate_brand'), false)
            ->assertSee(__('site.auth.partner_activate_support'), false)
            ->assertSee('Namba ya akaunti ya mshirika', false)
            ->assertDontSee('Wezesha MacLeans Autotraders', false)
            ->assertSee(__('site.auth.partner_phone_ends_in', ['last' => '0111']), false)
            ->assertDontSee('value="255715000111"', false)
            ->getContent();

        $this->assertStringNotContainsString('715000111', $html);
        $this->assertStringContainsString('name="phone"', $html);
    }

    public function test_wrong_phone_is_blocked_without_extra_account_detail(): void
    {
        $partner = $this->invitedSupplier();

        $this->from(route('site.partner.start', ['partner_code' => $partner->partner_number]))
            ->post(route('site.partner.start.lookup'), [
                'partner_code' => $partner->partner_number,
                'phone' => '255715000000',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('phone', __('site.auth.partner_phone_mismatch'));
    }

    public function test_normalized_phone_variants_activate_the_matching_account(): void
    {
        $cases = [
            ['entered' => '0715000222', 'code' => 'PT-SP-TZ-NRM1', 'stored' => '255715000222'],
            ['entered' => '715000333', 'code' => 'PT-SP-TZ-NRM2', 'stored' => '255715000333'],
            ['entered' => '255715000444', 'code' => 'PT-SP-TZ-NRM3', 'stored' => '255715000444'],
        ];

        foreach ($cases as $case) {
            $this->post(route('site.logout'));
            $partner = Vendor::create([
                'name' => 'Phone Variant '.$case['code'],
                'category' => 'supplier',
                'roles' => ['supplier'],
                'status' => 'inactive',
                'partner_number' => $case['code'],
                'phone' => $case['stored'],
                'email' => strtolower($case['code']).'@kopafasta.local',
                'supplier_type' => 'managed_loan',
            ]);

            $this->withSession([])
                ->from(route('site.partner.start', ['partner_code' => $case['code']]))
                ->post(route('site.partner.start.lookup'), [
                    'partner_code' => $case['code'],
                    'phone' => $case['entered'],
                    'phone_local' => ltrim(preg_replace('/\D+/', '', $case['entered']) ?? '', '0'),
                ])
                ->assertRedirect(route('site.partner.setup-pin'));

            $partner->refresh();
            $this->assertNotNull($partner->activated_at, $case['entered']);
            $this->assertNotNull($partner->user_id, $case['entered']);
        }
    }

    public function test_self_activation_reuses_existing_portal_user_with_the_partner_email(): void
    {
        $partner = $this->invitedSupplier();
        $existing = User::factory()->create([
            'name' => 'Existing Portal',
            'email' => $partner->email,
            'phone' => $partner->phone,
            'role' => 'vendor',
            'is_active' => true,
        ]);

        $this->from(route('site.partner.start', ['partner_code' => $partner->partner_number]))
            ->post(route('site.partner.start.lookup'), [
                'partner_code' => $partner->partner_number,
                'phone' => '255715000111',
            ])
            ->assertRedirect(route('site.partner.setup-pin'))
            ->assertSessionMissing('errors');

        $partner->refresh();
        $this->assertSame($existing->id, $partner->user_id);
        $this->assertSame(1, User::query()->where('email', $partner->email)->count());
        $this->assertSame($partner->email, $existing->fresh()->email);
    }

    public function test_self_activation_does_not_fail_when_email_belongs_to_a_non_partner_identity(): void
    {
        $partner = $this->invitedSupplier();
        User::factory()->create([
            'name' => 'Admin MacLean',
            'email' => $partner->email,
            'phone' => '255700000001',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->from(route('site.partner.start', ['partner_code' => $partner->partner_number]))
            ->post(route('site.partner.start.lookup'), [
                'partner_code' => $partner->partner_number,
                'phone' => '255715000111',
            ])
            ->assertRedirect(route('site.partner.setup-pin'))
            ->assertSessionMissing('errors');

        $partner->refresh();
        $this->assertNotNull($partner->user_id);
        $portal = User::query()->findOrFail($partner->user_id);
        $this->assertSame('vendor', $portal->role);
        $this->assertNotSame($partner->email, $portal->email);
        $this->assertSame('phone-verify@kopafasta.local', $partner->email);
        $this->assertSame(2, User::query()->count());
    }

    public function test_self_activation_blocks_email_owned_by_another_partner(): void
    {
        $partner = $this->invitedSupplier();
        $otherUser = User::factory()->create([
            'email' => $partner->email,
            'phone' => '255700000002',
            'role' => 'vendor',
        ]);
        Vendor::create([
            'name' => 'Other Supplier',
            'category' => 'supplier',
            'roles' => ['supplier'],
            'status' => 'active',
            'partner_number' => 'PT-SP-TZ-OTHR',
            'phone' => '255700000002',
            'email' => $partner->email,
            'user_id' => $otherUser->id,
            'activated_at' => now(),
            'supplier_type' => 'managed_loan',
        ]);

        $this->from(route('site.partner.start', ['partner_code' => $partner->partner_number]))
            ->post(route('site.partner.start.lookup'), [
                'partner_code' => $partner->partner_number,
                'phone' => '255715000111',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('email', __('site.auth.partner_identity_conflict'));

        $this->assertNull($partner->fresh()->user_id);
        $this->assertNull($partner->fresh()->activated_at);
    }

    private function invitedSupplier(): Vendor
    {
        return Vendor::create([
            'name' => 'MacLeans Autotraders',
            'category' => 'supplier',
            'roles' => ['supplier'],
            'status' => 'inactive',
            'partner_number' => 'PT-SP-TZ-MHXL',
            'phone' => '255715000111',
            'email' => 'phone-verify@kopafasta.local',
            'supplier_type' => 'managed_loan',
        ]);
    }
}
