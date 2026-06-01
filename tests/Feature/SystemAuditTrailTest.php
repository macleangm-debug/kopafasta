<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_resource_create_writes_audit_log(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'email' => 'audit-admin@example.com',
        ]);

        $this->actingAs($admin, 'admin')->post(route('admin.branches.store'), [
            'code' => 'TST1',
            'name' => 'Test Branch',
            'region' => 'Dar es Salaam',
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'admin.branches.created',
            'user_id' => $admin->id,
        ]);
    }

    public function test_borrower_action_writes_audit_log(): void
    {
        $user = User::factory()->create([
            'role' => 'borrower',
            'email' => 'audit-borrower@example.com',
        ]);

        app(AuditService::class)->logBorrower($user, 'payment.submitted', null, [
            'reference' => 'MPESA123',
            'amount'    => 50000,
        ]);

        $log = AuditLog::where('event', 'borrower.payment.submitted')->first();

        $this->assertNotNull($log);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('MPESA123', $log->new_values['reference'] ?? null);
    }

    public function test_audit_service_redacts_sensitive_fields(): void
    {
        $sanitized = app(AuditService::class)->sanitize([
            'email' => 'user@example.com',
            'password' => 'secret123',
            'pin' => '1234',
        ]);

        $this->assertSame('user@example.com', $sanitized['email']);
        $this->assertSame('[redacted]', $sanitized['password']);
        $this->assertSame('[redacted]', $sanitized['pin']);
    }
}
