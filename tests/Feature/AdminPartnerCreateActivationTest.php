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
            ->assertSee('Create this partner?', false)
            ->assertSee('partnerCreateConfirm', false)
            ->assertSee('id="admin-create-form"', false)
            ->assertDontSee('querySelector(`[name=', false);
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

    public function test_admin_can_activate_invited_supplier_by_setting_pin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.partners.store'), [
                'name' => 'UAT Supplier',
                'category' => 'supplier',
                'status' => 'inactive',
                'phone' => '255712345910',
                'email' => 'supplier-uat@example.com',
                'coverage_type' => 'nationwide',
                'activation_mode' => 'invite',
                'supplier_type' => 'managed_loan',
            ])
            ->assertRedirect();

        $partner = Vendor::query()->where('name', 'UAT Supplier')->firstOrFail();
        $this->assertSame('inactive', $partner->status);
        $this->assertNull($partner->user_id);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.partners.reset-pin', $partner), ['pin' => '2468'])
            ->assertRedirect(route('admin.partners.show', $partner));

        $partner->refresh();
        $this->assertSame('active', $partner->status);
        $this->assertNotNull($partner->activated_at);
        $this->assertNotNull($partner->user_id);
        $this->assertSame('vendor', $partner->user->role);
        $this->assertTrue(app(\App\Services\PinService::class)->verify('2468', $partner->user->pin_hash));
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
            ->assertSee('Resend activation', false);

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

    public function test_partner_support_can_open_the_add_partner_form(): void
    {
        $support = User::factory()->create(['role' => 'partner_support', 'is_active' => true]);

        $this->actingAs($support, 'admin')
            ->get(route('admin.partners.create', ['category' => 'valuer']))
            ->assertOk()
            ->assertSee('Create this partner?', false);
    }

    public function test_valuer_create_form_defaults_to_individual_person_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.partners.create', ['category' => 'valuer']))
            ->assertOk()
            ->assertSee('Entity type', false)
            ->assertSee('Full name', false)
            ->assertSee('Choose Individual for a person', false)
            ->assertSee('no trading name, BRELA, or TIN for an individual', false)
            ->assertSee('x-if="isCompany"', false)
            ->assertSee('data-company-docs-step', false)
            ->assertSee('Skip this step for an individual', false);
    }

    public function test_company_partner_create_form_does_not_default_to_individual(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.partners.create', ['category' => 'insurance']))
            ->assertOk()
            ->assertSee('Trading / company name', false)
            ->assertSee('Business documents', false)
            ->assertSee('name="tin"', false)
            ->assertSee('name="registration_number"', false)
            ->assertSee('data-kf-address-fields', false)
            ->assertSee('Region → district', false)
            ->assertSee('value="nationwide"', false)
            ->assertSee('md:grid-cols-2', false);
    }

    public function test_admin_can_create_individual_valuer_without_company_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.partners.store'), [
                'name' => 'Rogathe Nyela',
                'applicant_category' => 'individual',
                'category' => 'valuer',
                'status' => 'inactive',
                'phone' => '255712345903',
                'email' => 'rogathe@example.com',
                'coverage_type' => 'nationwide',
                'activation_mode' => 'draft',
                'legal_name' => 'Should Be Cleared Ltd',
                'registration_number' => 'BRELA-1',
                'tin' => '123456789',
            ])
            ->assertRedirect();

        $partner = Vendor::query()->where('name', 'Rogathe Nyela')->first();
        $this->assertNotNull($partner);
        $this->assertSame('individual', $partner->applicant_category);
        $this->assertTrue($partner->isIndividualApplicant());
        $this->assertNull($partner->legal_name);
        $this->assertNull($partner->registration_number);
        $this->assertNull($partner->tin);
        $this->assertSame('Rogathe Nyela', $partner->contactPersonName());
    }

    public function test_invite_create_normalizes_phone_and_shows_share_card(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.partners.store'), [
                'name' => 'Invite Valuer',
                'applicant_category' => 'individual',
                'category' => 'valuer',
                'status' => 'inactive',
                'phone' => '784275297',
                'coverage_type' => 'nationwide',
                'activation_mode' => 'invite',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Partner created. Share the partner code below so they can activate.')
            ->assertSessionHas('partner_invite_ready', true);

        $partner = Vendor::query()->where('name', 'Invite Valuer')->first();
        $this->assertNotNull($partner);
        $this->assertSame('255784275297', $partner->phone);
        $this->assertNotNull($partner->partner_number);

        $show = $this->actingAs($admin, 'admin')
            ->withSession(['partner_invite_ready' => true])
            ->get(route('admin.partners.show', $partner));
        $show->assertOk()
            ->assertSee('Share activation', false)
            ->assertSee($partner->partner_number, false)
            ->assertSee('Send activation via WhatsApp', false)
            ->assertSee('Copy activation link', false)
            ->assertSee('Copy message', false)
            ->assertSee('wa.me/', false);
    }

    public function test_creating_supplier_with_apostrophe_lands_on_profile_and_list(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.partners.store'), [
                'name' => "Macklin's Auto Traders",
                'category' => 'supplier',
                'status' => 'inactive',
                'phone' => '255712345920',
                'email' => 'macklin@example.com',
                'coverage_type' => 'nationwide',
                'activation_mode' => 'invite',
                'supplier_type' => 'managed_loan',
            ]);

        $partner = Vendor::query()->where('name', "Macklin's Auto Traders")->first();
        $this->assertNotNull($partner);
        $response->assertRedirect(route('admin.partners.show', $partner->id));

        $this->actingAs($admin, 'admin')
            ->get(route('admin.partners.show', $partner->id))
            ->assertOk()
            ->assertSee("Macklin's Auto Traders")
            ->assertSee('Awaiting activation', false)
            ->assertSee('Send activation via WhatsApp', false);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.partners.show', $partner->partner_number))
            ->assertOk()
            ->assertSee("Macklin's Auto Traders");

        $this->actingAs($admin, 'admin')
            ->get(route('admin.partners.index'))
            ->assertOk()
            ->assertSee("Macklin's Auto Traders")
            ->assertSee('Awaiting activation', false);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.partners.onboarding'))
            ->assertOk()
            ->assertSee("Macklin's Auto Traders");

        $activation = app(\App\Services\PartnerActivationService::class);
        $start = $this->get($activation->publicActivateUrl($partner));
        $start->assertOk()
            ->assertSee("Macklin's Auto Traders")
            ->assertSee(__('site.auth.partner_activate_named', ['name' => "Macklin's Auto Traders"]));
    }

    public function test_create_form_omits_payout_and_nida_images_and_locks_phone_prefix(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.partners.create', ['category' => 'valuer']))
            ->assertOk()
            ->assertDontSee('>Payout account<', false)
            ->assertDontSee('National ID (front)', false)
            ->assertSee('data-phone-locked="1"', false)
            ->assertSee('+255', false)
            ->assertSee('nida-boxes', false)
            ->assertSee('verification card goes live', false);
    }

    public function test_supplier_create_with_document_page_fields_does_not_insert_ghost_columns(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.partners.store'), [
                'name' => 'MacLeans Autotraders Pages',
                'legal_name' => 'MacLeans Autotraders Pages',
                'registration_number' => '12345678',
                'tin' => '12345678',
                'category' => 'supplier',
                'applicant_category' => 'company',
                'status' => 'inactive',
                'phone' => '255715222199',
                'email' => 'pages-supplier@example.com',
                'contact_person_name' => 'Maclean Mwaijonga',
                'national_id' => '19800101123456789012',
                'address_region' => 'Dar es Salaam',
                'address_district' => 'Ilala',
                'address_street' => 'Mikocheni',
                'coverage_type' => 'regions',
                'regions' => ['Dar es Salaam'],
                'activation_mode' => 'invite',
                'supplier_type' => 'managed_loan',
                'doc_brela_pages' => null,
                'doc_tin_certificate_pages' => null,
                'doc_business_licence_pages' => null,
                'doc_other_pages' => null,
            ]);

        $partner = Vendor::query()->where('name', 'MacLeans Autotraders Pages')->first();
        $this->assertNotNull($partner);
        $response->assertRedirect(route('admin.partners.show', $partner->id));
        $this->assertSame('supplier', $partner->category);
        $this->assertSame(['supplier'], $partner->roles);
        $this->assertSame('managed_loan', $partner->supplier_type);
        $this->assertSame('inactive', $partner->status);
        $this->assertNull($partner->user_id);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.partners.show', $partner->id))
            ->assertOk()
            ->assertSee('MacLeans Autotraders Pages', false)
            ->assertSee('Awaiting activation', false)
            ->assertSee('Send activation via WhatsApp', false);
    }

    public function test_supplier_create_without_coverage_checkboxes_persists_and_lists(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.partners.store'), [
                'name' => 'KF Staging Path Supplier',
                'category' => 'supplier',
                'status' => 'inactive',
                'phone' => '255712345930',
                'email' => 'kf-staging-path@example.com',
                'activation_mode' => 'invite',
                'supplier_type' => 'managed_loan',
            ]);

        $partner = Vendor::query()->where('name', 'KF Staging Path Supplier')->first();
        $this->assertNotNull($partner);
        $this->assertSame('nationwide', $partner->coverage_type);
        $this->assertSame([], $partner->regions ?? []);
        $this->assertSame('inactive', $partner->status);
        $this->assertNull($partner->user_id);
        $response->assertRedirect(route('admin.partners.show', $partner->id));

        $this->actingAs($admin, 'admin')
            ->get(route('admin.partners.index'))
            ->assertOk()
            ->assertSee('KF Staging Path Supplier', false)
            ->assertSee('Asset supplier', false);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.partners.index', ['role' => 'supplier']))
            ->assertOk()
            ->assertSee('KF Staging Path Supplier', false);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.partners.index', ['q' => $partner->partner_number]))
            ->assertOk()
            ->assertSee('KF Staging Path Supplier', false);

        $activation = app(\App\Services\PartnerActivationService::class);
        $message = $activation->shareMessage($partner);
        $this->assertStringContainsString('Thank you for registering as an Asset Supplier', $message);
        $this->assertStringContainsString('Use the secure link below', $message);
        $this->assertStringContainsString((string) $partner->partner_number, $message);
        $this->assertStringContainsString($activation->publicActivateUrl($partner), $message);
    }

    public function test_supplier_create_uses_address_region_when_coverage_boxes_empty(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.partners.store'), [
                'name' => 'KF Address Region Supplier',
                'category' => 'supplier',
                'status' => 'inactive',
                'phone' => '255712345931',
                'coverage_type' => 'regions',
                'address_region' => 'Dar es Salaam',
                'address_district' => 'Ilala',
                'activation_mode' => 'invite',
                'supplier_type' => 'managed_loan',
            ])
            ->assertRedirect();

        $partner = Vendor::query()->where('name', 'KF Address Region Supplier')->first();
        $this->assertNotNull($partner);
        $this->assertSame('regions', $partner->coverage_type);
        $this->assertSame(['Dar es Salaam'], $partner->regions);
        $this->assertSame('Dar es Salaam', data_get($partner->metadata, 'residence.region'));
    }

    public function test_failed_supplier_create_does_not_leave_a_ghost_record(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->mock(\App\Services\PartnerActivationService::class, function ($mock) {
            $mock->shouldReceive('requiresActivation')->andReturn(true);
            $mock->shouldReceive('sendActivationInvite')->andThrow(new \RuntimeException('activation failed'));
        });

        $this->withoutExceptionHandling();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('activation failed');

        try {
            $this->actingAs($admin, 'admin')
                ->post(route('admin.partners.store'), [
                    'name' => 'KF Ghost Supplier',
                    'category' => 'supplier',
                    'status' => 'inactive',
                    'phone' => '255712345932',
                    'activation_mode' => 'invite',
                    'supplier_type' => 'managed_loan',
                ]);
        } finally {
            $this->assertNull(Vendor::query()->where('name', 'KF Ghost Supplier')->first());
        }
    }
}
