<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthorizationAuditLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_policy_denial_writes_audit_log(): void
    {
        $branch = Branch::create([
            'code' => 'AUD1',
            'name' => 'B',
            'region' => 'R',
            'is_active' => true,
        ]);
        $otherBranch = Branch::create([
            'code' => 'AUD2',
            'name' => 'B2',
            'region' => 'R',
            'is_active' => true,
        ]);
        $customer = Customer::create([
            'branch_id' => $branch->id,
            'customer_number' => 'C-AUD-1',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Aud',
            'last_name' => 'Test',
        ]);

        $officer = User::factory()->create([
            'role' => 'officer',
            'email' => 'aud-off@example.com',
            'branch_id' => $otherBranch->id,
        ]);

        Sanctum::actingAs($officer);

        $this->getJson('/api/customers/'.$customer->id)->assertStatus(403);

        $log = AuditLog::where('event', 'authorization.denied')
            ->where('user_id', $officer->id)
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $payload = json_decode($log->new_values, true);
        $this->assertSame('GET', $payload['method']);
        $this->assertSame('api/customers/'.$customer->id, $payload['path']);
    }

    public function test_role_middleware_denial_writes_audit_log(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'email' => 'aud-cust@example.com',
        ]);

        Sanctum::actingAs($customer);

        $this->getJson('/api/system/users')->assertStatus(403);

        $log = AuditLog::where('event', 'authorization.role_denied')
            ->where('user_id', $customer->id)
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $payload = json_decode($log->new_values, true);
        $this->assertSame('customer', $payload['user_role']);
        $this->assertContains('admin', $payload['required_roles']);
    }
}
