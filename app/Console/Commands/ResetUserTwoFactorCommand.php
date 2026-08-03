<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ResetUserTwoFactorCommand extends Command
{
    protected $signature = 'auth:reset-2fa {email : User email}';

    protected $description = 'Clear two-factor secret, recovery codes, and confirmation so the user must set up 2FA again';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $this->error("No user found for {$email}");

            return self::FAILURE;
        }

        $user->forceFill([
            'two_factor_secret'         => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at'   => null,
        ])->save();

        $this->info("Two-factor authentication reset for {$user->email}. Next sign-in will require setup.");

        return self::SUCCESS;
    }
}
