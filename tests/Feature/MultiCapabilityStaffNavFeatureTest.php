<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Services\ConsoleNavService;
use App\Services\PermissionService;
use Database\Seeders\DepartmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiCapabilityStaffNavFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DepartmentSeeder::class);
        $this->seedRoles(['credit_analyst', 'officer', 'agent']);
    }

    public function test_analyst_plus_agent_sees_tickets_not_admin_settings(): void
    {
        $user = $this->multiCap(['credit_analyst', 'agent'], 'credit_analyst');

        $this->assertTrue($user->hasPermission('support.tickets'));
        $this->assertTrue($user->hasPermission('applications.view'));
        $this->assertFalse($user->hasPermission('settings.manage'));
        $this->assertFalse($user->hasPermission('users.manage'));

        $sections = collect(app(ConsoleNavService::class)->visibleSections($user));
        $labels = $sections->pluck('label')->all();

        $this->assertContains('Home', $labels);
        $this->assertContains('Lending', $labels);
        $this->assertContains('Communications', $labels);
        $this->assertNotContains('Settings', $labels);
        $this->assertNotContains('More', $labels);
        $this->assertNotContains('Money', $labels);
        $this->assertNotContains('Growth', $labels);

        $ticketRoutes = collect($sections->firstWhere('label', 'Communications')['items'] ?? [])
            ->pluck(1)
            ->all();
        $this->assertContains('admin.support-tickets.index', $ticketRoutes);

        $lendingRoutes = collect($sections->firstWhere('label', 'Lending')['items'] ?? [])
            ->pluck(1)
            ->all();
        $this->assertContains('admin.loan-applications.pipeline.under-review', $lendingRoutes);
        $this->assertContains('admin.teams.screening', $lendingRoutes);
        $this->assertContains('admin.loan-applications.index', $lendingRoutes);
        $this->assertNotContains('admin.loan-applications.pre-approvals', $lendingRoutes);
        $this->assertNotContains('admin.teams.committee', $lendingRoutes);
        $this->assertNotContains('admin.teams.management', $lendingRoutes);
        $this->assertNotContains('admin.loan-applications.pipeline.approved', $lendingRoutes);
        $this->assertNotContains('admin.loan-applications.pipeline.disbursement', $lendingRoutes);
        $this->assertNotContains('admin.loans.disbursement', $lendingRoutes);
        $this->assertNotContains('admin.loans.index', $lendingRoutes);

        $this->actingAs($user, 'admin')
            ->get(route('admin.support-tickets.index'))
            ->assertOk();

        $this->actingAs($user, 'admin')
            ->get(route('admin.support-tickets.create'))
            ->assertOk();

        $this->actingAs($user, 'admin')
            ->get(route('admin.settings.index'))
            ->assertForbidden();
    }

    public function test_officer_plus_analyst_plus_agent_keeps_screening_and_tickets(): void
    {
        $user = $this->multiCap(['officer', 'credit_analyst', 'agent'], 'credit_analyst');

        $sections = collect(app(ConsoleNavService::class)->visibleSections($user));
        $labels = $sections->pluck('label')->all();

        $this->assertContains('Lending', $labels);
        $this->assertContains('Communications', $labels);
        $this->assertNotContains('Settings', $labels);

        $lendingRoutes = collect($sections->firstWhere('label', 'Lending')['items'] ?? [])
            ->pluck(1)
            ->all();
        $this->assertContains('admin.loan-applications.pipeline.under-review', $lendingRoutes);
        $this->assertNotContains('admin.loan-applications.pre-approvals', $lendingRoutes);
        $this->assertNotContains('admin.loan-applications.pipeline.management-approval', $lendingRoutes);
    }

    public function test_credit_analyst_alone_does_not_see_unrestricted_lending(): void
    {
        $user = $this->multiCap(['credit_analyst'], 'credit_analyst');

        $lendingRoutes = collect(app(ConsoleNavService::class)->visibleSections($user))
            ->firstWhere('label', 'Lending')['items'] ?? [];

        $allApps = collect($lendingRoutes)->first(
            fn ($item) => ($item[1] ?? '') === 'admin.loan-applications.index'
                && empty($item[3] ?? null)
        );
        $mine = collect($lendingRoutes)->first(
            fn ($item) => ($item[1] ?? '') === 'admin.loan-applications.index'
                && (($item[3]['mine'] ?? null) == 1)
        );

        $this->assertNull($allApps);
        $this->assertNotNull($mine);
        $this->assertSame('My appraisal queue', $mine[0]);

        $routes = collect($lendingRoutes)->pluck(1)->all();
        $this->assertContains('admin.loan-applications.pipeline.under-review', $routes);
        $this->assertNotContains('admin.loans.index', $routes);
        $this->assertNotContains('admin.loan-applications.pre-approvals', $routes);
    }

    public function test_permission_union_includes_support_tickets_from_agent_capability(): void
    {
        $user = User::factory()->create([
            'role' => 'officer',
            'roles' => ['officer', 'agent'],
            'is_active' => true,
        ]);

        $perms = app(PermissionService::class)->forUser($user);
        $this->assertContains('support.tickets', $perms);
        $this->assertTrue($user->hasRole('agent'));
        $this->assertTrue($user->hasRole('officer'));
    }

    /** @param  list<string>  $codes */
    private function multiCap(array $codes, string $primary): User
    {
        $und = Department::query()->where('code', 'UND')->firstOrFail();
        $cs = Department::query()->where('code', 'CS')->firstOrFail();

        $deptIds = [$und->id];
        if (in_array('agent', $codes, true)) {
            $deptIds[] = $cs->id;
        }

        $user = User::factory()->create([
            'role' => $primary,
            'roles' => $codes,
            'department_id' => $und->id,
            'is_active' => true,
        ]);
        $user->departments()->sync($deptIds);

        return $user->fresh(['departments']);
    }

    /** @param  list<string>  $codes */
    private function seedRoles(array $codes): void
    {
        foreach ($codes as $code) {
            Role::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => ucfirst(str_replace('_', ' ', $code)),
                    'permissions' => config('permissions.defaults.'.$code, []),
                    'is_system' => true,
                ]
            );
        }
    }
}
