<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Phase11FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_partner_route_aliases_are_registered(): void
    {
        $this->assertTrue(Route::has('admin.partners.show'));
        $this->assertTrue(Route::has('admin.partners.edit'));
        $this->assertTrue(Route::has('admin.partners.valuers'));
        $this->assertSame('/admin/partners/1', route('admin.partners.show', ['vendor' => 1], false));
    }

    public function test_legacy_admin_vendor_routes_remain_available(): void
    {
        $this->assertTrue(Route::has('admin.vendors.show'));
        $this->assertSame('/admin/vendors/1', route('admin.vendors.show', ['vendor' => 1], false));
    }

    public function test_partner_create_form_locks_category_from_query_string(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.partners.create', ['category' => 'valuer']))
            ->assertOk()
            ->assertSee('Rates', false)
            ->assertSee('value="valuer"', false);
    }

    public function test_referral_share_message_replaces_placeholders(): void
    {
        $customer = Customer::create([
            'customer_number' => 'CU-P11-001',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Refer',
            'last_name' => 'Member',
            'phone' => '255712345693',
            'referral_code' => 'KPF-REFER001',
        ]);

        $message = app(ReferralService::class)->shareMessage($customer);

        $this->assertStringContainsString('KPF-REFER001', $message);
        $this->assertStringContainsString('ref=', $message);
    }

    public function test_partner_start_page_is_available(): void
    {
        $this->get(route('site.partner.start'))
            ->assertOk()
            ->assertSee(__('site.auth.activate_account'), false)
            ->assertSee(__('site.auth.partner_code_label'), false);
    }
}
