<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssertProductionCleanCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_database_passes(): void
    {
        $this->artisan('production:assert-clean')->assertSuccessful();
    }

    public function test_refuses_staging_environment(): void
    {
        app()->instance('env', 'staging');

        $this->artisan('production:assert-clean')->assertFailed();
    }

    public function test_fails_when_a_borrower_exists(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-CLEAN-1',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Test',
            'last_name' => 'Borrower',
            'phone' => '255700099001',
        ]);

        $this->artisan('production:assert-clean')->assertFailed();
    }
}
