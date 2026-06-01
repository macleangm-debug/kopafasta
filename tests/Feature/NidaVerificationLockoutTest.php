<?php

namespace Tests\Feature;

use App\DataTransferObjects\CrbIdentityResult;
use App\Models\Customer;
use App\Models\User;
use App\Services\CrbService;
use App\Services\NidaVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class NidaVerificationLockoutTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(array $overrides = []): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);

        return Customer::create(array_merge([
            'user_id'         => $user->id,
            'customer_number' => 'C-NIDA'.random_int(100, 999),
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Asha',
            'last_name'       => 'Mwangi',
            'phone'           => '+255700'.random_int(100000, 999999),
            'date_of_birth'   => '1990-01-15',
            'gender'          => 'female',
        ], $overrides));
    }

    private function mockNameMismatchLookup(): void
    {
        $this->mock(CrbService::class, function ($mock): void {
            $mock->shouldReceive('usesStub')->andReturn(true);
            $mock->shouldReceive('verifyConsumerIdentity')
                ->andReturn(CrbIdentityResult::verified(
                    fullName: 'Neema Hassan Juma',
                    firstName: 'Neema',
                    lastName: 'Juma',
                    dateOfBirth: '1990-01-15',
                    gender: 'female',
                    nationalId: '19900115-12345-67890-12',
                    searchScore: '100%',
                ));
        });
    }

    public function test_name_mismatch_increments_attempt_counter(): void
    {
        Config::set('identity_verification.max_mismatch_attempts', 3);

        $customer = $this->makeCustomer();
        $this->actingAs($customer->user);
        $this->mockNameMismatchLookup();

        $service = app(NidaVerificationService::class);
        $result = $service->verify($customer, '19900115-12345-67890-12');

        $this->assertFalse($result->success);
        $this->assertSame('name_mismatch', $result->status);

        $customer->refresh();
        $this->assertSame(1, $customer->nida_mismatch_attempts);
        $this->assertNull($customer->nida_locked_until);
        $this->assertSame('name_mismatch', $customer->nida_verification_status);
    }

    public function test_max_mismatch_attempts_locks_customer_and_user(): void
    {
        Config::set('identity_verification.max_mismatch_attempts', 2);
        Config::set('identity_verification.lock_hours', 24);

        $customer = $this->makeCustomer();
        $this->actingAs($customer->user);
        $this->mockNameMismatchLookup();

        $service = app(NidaVerificationService::class);
        $nida = '19900115-12345-67890-12';

        $service->verify($customer, $nida);
        $service->verify($customer->fresh(), $nida);

        $customer->refresh();
        $user = $customer->user()->first();

        $this->assertSame(2, $customer->nida_mismatch_attempts);
        $this->assertNotNull($customer->nida_locked_until);
        $this->assertTrue($customer->nida_locked_until->isFuture());
        $this->assertNotNull($user->locked_until);
        $this->assertTrue($user->locked_until->isFuture());
    }

    public function test_locked_customer_cannot_verify_again(): void
    {
        Config::set('identity_verification.max_mismatch_attempts', 1);
        Config::set('identity_verification.lock_hours', 24);

        $customer = $this->makeCustomer();
        $this->actingAs($customer->user);
        $this->mockNameMismatchLookup();

        $service = app(NidaVerificationService::class);
        $nida = '19900115-12345-67890-12';

        $service->verify($customer, $nida);
        $result = $service->verify($customer->fresh(), $nida);

        $this->assertFalse($result->success);
        $this->assertSame('locked', $result->status);
    }

    public function test_admin_unlock_clears_customer_and_user_lock(): void
    {
        $customer = $this->makeCustomer([
            'nida_mismatch_attempts' => 3,
            'nida_locked_until'      => now()->addDay(),
        ]);
        $customer->user->forceFill(['locked_until' => now()->addDay()])->save();

        $admin = User::factory()->create(['role' => 'admin']);
        $service = app(NidaVerificationService::class);

        $service->unlockIdentityVerification($customer->fresh(), $admin);

        $customer->refresh();
        $user = $customer->user()->first();

        $this->assertSame(0, $customer->nida_mismatch_attempts);
        $this->assertNull($customer->nida_locked_until);
        $this->assertNull($user->locked_until);
    }
}
