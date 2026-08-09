<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Services\PinRecoveryChallengeService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PinRecoveryChallengeFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_pin_requires_security_answers(): void
    {
        $user = $this->makeBorrowerUser();

        $this->actingAs($user)
            ->get(route('site.borrower.setup-pin'))
            ->assertOk()
            ->assertSee(__('site.auth.pin_recovery.enroll_notice_title'), false);

        $keys = session('pin_setup_question_keys');
        $this->assertIsArray($keys);
        $this->assertCount(3, $keys);

        $answers = [];
        foreach ($keys as $key) {
            $answers[$key] = $key === 'nida_middle4' ? '4582' : 'Test Answer';
        }

        $this->actingAs($user)
            ->post(route('site.borrower.setup-pin.post'), [
                'pin' => '1234',
                'pin_confirmation' => '1234',
                'answers' => $answers,
            ])
            ->assertRedirect(route('site.borrower.dashboard'));

        $user->refresh();
        $this->assertTrue(app(PinService::class)->hasPin($user));
        $this->assertTrue(app(PinRecoveryChallengeService::class)->hasEnrolledAnswers($user));
    }

    public function test_forgot_pin_uses_enrolled_answers_not_otp(): void
    {
        $user = $this->makeBorrowerUser();
        app(PinService::class)->setPin($user, '1234');

        $keys = ['mother_first_name', 'primary_school', 'nida_middle4'];
        app(PinRecoveryChallengeService::class)->enroll($user, [
            'mother_first_name' => 'Asha',
            'primary_school' => 'Uhuru Primary',
            'nida_middle4' => '4582',
        ]);

        $this->post(route('site.forgot-pin.start'), ['phone' => $user->phone])
            ->assertRedirect(route('site.forgot-pin', ['step' => 2]));

        $this->assertSame('kba', session('pin_recovery_mode'));
        $this->assertNotEmpty(session('pin_recovery_token'));

        $this->withSession([
            'pin_recovery_token' => session('pin_recovery_token'),
            'pin_recovery_mode' => 'kba',
            'pin_recovery_questions' => session('pin_recovery_questions'),
            'pin_recovery_required' => 2,
            'pin_recovery_phone' => $user->phone,
        ])->post(route('site.forgot-pin.reset-challenge'), [
            'token' => session('pin_recovery_token'),
            'phone' => $user->phone,
            'answers' => [
                'mother_first_name' => 'Asha',
                'primary_school' => 'Uhuru Primary',
                'nida_middle4' => '0000', // one wrong is ok if 2 of 3 match
            ],
            'pin' => '9876',
            'pin_confirmation' => '9876',
        ])->assertRedirect(route('site.login', [
            'phone' => $user->phone,
            'auth_method' => 'pin',
        ]));

        $user->refresh();
        $this->assertTrue(app(PinService::class)->verify('9876', $user->pin_hash));
    }

    public function test_forgot_pin_blocks_when_not_enrolled(): void
    {
        $user = $this->makeBorrowerUser();
        app(PinService::class)->setPin($user, '1234');

        $this->from(route('site.forgot-pin'))
            ->post(route('site.forgot-pin.start'), ['phone' => $user->phone])
            ->assertRedirect(route('site.forgot-pin'))
            ->assertSessionHasErrors('phone');
    }

    private function makeBorrowerUser(): User
    {
        $user = User::factory()->create([
            'role' => 'borrower',
            'phone' => '255712345678',
            'is_active' => true,
        ]);

        Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-PIN-'.random_int(100, 999),
            'type' => 'individual',
            'phone' => $user->phone,
            'first_name' => 'Gaspari',
            'last_name' => 'Shiliba',
            'status' => 'active',
            'gender' => 'male',
        ]);

        return $user->fresh();
    }
}
