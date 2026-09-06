<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Staging-only: align the single Admin identity with a production credential hash export.
 * Never accepts or prints plaintext passwords. Never runs in production.
 */
class SyncStagingAdminCredentialsCommand extends Command
{
    protected $signature = 'staging:sync-admin-credentials
        {--payload= : Absolute path to JSON payload with email, password_hash, optional pin_hash/name/phone}
        {--verify-password-file= : Optional absolute path to plaintext password used only for Auth::attempt confirmation (never logged)}
        {--force : Skip confirmation}';

    protected $description = 'Sync staging Admin email + credential hashes from a sealed production export. Staging only.';

    public function handle(): int
    {
        if (app()->isProduction() || ! app()->environment('staging')) {
            $this->error('Refusing: this command only runs when APP_ENV=staging.');

            return self::FAILURE;
        }

        $payloadPath = (string) $this->option('payload');
        if ($payloadPath === '' || ! is_readable($payloadPath)) {
            $this->error('Provide a readable --payload JSON file.');

            return self::FAILURE;
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) file_get_contents($payloadPath), true);
        if (! is_array($payload)) {
            $this->error('Payload JSON is invalid.');

            return self::FAILURE;
        }

        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $passwordHash = trim((string) ($payload['password_hash'] ?? ''));
        $pinHash = trim((string) ($payload['pin_hash'] ?? ''));
        $name = trim((string) ($payload['name'] ?? 'Kopafasta Owner'));
        $phone = trim((string) ($payload['phone'] ?? '255700000000'));

        if ($email === '' || ! str_contains($email, '@')) {
            $this->error('Payload email is required.');

            return self::FAILURE;
        }
        if ($passwordHash === '' || ! str_starts_with($passwordHash, '$2y$')) {
            $this->error('Payload password_hash must be a bcrypt hash.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Sync staging Admin identity to {$email}?")) {
            return self::SUCCESS;
        }

        $admins = User::query()->whereIn('role', ['admin', 'super_admin'])->orderBy('id')->get();
        $canonical = $admins->firstWhere('email', $email) ?: $admins->first();

        if (! $canonical) {
            $canonical = new User;
            $canonical->role = 'admin';
        }

        $before = [
            'id' => $canonical->id,
            'email' => $canonical->email,
            'role' => $canonical->role,
            'is_active' => (bool) $canonical->is_active,
            'other_admin_ids' => $admins->where('id', '!=', $canonical->id)->pluck('id')->values()->all(),
        ];

        DB::transaction(function () use ($canonical, $email, $passwordHash, $pinHash, $name, $phone, $admins) {
            $canonical->fill([
                'name' => $name !== '' ? $name : $canonical->name,
                'email' => $email,
                'phone' => $phone !== '' ? $phone : $canonical->phone,
                'role' => 'admin',
                'is_active' => true,
                'email_verified_at' => $canonical->email_verified_at ?? now(),
                // Staging cannot decrypt production APP_KEY-encrypted 2FA secrets.
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'locked_until' => null,
            ]);
            $canonical->save();

            // Write raw bcrypt hash — do not re-hash through the Eloquent cast.
            $update = [
                'password' => $passwordHash,
                'password_changed_at' => now(),
            ];
            if ($pinHash !== '') {
                $update['pin_hash'] = $pinHash;
                $update['pin_set_at'] = now();
            }
            DB::table('users')->where('id', $canonical->id)->update($update);

            foreach ($admins as $other) {
                if ((int) $other->id === (int) $canonical->id) {
                    continue;
                }
                if (in_array($other->role, ['admin', 'super_admin'], true)) {
                    $disabledEmail = 'disabled-admin-'.$other->id.'@staging.kopafasta.invalid';
                    DB::table('users')->where('id', $other->id)->update([
                        'email' => $disabledEmail,
                        'role' => 'officer',
                        'is_active' => false,
                        'password' => Hash::make(bin2hex(random_bytes(32))),
                        'pin_hash' => null,
                        'two_factor_secret' => null,
                        'two_factor_recovery_codes' => null,
                        'two_factor_confirmed_at' => null,
                        'remember_token' => null,
                    ]);
                }
            }
        });

        $canonical->refresh();

        AuditLog::create([
            'user_id' => $canonical->id,
            'event' => 'staging.admin_credentials_synced',
            'auditable_type' => $canonical->getMorphClass(),
            'auditable_id' => $canonical->id,
            'old_values' => $before,
            'new_values' => [
                'email' => $canonical->email,
                'role' => $canonical->role,
                'is_active' => (bool) $canonical->is_active,
                'password' => '[redacted-hash-synced]',
                'pin_hash' => $pinHash !== '' ? '[redacted-hash-synced]' : '[unchanged]',
                'two_factor' => 'cleared_on_staging',
                'source' => 'production_credential_hash_export',
            ],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'staging:sync-admin-credentials',
        ]);

        $authOk = null;
        $authDenied = null;
        $verifyFile = (string) $this->option('verify-password-file');
        if ($verifyFile !== '' && is_readable($verifyFile)) {
            $plain = trim((string) file_get_contents($verifyFile));
            if ($plain !== '') {
                $authOk = Auth::guard('admin')->attempt([
                    'email' => $email,
                    'password' => $plain,
                ], false);
                Auth::guard('admin')->logout();
                $authDenied = Auth::guard('admin')->attempt([
                    'email' => $email,
                    'password' => 'definitely-not-the-password-'.bin2hex(random_bytes(8)),
                ], false);
                Auth::guard('admin')->logout();
                // Confirm hash matches without logging plaintext.
                if (! Hash::check($plain, $passwordHash)) {
                    $this->error('AUTH_HASH_MISMATCH');

                    return self::FAILURE;
                }
            }
            unset($plain);
        }

        $this->info('SYNC_OK');
        $this->line('login_identifier='.$email);
        $this->line('admin_id='.$canonical->id);
        $this->line('hash_synced=yes');
        if ($authOk !== null) {
            $this->line('auth_attempt_production_password='.($authOk ? 'PASS' : 'FAIL'));
            $this->line('auth_attempt_wrong_password='.($authDenied ? 'FAIL_UNEXPECTED_ALLOW' : 'PASS_DENIED'));
        }

        return ($authOk === false) ? self::FAILURE : self::SUCCESS;
    }
}
