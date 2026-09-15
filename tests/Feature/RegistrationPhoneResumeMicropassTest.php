<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationPhoneResumeMicropassTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_phone_returns_login_guidance_for_completed_borrower(): void
    {
        $user = User::factory()->create([
            'role' => 'borrower',
            'phone' => '255712345678',
        ]);
        app(PinService::class)->setPin($user, '1234');
        Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-PH'.random_int(1000, 9999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Asha',
            'last_name' => 'Juma',
            'phone' => '255712345678',
        ]);

        $this->assertFalse(\App\Support\BorrowerRegistrationGate::isIncomplete($user->fresh()));

        $response = $this->postJson(route('site.register.check-phone'), [
            'phone' => '255712345678',
        ]);

        $response->assertOk()
            ->assertJson([
                'available' => false,
                'status' => 'active',
                'action' => 'login',
            ]);
        $this->assertSame('login', $response->json('action'));
        $this->assertNotEmpty($response->json('action_label'));
    }

    public function test_check_phone_returns_resume_for_incomplete_registration(): void
    {
        $user = User::factory()->needsPinSetup()->create([
            'role' => 'borrower',
            'is_active' => false,
            'password' => Hash::make('Password1!'),
            'phone' => '255798765432',
        ]);
        Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-IN'.random_int(1000, 9999),
            'type' => 'individual',
            'status' => 'pending',
            'first_name' => 'Incomplete',
            'last_name' => 'Draft',
            'phone' => '255798765432',
            'country_code' => 'TZ',
        ]);

        $this->assertTrue(\App\Support\BorrowerRegistrationGate::isIncomplete($user->fresh()));

        $response = $this->postJson(route('site.register.check-phone'), [
            'phone' => '255798765432',
        ]);

        $response->assertOk()
            ->assertJson([
                'available' => false,
                'status' => 'incomplete',
                'action' => 'resume',
            ]);
        $this->assertNotEmpty($response->json('resume_url'));
    }

    public function test_check_phone_allows_new_number(): void
    {
        $response = $this->postJson(route('site.register.check-phone'), [
            'phone' => '255711000111',
        ]);

        $response->assertOk()->assertJson([
            'available' => true,
            'status' => 'new',
        ]);
    }

    public function test_resume_logs_in_incomplete_borrower_to_setup_pin(): void
    {
        $user = User::factory()->needsPinSetup()->create([
            'role' => 'borrower',
            'is_active' => false,
            'password' => Hash::make('Password1!'),
            'phone' => '255722334455',
        ]);
        Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-RS'.random_int(1000, 9999),
            'type' => 'individual',
            'status' => 'pending',
            'first_name' => 'Resume',
            'last_name' => 'Me',
            'phone' => '255722334455',
            'country_code' => 'TZ',
        ]);

        $response = $this->postJson(route('site.register.resume'), [
            'phone' => '255722334455',
        ]);

        $response->assertOk()->assertJsonPath('ok', true);
        $this->assertAuthenticatedAs($user);
        $this->assertStringContainsString('setup-pin', (string) $response->json('redirect'));
    }
}
