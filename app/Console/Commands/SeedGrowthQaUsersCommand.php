<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\User;
use App\Models\Vendor;
use App\Services\LoyaltyPointsService;
use App\Services\LoyaltyRedemptionService;
use App\Services\PinService;
use App\Services\Plus\PlusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SeedGrowthQaUsersCommand extends Command
{
    protected $signature = 'growth:seed-qa-users {--pin=1234 : PIN for every QA persona}';

    protected $description = 'Create staging QA borrowers for Growth/Rewards interactive test (A/B/C/D + affiliate)';

    public function handle(PinService $pins, PlusService $plus, LoyaltyPointsService $points, LoyaltyRedemptionService $redemptions): int
    {
        $pin = (string) $this->option('pin');
        $rows = [];

        foreach ($this->personas() as $persona) {
            $customer = $this->upsertCustomer($persona, $pin, $pins);

            if (($persona['points'] ?? 0) > 0) {
                $points->earnCustom(
                    $customer->fresh(),
                    (int) $persona['points'],
                    'qa_seed',
                    'QA seed balance',
                    'growth_qa',
                    (int) $customer->id,
                );
            }

            if (! empty($persona['plus'])) {
                $plus->grantComplimentary($customer->fresh(), 'Growth QA Plus', null, 365);
            }

            if (! empty($persona['unlock'])) {
                try {
                    $redemptions->redeem($customer->fresh(), (string) $persona['unlock']);
                } catch (\InvalidArgumentException) {
                    // Already unlocked on a previous seed.
                }
            }

            $customer = $customer->fresh();
            $rows[] = [
                $persona['label'],
                $customer->phone,
                $pin,
                (int) $customer->loyalty_points,
                ! empty($persona['plus']) ? 'Plus' : '—',
                ! empty($persona['unlock']) ? (string) $persona['unlock'] : '—',
            ];
        }

        $affiliate = $this->upsertAffiliate($pin, $pins);
        $rows[] = [
            'Affiliate',
            $affiliate->phone,
            $pin,
            '—',
            'App fee + Plus ON',
            $affiliate->affiliate_code,
        ];

        $this->table(['Persona', 'Phone', 'PIN', 'Points', 'Plus / scope', 'Reward / code'], $rows);
        $this->comment('Staging visual QA only. These accounts are not created by the points engine.');

        return self::SUCCESS;
    }

    /** @return list<array<string, mixed>> */
    private function personas(): array
    {
        return [
            ['label' => 'User A — 0 points', 'number' => 'QA-GROWTH-A', 'phone' => '255700010001', 'points' => 0],
            ['label' => 'User B — 95 points', 'number' => 'QA-GROWTH-B', 'phone' => '255700010002', 'points' => 95],
            ['label' => 'User C — unlocked app-fee 10%', 'number' => 'QA-GROWTH-C', 'phone' => '255700010003', 'points' => 125, 'unlock' => 'application_fee_10'],
            ['label' => 'User D — Plus + plus-only available', 'number' => 'QA-GROWTH-D', 'phone' => '255700010004', 'points' => 400, 'plus' => true],
        ];
    }

    private function upsertCustomer(array $persona, string $pin, PinService $pins): Customer
    {
        $customer = Customer::query()->where('customer_number', $persona['number'])->first();

        if (! $customer) {
            $user = User::query()->create([
                'name' => $persona['label'],
                'email' => Str::lower($persona['number']).'@qa.kopafasta.test',
                'password' => Hash::make('password'),
                'role' => 'borrower',
                'phone' => $persona['phone'],
            ]);
            $customer = Customer::query()->create([
                'user_id' => $user->id,
                'customer_number' => $persona['number'],
                'type' => 'individual',
                'status' => 'active',
                'first_name' => $persona['label'],
                'last_name' => 'QA',
                'phone' => $persona['phone'],
                'country_code' => 'TZ',
            ]);
        }

        $pins->setPin($customer->user, $pin);

        return $customer->fresh();
    }

    private function upsertAffiliate(string $pin, PinService $pins): Vendor
    {
        $phone = '255700010099';
        $email = 'growth.affiliate@qa.kopafasta.test';

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Growth QA Affiliate',
                'phone' => $phone,
                'password' => Hash::make('password'),
                'role' => 'vendor',
                'is_active' => true,
            ]
        );
        $pins->setPin($user, $pin);

        return Vendor::query()->updateOrCreate(
            ['partner_number' => 'QA-GROWTH-AFF'],
            [
                'user_id' => $user->id,
                'name' => 'Growth QA Affiliate',
                'category' => 'affiliate',
                'status' => 'active',
                'phone' => $phone,
                'email' => $email,
                'affiliate_code' => 'GROWTHQA',
                'affiliate_kyc_status' => 'verified',
                'affiliate_lifecycle_status' => 'active',
                'application_discount_percent' => 10,
                'affiliate_commission_percent' => 10,
                'activated_at' => now(),
                'metadata' => ['plus_discount_percent' => 10],
            ]
        );
    }
}
