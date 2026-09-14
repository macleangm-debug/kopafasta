<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MicroPassP0Kyc405FeatureTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        return Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-P0405-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Kyc',
            'last_name' => 'Fix',
            'phone' => '+255700'.random_int(100000, 999999),
            'membership_status' => 'active',
            'membership_issued_at' => now(),
            'membership_expires_at' => now()->addYear(),
        ]);
    }

    public function test_profile_kyc_section_accepts_post_without_405(): void
    {
        $customer = $this->makeCustomer();

        $response = $this->actingAs($customer->user)->post(route('site.borrower.profile.update', ['section' => 'kyc']), [
            'focus' => 'income',
        ]);

        $this->assertNotSame(405, $response->status());
        $response->assertRedirect();
    }

    public function test_get_profile_kyc_redirects_to_activity(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($customer->user)
            ->get(route('site.borrower.profile', ['section' => 'kyc']))
            ->assertRedirect(route('site.borrower.profile', ['section' => 'activity', 'focus' => 'income']));
    }
}
