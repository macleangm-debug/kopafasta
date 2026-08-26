<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            if (($user->pin_hash ?? null) === '__needs_pin_setup__') {
                $user->forceFill(['pin_hash' => null, 'pin_set_at' => null])->save();

                return;
            }

            if ($user->role === 'borrower') {
                if (! app(\App\Services\PinService::class)->hasPin($user)) {
                    app(\App\Services\PinService::class)->setPin($user, '1234');
                }
                if (! app(\App\Services\PinRecoveryChallengeService::class)->hasEnrolledAnswers($user)) {
                    app(\App\Services\PinRecoveryChallengeService::class)->enroll($user, [
                        'mother_first_name' => 'Asha',
                        'primary_school' => 'Uhuru Primary',
                        'nida_middle4' => '4582',
                    ]);
                }
            }

            if (in_array($user->role, ['vendor', 'partner'], true) && ! app(\App\Services\PinService::class)->hasPin($user)) {
                app(\App\Services\PinService::class)->setPin($user, '1234');
            }
        });
    }

    public function needsPinSetup(): static
    {
        return $this->state(fn () => ['pin_hash' => '__needs_pin_setup__']);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
