<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Models\Vendor;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase18FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_footer_links_to_affiliate_application(): void
    {
        $this->get(route('site.home'))
            ->assertOk()
            ->assertSee(route('site.affiliate', [], false), false)
            ->assertSee('Become an affiliate', false);
    }

    public function test_affiliate_application_page_supports_swahili_locale(): void
    {
        $this->withSession(['locale' => 'sw'])
            ->get(route('site.affiliate'))
            ->assertOk()
            ->assertSee(__('site.affiliate_apply.title', [], 'sw'), false);
    }

    public function test_admin_settings_dropdown_shows_grouped_navigation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.crb'))
            ->assertOk()
            ->assertSee('Integrations', false)
            ->assertSee('CRB integration', false);
    }

    public function test_admin_partners_nav_includes_affiliate_applications(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.partner-applications.index'))
            ->assertOk()
            ->assertSee('Affiliate applications', false);
    }

    public function test_borrower_documents_page_shows_verification_and_upload_sections(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        Customer::create([
            'user_id'         => $user->id,
            'customer_number' => 'CU-P18-001',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Docs',
            'last_name'       => 'Borrower',
            'phone'           => '255712345804',
        ]);

        $this->actingAs($user)
            ->get(route('site.borrower.documents'))
            ->assertOk()
            ->assertSee(__('borrower.documents_page.verification_title'), false)
            ->assertSee(__('borrower.documents_page.uploaded_title'), false);
    }

    public function test_legacy_vendor_edit_redirects_to_partners_edit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $partner = Vendor::create([
            'vendor_number' => 'PTR-P18-001',
            'name'          => 'Redirect Partner',
            'category'      => 'supplier',
            'status'        => 'active',
            'phone'         => '255712345805',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.vendors.edit', $partner))
            ->assertRedirect(route('admin.partners.edit', $partner));
    }
}
