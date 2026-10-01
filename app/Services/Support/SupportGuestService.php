<?php

namespace App\Services\Support;

use App\Models\Customer;
use App\Models\SupportConversation;
use App\Models\SupportGuest;
use App\Models\User;
use App\Models\Vendor;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;

class SupportGuestService
{
    public const SOURCE_GUEST_CHAT = 'guest_chat';

    public const SOURCE_PHONE_CALL = 'phone_call';

    public const SOURCE_FEEDBACK = 'feedback';

    public const SOURCE_WALK_IN = 'walk_in';

    public const SOURCE_OTHER = 'other';

    /**
     * Upsert an active Guest by normalized phone. Never duplicates on phone.
     * If the phone already belongs to a Member/Partner, returns null (caller records on that identity).
     */
    public function touchGuest(
        string $firstName,
        string $lastName,
        string $phone,
        string $source = self::SOURCE_GUEST_CHAT,
    ): ?SupportGuest {
        $normalized = $this->normalizePhone($phone);
        if ($normalized === null) {
            return null;
        }

        if ($this->findRegisteredByPhone($normalized) !== null) {
            return null;
        }

        $guest = SupportGuest::query()->where('phone', $normalized)->first();
        $now = now();

        if ($guest) {
            if ($guest->isActiveGuest()) {
                $incomingFirst = trim($firstName);
                $incomingLast = trim($lastName);
                $storedFirst = trim((string) $guest->first_name);
                $storedLast = trim((string) $guest->last_name);

                $guest->fill([
                    'first_name' => $this->safeGuestName($storedFirst, $incomingFirst),
                    'last_name' => $this->safeGuestName($storedLast, $incomingLast),
                    'last_contact_at' => $now,
                    'contact_count' => (int) $guest->contact_count + 1,
                ]);
                if (blank($guest->source)) {
                    $guest->source = $source;
                }
                $guest->save();
            }

            return $guest;
        }

        return SupportGuest::create([
            'phone' => $normalized,
            'first_name' => trim($firstName),
            'last_name' => trim($lastName),
            'source' => $source,
            'first_contact_at' => $now,
            'last_contact_at' => $now,
            'contact_count' => 1,
            'registration_status' => 'guest',
        ]);
    }

    /**
     * When a phone registers as Member or Partner, convert matching Guest and keep history.
     * Does not merge Borrower ↔ Partner identities.
     */
    public function convertOnRegistration(string $phone, Customer|User|Vendor $identity, string $as): ?SupportGuest
    {
        $normalized = $this->normalizePhone($phone);
        if ($normalized === null) {
            return null;
        }

        $guest = SupportGuest::query()
            ->where('phone', $normalized)
            ->where('registration_status', 'guest')
            ->first();

        if (! $guest) {
            return null;
        }

        return DB::transaction(function () use ($guest, $identity, $as, $normalized) {
            $customerId = null;
            $userId = null;

            if ($identity instanceof Customer) {
                $customerId = $identity->id;
                $userId = $identity->user_id;
            } elseif ($identity instanceof Vendor) {
                $userId = $identity->user_id;
                $customerId = null;
            } elseif ($identity instanceof User) {
                $userId = $identity->id;
                $customerId = $identity->customer?->id;
            }

            $guest->update([
                'registration_status' => 'converted',
                'converted_as' => $as,
                'converted_at' => now(),
                'customer_id' => $customerId,
                'user_id' => $userId,
            ]);

            // Preserve conversation history on the registered identity.
            $query = SupportConversation::query()
                ->whereNull('customer_id')
                ->where('guest_phone', $normalized);

            if ($customerId) {
                $query->update([
                    'customer_id' => $customerId,
                    'user_id' => $userId,
                ]);
            } elseif ($userId) {
                $query->update(['user_id' => $userId]);
            }

            return $guest->fresh();
        });
    }

    /**
     * @return array{kind: string, customer?: Customer, vendor?: Vendor, guest?: SupportGuest}|null
     */
    public function resolvePhoneIdentity(string $phone): ?array
    {
        $normalized = $this->normalizePhone($phone);
        if ($normalized === null) {
            return null;
        }

        $registered = $this->findRegisteredByPhone($normalized);
        if ($registered) {
            return $registered;
        }

        $guest = SupportGuest::query()->where('phone', $normalized)->first();
        if ($guest) {
            return ['kind' => 'guest', 'guest' => $guest];
        }

        return null;
    }

    /**
     * @return array{kind: string, customer?: Customer, vendor?: Vendor}|null
     */
    public function findRegisteredByPhone(string $normalizedPhone): ?array
    {
        $customer = Customer::query()
            ->where(function ($q) use ($normalizedPhone) {
                $q->where('phone', $normalizedPhone)
                    ->orWhere('phone', 'like', '%'.substr($normalizedPhone, -9));
            })
            ->orderByDesc('id')
            ->get()
            ->first(fn (Customer $c) => $this->phonesMatch((string) $c->phone, $normalizedPhone));

        if ($customer) {
            return ['kind' => 'member', 'customer' => $customer];
        }

        if (class_exists(Vendor::class)) {
            $vendor = Vendor::query()
                ->where(function ($q) use ($normalizedPhone) {
                    $q->where('phone', $normalizedPhone);
                    if (\Illuminate\Support\Facades\Schema::hasColumn('vendors', 'phone')) {
                        $q->orWhere('phone', 'like', '%'.substr($normalizedPhone, -9));
                    }
                })
                ->orderByDesc('id')
                ->get()
                ->first(fn (Vendor $v) => $this->phonesMatch((string) ($v->phone ?? ''), $normalizedPhone));

            if ($vendor) {
                return ['kind' => 'partner', 'vendor' => $vendor];
            }
        }

        return null;
    }

    public function normalizePhone(string $phone): ?string
    {
        $normalized = PhoneNumber::normalizeForCountry($phone, (string) session('country', 'TZ'))
            ?: PhoneNumber::digits($phone);

        if (! $normalized || strlen(PhoneNumber::digits($normalized)) < 9) {
            return null;
        }

        return PhoneNumber::digits($normalized);
    }

    private function phonesMatch(string $a, string $b): bool
    {
        $da = PhoneNumber::digits($a);
        $db = PhoneNumber::digits($b);
        if ($da === '' || $db === '') {
            return false;
        }
        if ($da === $db) {
            return true;
        }

        return strlen($da) >= 9 && strlen($db) >= 9 && substr($da, -9) === substr($db, -9);
    }

    /**
     * Preserve phone-owned Guest CRM identity. Fill blanks; keep stored when names differ materially.
     */
    private function safeGuestName(string $stored, string $incoming): string
    {
        if ($stored === '') {
            return $incoming;
        }
        if ($incoming === '') {
            return $stored;
        }
        if ($this->namesMateriallyDiffer($stored, $incoming)) {
            return $stored;
        }

        return $incoming;
    }

    private function namesMateriallyDiffer(string $a, string $b): bool
    {
        $na = mb_strtolower(preg_replace('/\s+/', ' ', trim($a)) ?? '');
        $nb = mb_strtolower(preg_replace('/\s+/', ' ', trim($b)) ?? '');

        return $na !== '' && $nb !== '' && $na !== $nb;
    }
}
