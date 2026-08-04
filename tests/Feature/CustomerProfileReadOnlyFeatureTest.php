<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerProfileReadOnlyFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $branch = Branch::create([
            'code'      => 'CP'.random_int(10, 99),
            'name'      => 'CP Branch',
            'region'    => 'Dar',
            'is_active' => true,
        ]);

        return User::factory()->create([
            'role'      => 'admin',
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
    }

    private function customer(): Customer
    {
        return Customer::create([
            'user_id'         => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-CP-'.random_int(100, 999),
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Amina',
            'last_name'       => 'Juma',
            'phone'           => '2557'.random_int(10000000, 99999999),
        ]);
    }

    public function test_customer_profile_is_premium_read_only(): void
    {
        $admin = $this->staff();
        $customer = $this->customer();

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Member profile', $html);
        $this->assertStringContainsString('Read-only', $html);
        $this->assertStringContainsString('Profile sections', $html);
        $this->assertStringContainsString('Trust · repayment', $html);
        $this->assertStringNotContainsString('Advanced edit', $html);
        $this->assertStringNotContainsString('Upload document', $html);
        $this->assertStringNotContainsString('Save personal details', $html);
    }

    public function test_customer_section_update_is_forbidden(): void
    {
        $admin = $this->staff();
        $customer = $this->customer();

        $this->actingAs($admin, 'admin')
            ->put(route('admin.customers.section.update', [$customer, 'personal']), [
                'first_name' => 'Changed',
                'last_name'  => 'Name',
                'phone'      => '255700000000',
            ])
            ->assertForbidden();
    }

    public function test_payments_and_payout_ledgers_load(): void
    {
        $admin = $this->staff();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.payments.ledger'))
            ->assertOk()
            ->assertSee('Payments ledger');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.payouts.ledger'))
            ->assertOk()
            ->assertSee('Payout ledger');
    }
}
