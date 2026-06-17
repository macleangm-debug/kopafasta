<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerDossierTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_customer_dossier(): void
    {
        $branch = Branch::create([
            'code'      => 'BR1',
            'name'      => 'Main Branch',
            'region'    => 'Dar es Salaam',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'branch_id'       => $branch->id,
            'customer_number' => 'CU-TEST-001',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Jane',
            'last_name'       => 'Borrower',
            'phone'           => '255712345678',
        ]);

        $admin = User::factory()->create([
            'role'  => 'super_admin',
            'email' => 'dossier-admin@example.com',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertSee('Jane Borrower');
    }

    public function test_customer_dossier_service_builds_without_error(): void
    {
        $customer = Customer::create([
            'customer_number' => 'CU-TEST-002',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'John',
            'last_name'       => 'Doe',
            'phone'           => '255700000001',
        ]);

        $dossier = app(\App\Services\CustomerDossierService::class)->dossier($customer);

        $this->assertSame($customer->id, $dossier['customer']->id);
        $this->assertArrayHasKey('profile', $dossier);
        $this->assertArrayHasKey('checklist', $dossier);
    }
}
