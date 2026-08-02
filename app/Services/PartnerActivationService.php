<?php

namespace App\Services;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PartnerActivationService
{
    /** Partner categories that require portal activation. */
    private const ACTIVATABLE_CATEGORIES = [
        'affiliate',
        'call_center',
        'debt_collector',
        'auctioneer',
        'legal_partner',
        'gps_installer',
        'capital',
        'valuer',
        'supplier',
        'insurance',
    ];

    public function requiresActivation(Vendor $vendor): bool
    {
        if ($vendor->activated_at) {
            return false;
        }

        return in_array($vendor->category, self::ACTIVATABLE_CATEGORIES, true)
            || collect($vendor->partnerRoles())->intersect(self::ACTIVATABLE_CATEGORIES)->isNotEmpty();
    }

    /** Create/refresh activation token without sending SMS or email. */
    public function prepareActivation(Vendor $vendor): string
    {
        $token = Str::random(64);

        $vendor->update([
            'activation_token'   => hash('sha256', $token),
            'activation_sent_at' => now(),
            'status'             => $vendor->status === 'active' && ! $vendor->user_id ? 'inactive' : $vendor->status,
        ]);

        return $token;
    }

    public function activationUrl(Vendor $vendor, string $plainToken): string
    {
        return URL::temporarySignedRoute(
            'site.partner.activate',
            now()->addDays(14),
            ['vendor' => $vendor->id, 'token' => $plainToken],
        );
    }

    /**
     * Prepare activation. SMS/email skipped by default (partner code is shown on track status).
     *
     * @param  bool  $notify  When true, also email/SMS the signed link (off until integrations are live).
     */
    public function sendActivationInvite(Vendor $vendor, ?User $actor = null, bool $notify = false): Vendor
    {
        $token = $this->prepareActivation($vendor);
        $url = $this->activationUrl($vendor->fresh(), $token);

        if ($notify) {
            $message = 'Activate your '.brand_name().' partner account: '.$url;

            if (filled($vendor->email)) {
                app(NotificationService::class)->sendEmail(
                    $vendor->email,
                    'Activate your partner account',
                    $message,
                );
            }

            if (filled($vendor->phone)) {
                app(NotificationService::class)->sendSms($vendor->phone, $message);
            }
        }

        return $vendor->fresh();
    }

    public function verifyToken(Vendor $vendor, string $token): bool
    {
        if (! $vendor->activation_token) {
            return false;
        }

        return hash_equals($vendor->activation_token, hash('sha256', $token));
    }

    public function activate(Vendor $vendor, string $token, array $data): User
    {
        if (! $this->verifyToken($vendor, $token)) {
            throw ValidationException::withMessages([
                'token' => 'This activation link is invalid or has expired.',
            ]);
        }

        if ($vendor->user_id && $vendor->activated_at) {
            throw ValidationException::withMessages([
                'vendor' => 'This partner account is already activated.',
            ]);
        }

        $validated = validator($data, [
            'pin' => ['nullable', 'digits:4'],
        ])->validate();

        $password = Str::password(32);

        $email = $vendor->email;
        if (blank($email)) {
            $digits = preg_replace('/\D/', '', (string) $vendor->phone) ?: Str::random(8);
            $email = 'partner-'.$vendor->id.'-'.$digits.'@partners.kopafasta.local';
        }

        if (User::query()->where('email', $email)->exists() && ! $vendor->user_id) {
            $existingUser = User::query()->where('email', $email)->first();
            if ((int) $existingUser->id !== (int) $vendor->user_id) {
                throw ValidationException::withMessages(['email' => 'Email already registered.']);
            }
        }

        $existingUser = $vendor->user_id
            ? User::query()->find($vendor->user_id)
            : User::query()->where('email', $email)->first();

        $role = match (true) {
            $vendor->isAffiliate()           => 'vendor',
            $vendor->isRecoveryPartner()      => 'vendor',
            default                           => 'vendor',
        };

        $user = $existingUser ?? User::create([
            'name'      => $vendor->name,
            'email'     => $email,
            'phone'     => $vendor->phone,
            'password'  => Hash::make($password),
            'role'      => $role,
            'is_active' => true,
        ]);

        if ($existingUser) {
            $user->update(['is_active' => true]);
        }

        if (filled($validated['pin'] ?? null)) {
            app(PinService::class)->setPin($user, $validated['pin']);
        }

        $vendor->update([
            'user_id'           => $user->id,
            'activated_at'      => now(),
            'activation_token'  => null,
            'status'            => 'active',
        ]);

        return $user;
    }

    /** Activate using partner code + matching phone (PIN is created after login). */
    public function activateWithPartnerCode(Vendor $vendor, string $phone, ?string $pin = null): User
    {
        $normalizedPhone = preg_replace('/\D+/', '', $phone) ?: $phone;
        $vendorPhone = preg_replace('/\D+/', '', (string) $vendor->phone) ?: (string) $vendor->phone;

        if ($vendorPhone !== $normalizedPhone && ! str_ends_with($vendorPhone, $normalizedPhone) && ! str_ends_with($normalizedPhone, $vendorPhone)) {
            throw ValidationException::withMessages([
                'phone' => 'Phone number does not match this partner code.',
            ]);
        }

        if ($vendor->user_id && $vendor->activated_at) {
            throw ValidationException::withMessages([
                'partner_code' => 'This partner account is already activated. Sign in with your phone and PIN.',
            ]);
        }

        $token = $this->prepareActivation($vendor);

        return $this->activate($vendor->fresh(), $token, filled($pin) ? ['pin' => $pin] : []);
    }
}
