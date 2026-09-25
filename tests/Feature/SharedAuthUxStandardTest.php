<?php

namespace Tests\Feature;

use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharedAuthUxStandardTest extends TestCase
{
    use RefreshDatabase;

    public function test_borrower_and_partner_auth_surfaces_share_the_premium_card(): void
    {
        $login = $this->get(route('site.login'))->assertOk()->getContent();
        $register = $this->get(route('site.register.borrower'))->assertOk()->getContent();
        $forgot = $this->get(route('site.forgot-pin'))->assertOk()->getContent();

        foreach ([$login, $register, $forgot] as $html) {
            $this->assertStringContainsString('kf-premium-panel', $html);
            $this->assertStringContainsString('kf-auth-form', $html);
        }

        $this->assertStringContainsString(__('site.auth.welcome_back'), $login);
        $this->assertStringContainsString(__('site.auth.shell.login_support'), $login);
        $this->assertStringContainsString(__('site.auth.shell.register_heading'), $register);
        $this->assertStringContainsString(__('borrower.register.details_title'), $register);
        $this->assertStringContainsString(__('borrower.register.details_body'), $register);
        $this->assertStringNotContainsString('<h2 class="text-2xl font-bold text-gray-900">'.e(__('borrower.register.details_title')), $register);
        $this->assertStringContainsString('name="phone"', $login);
        $this->assertStringContainsString('name="pin"', $login);
        $this->assertStringContainsString('kf-auth-pin', $login);
    }

    public function test_secure_account_and_partner_activation_reuse_the_same_shell(): void
    {
        $partner = Vendor::create([
            'name' => 'Shared Auth Supplier',
            'category' => 'supplier',
            'roles' => ['supplier'],
            'status' => 'inactive',
            'partner_number' => 'PT-SP-TZ-AUTH',
            'phone' => '255715000999',
            'email' => 'shared-auth@kopafasta.local',
            'supplier_type' => 'managed_loan',
        ]);

        $activation = $this->get(route('site.partner.start', ['partner_code' => $partner->partner_number]))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('kf-premium-panel', $activation);
        $this->assertStringContainsString('kf-auth-form', $activation);
        $this->assertStringContainsString($partner->name, $activation);
    }
}
