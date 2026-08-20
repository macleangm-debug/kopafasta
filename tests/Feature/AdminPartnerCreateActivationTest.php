<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPartnerCreateActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_partner_page_uses_confirm_before_submit_wizard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.partners.create', ['category' => 'insurance']))
            ->assertOk()
            ->assertSee('data-confirm-before-submit', false)
            ->assertSee('Confirm &amp; activate', false)
            ->assertSee('Activate account now', false);
    }

    public function test_admin_can_create_insurance_partner_and_activate_now(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.partners.store'), [
                'name' => 'Aventris Insurance',
                'legal_name' => 'Aventris Insurance',
                'category' => 'insurance',
                'status' => 'inactive',
                'phone' => '255712345900',
                'email' => 'aventris@example.com',
                'coverage_type' => 'nationwide',
                'activation_mode' => 'activate_now',
                'activation_pin' => '4321',
                'notify_partner' => '0',
            ])
            ->assertRedirect();

        $partner = Vendor::query()->where('name', 'Aventris Insurance')->first();
        $this->assertNotNull($partner);
        $this->assertSame('active', $partner->status);
        $this->assertNotNull($partner->activated_at);
        $this->assertNotNull($partner->user_id);
        $this->assertTrue(app(\App\Services\PinService::class)->verify('4321', $partner->user->pin_hash));
        $this->assertTrue(app(\App\Services\PinService::class)->hasPin($partner->user));
    }

    public function test_admin_can_save_partner_as_draft_without_activation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.partners.store'), [
                'name' => 'Draft Insurance Co',
                'category' => 'insurance',
                'status' => 'inactive',
                'phone' => '255712345901',
                'coverage_type' => 'nationwide',
                'activation_mode' => 'draft',
            ])
            ->assertRedirect();

        $partner = Vendor::query()->where('name', 'Draft Insurance Co')->first();
        $this->assertNotNull($partner);
        $this->assertSame('inactive', $partner->status);
        $this->assertNull($partner->activated_at);
        $this->assertNull($partner->activation_token);
    }

    public function test_admin_can_reset_partner_pin_from_partner_show(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'admin')
            ->post(route('admin.partners.store'), [
                'name' => 'Reset PIN Valuer',
                'category' => 'valuer',
                'status' => 'inactive',
                'phone' => '255712345902',
                'email' => 'reset-pin@example.com',
                'coverage_type' => 'nationwide',
                'activation_mode' => 'activate_now',
                'activation_pin' => '1111',
            ])
            ->assertRedirect();

        $partner = Vendor::query()->where('name', 'Reset PIN Valuer')->firstOrFail();
        $this->assertTrue(app(\App\Services\PinService::class)->verify('1111', $partner->user->pin_hash));

        $this->actingAs($admin, 'admin')
            ->get(route('admin.partners.show', $partner))
            ->assertOk()
            ->assertSee('Portal PIN', false)
            ->assertSee('Re-issue activation link', false);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.partners.reset-pin', $partner), ['pin' => '9999'])
            ->assertRedirect(route('admin.partners.show', $partner));

        $this->assertTrue(app(\App\Services\PinService::class)->verify('9999', $partner->user->fresh()->pin_hash));
    }

    public function test_screening_officer_cannot_open_the_add_partner_form(): void
    {
        $officer = User::factory()->create(['role' => 'officer', 'is_active' => true]);

        $this->actingAs($officer, 'admin')
            ->get(route('admin.partners.create', ['category' => 'valuer']))
            ->assertForbidden();
    }

    public function test_credit_manager_cannot_open_the_add_partner_form(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);

        $this->actingAs($manager, 'admin')
            ->get(route('admin.partners.create', ['category' => 'valuer']))
            ->assertForbidden();
    }
}
