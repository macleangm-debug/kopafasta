<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\User;
use App\Services\PartnerWorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerWorkspaceSwitcherFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_multi_role_partner_can_switch_workspace_without_new_identity(): void
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'pin_hash' => bcrypt('1234'),
            'pin_set_at' => now(),
        ]);
        $partner = Partner::create([
            'user_id' => $user->id,
            'vendor_number' => 'PT-MULTI-001',
            'name' => 'Maclean Mwaijonga',
            'phone' => '255700000001',
            'email' => 'multi@example.com',
            'category' => 'supplier',
            'roles' => ['supplier', 'affiliate'],
            'status' => 'active',
            'activated_at' => now(),
        ]);

        $ws = app(PartnerWorkspaceService::class);
        $this->assertTrue($ws->canSwitch($partner));
        $this->assertSame('supplier', $ws->currentKey($partner));
        $this->assertTrue($partner->isAffiliate());
        $this->assertTrue($partner->isSupplier());

        $this->actingAs($user)
            ->post(route('site.partner.workspace.switch'), ['workspace' => 'affiliate'])
            ->assertRedirect(route('site.affiliate.dashboard'));

        $this->assertSame('affiliate', $ws->currentKey($partner->fresh()));
        $this->assertSame($partner->id, Partner::query()->where('user_id', $user->id)->value('id'));
    }

    public function test_single_role_partner_has_no_workspace_switcher(): void
    {
        $partner = Partner::create([
            'vendor_number' => 'PT-ONE-001',
            'name' => 'Single Role',
            'phone' => '255700000002',
            'email' => 'one@example.com',
            'category' => 'affiliate',
            'roles' => ['affiliate'],
            'status' => 'active',
        ]);

        $this->assertFalse(app(PartnerWorkspaceService::class)->canSwitch($partner));
    }
}
