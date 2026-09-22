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
            'region'          => 'Dar es Salaam',
            'district'        => 'Ilala',
        ]);

        $admin = User::factory()->create([
            'role'  => 'super_admin',
            'email' => 'dossier-admin@example.com',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertSee('Jane Borrower')
            ->assertSee('About you')
            ->assertSee('Where you live')
            ->assertSee('Eligibility to apply');
    }

    public function test_member_360_profile_tabs_expose_actual_categories(): void
    {
        $customer = Customer::create([
            'customer_number' => 'CU-TEST-003',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Amina',
            'last_name'       => 'Hassan',
            'phone'           => '255700000003',
            'region'          => 'Arusha',
            'district'        => 'Arusha',
            'street'          => 'Sokoine Road 12',
        ]);

        $admin = User::factory()->create([
            'role'  => 'super_admin',
            'email' => 'dossier-tabs@example.com',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.show', ['customer' => $customer, 'tab' => 'about']))
            ->assertOk()
            ->assertSee('Amina Hassan')
            ->assertSee('Next of kin / family');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.show', ['customer' => $customer, 'tab' => 'residence']))
            ->assertOk()
            ->assertSee('Sokoine Road 12')
            ->assertSee('Residence proof');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.show', ['customer' => $customer, 'tab' => 'payment']))
            ->assertOk()
            ->assertSee('Payment account');
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
        $this->assertArrayHasKey('documents_by_context', $dossier);
        $this->assertArrayHasKey('by_category', $dossier['eligibility']);
        $this->assertArrayHasKey('payment_accounts', $dossier);
    }
}
