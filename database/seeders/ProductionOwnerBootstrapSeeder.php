<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\PinService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates the single production owner/super-admin. Never copies staging users.
 * Idempotent. Refuses unless APP_ENV=production.
 */
class ProductionOwnerBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->isProduction() || app()->environment('staging')) {
            $this->command?->warn('ProductionOwnerBootstrapSeeder skipped: not production.');

            return;
        }

        $email = trim((string) env('PRODUCTION_OWNER_EMAIL', ''));
        if ($email === '') {
            $this->command?->warn('Set PRODUCTION_OWNER_EMAIL before bootstrapping the owner account.');

            return;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->command?->info('Production owner already exists; leaving the account unchanged.');

            return;
        }

        $password = (string) env('PRODUCTION_OWNER_PASSWORD', '');
        if ($password === '') {
            $password = Str::password(20);
            $this->command?->warn('Generated a one-time owner password. Store it in the password manager and rotate it.');
            $this->command?->warn('Owner email: '.$email);
        }

        $user = User::query()->create([
            'name' => (string) env('PRODUCTION_OWNER_NAME', 'Kopafasta Owner'),
            'email' => $email,
            'phone' => (string) env('PRODUCTION_OWNER_PHONE', '255700000000'),
            'role' => 'admin',
            'is_active' => true,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]);

        $pin = (string) env('PRODUCTION_OWNER_PIN', '');
        if (strlen($pin) === 4 && ctype_digit($pin)) {
            app(PinService::class)->setPin($user, $pin);
        }

        $this->command?->info('Production owner bootstrapped: '.$user->email);
    }
}
