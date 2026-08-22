<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Database\Seeders\BranchSeeder;
use Database\Seeders\DepartmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserCreateWizardFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BranchSeeder::class);
        $this->seed(DepartmentSeeder::class);
    }

    public function test_create_user_page_is_a_wizard_without_branch_or_approval_limit(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.users.create', ['role' => 'partner_support']))
            ->assertOk()
            ->assertSee('data-step-label="Person"', false)
            ->assertSee('data-step-label="Desk"', false)
            ->assertSee('data-step-label="Access"', false)
            ->assertSee('autocomplete="new-password"', false)
            ->assertSee('Head Office, Dar es Salaam', false)
            ->assertDontSee('Approval limit', false)
            ->assertDontSee('Primary department', false)
            ->assertDontSee('>Branch<', false);
    }

    public function test_partner_support_user_lands_on_prt_and_head_office(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $prt = Department::query()->where('code', 'PRT')->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.users.store'), [
                'name' => 'Rogathe Nyela',
                'email' => 'rogathe.support@example.com',
                'phone' => '255653924624',
                'password' => 'secret12',
                'role' => 'partner_support',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $user = User::query()->where('email', 'rogathe.support@example.com')->firstOrFail();
        $this->assertSame('partner_support', $user->role);
        $this->assertSame((int) $prt->id, (int) $user->department_id);
        $this->assertTrue($user->departments->contains('id', $prt->id));
        $this->assertSame('HQ001', $user->branch?->code);
        $this->assertNull($user->approval_limit);
    }

    public function test_staff_can_change_their_own_password(): void
    {
        $user = User::factory()->create([
            'role' => 'partner_support',
            'is_active' => true,
            'password' => 'old-pass',
        ]);

        $this->actingAs($user, 'admin')
            ->get(route('admin.settings.account-security'))
            ->assertOk()
            ->assertSee('Update password', false);

        $this->actingAs($user, 'admin')
            ->post(route('admin.settings.account-security.password'), [
                'current_password' => 'old-pass',
                'password' => 'new-pass1',
                'password_confirmation' => 'new-pass1',
            ])
            ->assertRedirect(route('admin.settings.account-security'));

        $this->assertTrue(Hash::check('new-pass1', $user->fresh()->password));
    }
}
