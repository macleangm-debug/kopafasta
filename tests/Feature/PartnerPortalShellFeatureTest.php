<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use App\Services\PartnerPortalNavService;
use App\Services\PartnerPortalRedirectService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerPortalShellFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_insurance_partner_gets_cover_jobs_nav_not_supplier(): void
    {
        $partner = Vendor::create([
            'vendor_number' => 'PT-IN-TZ-TEST',
            'name' => 'Cover Co',
            'category' => 'insurance',
            'roles' => ['insurance'],
            'status' => 'active',
            'phone' => '255712349001',
        ]);

        $nav = app(PartnerPortalNavService::class)->serviceNav($partner);
        $keys = collect($nav)->pluck('key')->all();

        $this->assertSame(['dashboard', 'tasks', 'payments', 'notifications', 'profile', 'support'], $keys);
        $this->assertSame(__('site.partner_portal.nav_cover_jobs'), $nav[1]['label']);
        $this->assertFalse($partner->isSupplier());
        $this->assertTrue($partner->isInsurance());
        $this->assertSame('service', $partner->portalShell());
    }

    public function test_insurance_login_lands_on_partner_portal_not_supplier(): void
    {
        $user = User::factory()->create(['role' => 'vendor']);
        app(PinService::class)->setPin($user, '1234');
        Vendor::create([
            'vendor_number' => 'PT-IN-TZ-LOG',
            'name' => 'Insure Login Co',
            'category' => 'insurance',
            'roles' => ['insurance'],
            'status' => 'active',
            'phone' => '255712349002',
            'user_id' => $user->id,
            'activated_at' => now(),
        ]);

        $url = app(PartnerPortalRedirectService::class)->homeUrl($user);
        $this->assertSame(route('site.partner.dashboard'), $url);

        $this->actingAs($user)
            ->get('/supplier')
            ->assertRedirect(route('site.partner.dashboard'));
    }

    public function test_admin_create_keeps_insurance_category_when_roles_conflict(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.partners.store'), [
                'name' => 'Aventris Fix Co',
                'category' => 'insurance',
                'roles' => ['supplier'],
                'status' => 'inactive',
                'phone' => '255712349003',
                'coverage_type' => 'nationwide',
                'activation_mode' => 'draft',
            ])
            ->assertRedirect();

        $partner = Vendor::query()->where('name', 'Aventris Fix Co')->firstOrFail();
        $this->assertSame('insurance', $partner->category);
        $this->assertSame(['insurance', 'supplier'], $partner->roles);
        $this->assertTrue($partner->isInsurance());
        $this->assertFalse($partner->isSupplier());
    }
}
