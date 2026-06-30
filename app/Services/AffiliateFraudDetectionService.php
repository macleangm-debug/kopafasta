<?php

namespace App\Services;

use App\Models\AffiliateEvent;
use App\Models\Customer;
use App\Models\Vendor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AffiliateFraudDetectionService
{
    public const FLAG_LOW = 'low';

    public const FLAG_MEDIUM = 'medium';

    public const FLAG_HIGH = 'high';

    public const FLAG_BLOCKED = 'blocked';

    /** @return list<string> */
    public function flags(): array
    {
        return [self::FLAG_LOW, self::FLAG_MEDIUM, self::FLAG_HIGH, self::FLAG_BLOCKED];
    }

    public function label(string $flag): string
    {
        return match ($flag) {
            self::FLAG_MEDIUM  => 'Medium',
            self::FLAG_HIGH    => 'High',
            self::FLAG_BLOCKED => 'Blocked',
            default            => 'Low',
        };
    }

    /** @return array{signals: list<array<string, mixed>>, risk_flag: string, score: int} */
    public function scan(Vendor $affiliate): array
    {
        abort_unless($affiliate->isAffiliate(), 422);

        $signals = array_merge(
            $this->detectSelfReferral($affiliate),
            $this->detectSharedPhone($affiliate),
            $this->detectSharedNida($affiliate),
            $this->detectSharedDeviceFingerprint($affiliate),
            $this->detectMultiAccountDevice($affiliate),
        );

        $score = $this->scoreFromSignals($signals);
        $riskFlag = $this->resolveRiskFlag($score, $signals);

        return [
            'signals'   => $signals,
            'risk_flag' => $riskFlag,
            'score'     => $score,
        ];
    }

    public function scanAndPersist(Vendor $affiliate, bool $applyBlockedAction = true): array
    {
        $result = $this->scan($affiliate);

        $affiliate->update([
            'affiliate_risk_flag'     => $result['risk_flag'],
            'affiliate_fraud_snapshot'=> [
                'score'        => $result['score'],
                'signals'      => $result['signals'],
                'scanned_at'   => now()->toIso8601String(),
            ],
        ]);

        if ($applyBlockedAction && $result['risk_flag'] === self::FLAG_BLOCKED) {
            $lifecycle = app(AffiliateLifecycleService::class);
            if (! in_array($lifecycle->statusFor($affiliate), [
                AffiliateLifecycleService::SUSPENDED,
                AffiliateLifecycleService::TERMINATED,
            ], true)) {
                $lifecycle->transition(
                    $affiliate,
                    AffiliateLifecycleService::SUSPENDED,
                    'Automated suspension from affiliate fraud scan (blocked risk flag).',
                );
            }
        }

        return $result;
    }

    /** @return list<array<string, mixed>> */
    protected function detectSelfReferral(Vendor $affiliate): array
    {
        $signals = [];
        $affiliatePhone = $this->normalizePhone($affiliate->phone);
        $affiliateEmail = Str::lower(trim((string) $affiliate->email));

        if ($affiliatePhone === '' && $affiliateEmail === '') {
            return $signals;
        }

        $matches = Customer::query()
            ->where('affiliate_partner_id', $affiliate->id)
            ->where(function ($q) use ($affiliate, $affiliatePhone, $affiliateEmail): void {
                if ($affiliatePhone !== '') {
                    $q->orWhere('phone', $affiliate->phone)
                        ->orWhere('phone', 'like', '%'.substr($affiliatePhone, -9));
                }
                if ($affiliateEmail !== '') {
                    $q->orWhereRaw('LOWER(email) = ?', [$affiliateEmail]);
                }
            })
            ->limit(5)
            ->get(['id', 'customer_number', 'phone', 'email']);

        foreach ($matches as $customer) {
            $signals[] = [
                'type'     => 'self_referral',
                'severity' => 'high',
                'message'  => 'Referred customer contact matches affiliate profile.',
                'customer_id' => $customer->id,
                'customer_number' => $customer->customer_number,
            ];
        }

        return $signals;
    }

    /** @return list<array<string, mixed>> */
    protected function detectSharedPhone(Vendor $affiliate): array
    {
        $threshold = $this->settings()->sharedPhoneCustomerThreshold();
        $rows = DB::table('customers')
            ->select('phone', DB::raw('count(*) as total'))
            ->where('affiliate_partner_id', $affiliate->id)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->groupBy('phone')
            ->havingRaw('count(*) > ?', [$threshold])
            ->get();

        return $rows->map(fn ($row) => [
            'type'     => 'shared_phone',
            'severity' => 'medium',
            'message'  => "Phone {$row->phone} used on {$row->total} referred customers.",
            'phone'    => $row->phone,
            'count'    => (int) $row->total,
        ])->all();
    }

    /** @return list<array<string, mixed>> */
    protected function detectSharedNida(Vendor $affiliate): array
    {
        $rows = DB::table('customers')
            ->select('national_id', DB::raw('count(*) as total'))
            ->where('affiliate_partner_id', $affiliate->id)
            ->whereNotNull('national_id')
            ->where('national_id', '!=', '')
            ->groupBy('national_id')
            ->havingRaw('count(*) > 1')
            ->get();

        return $rows->map(fn ($row) => [
            'type'     => 'shared_nida',
            'severity' => 'high',
            'message'  => 'Duplicate national ID across referred customers.',
            'national_id' => $row->national_id,
            'count'    => (int) $row->total,
        ])->all();
    }

    /** @return list<array<string, mixed>> */
    protected function detectSharedDeviceFingerprint(Vendor $affiliate): array
    {
        $threshold = $this->settings()->sharedDeviceRegistrationThreshold();

        $rows = DB::table('affiliate_events')
            ->select('device_fingerprint', DB::raw('count(*) as total'))
            ->where('partner_id', $affiliate->id)
            ->where('event_type', 'registration')
            ->whereNotNull('device_fingerprint')
            ->groupBy('device_fingerprint')
            ->havingRaw('count(*) > ?', [$threshold])
            ->get();

        return $rows->map(fn ($row) => [
            'type'        => 'shared_device',
            'severity'    => 'medium',
            'message'     => 'Multiple registrations from the same device fingerprint.',
            'fingerprint' => Str::limit((string) $row->device_fingerprint, 16, ''),
            'count'       => (int) $row->total,
        ])->all();
    }

    /** @return list<array<string, mixed>> */
    protected function detectMultiAccountDevice(Vendor $affiliate): array
    {
        $threshold = $this->settings()->multiAccountDeviceThreshold();

        $rows = DB::table('affiliate_events as ae')
            ->join('customers as c', 'c.id', '=', 'ae.customer_id')
            ->select('ae.device_fingerprint', DB::raw('count(distinct ae.customer_id) as customers'))
            ->where('ae.partner_id', $affiliate->id)
            ->where('ae.event_type', 'registration')
            ->whereNotNull('ae.device_fingerprint')
            ->groupBy('ae.device_fingerprint')
            ->havingRaw('count(distinct ae.customer_id) > ?', [$threshold])
            ->get();

        return $rows->map(fn ($row) => [
            'type'        => 'multi_account_device',
            'severity'    => 'high',
            'message'     => 'One device fingerprint linked to multiple customer accounts.',
            'fingerprint' => Str::limit((string) $row->device_fingerprint, 16, ''),
            'customers'   => (int) $row->customers,
        ])->all();
    }

    /** @param  list<array<string, mixed>>  $signals */
    protected function scoreFromSignals(array $signals): int
    {
        $score = 0;

        foreach ($signals as $signal) {
            $score += match ($signal['severity'] ?? 'low') {
                'high'   => 40,
                'medium' => 20,
                default  => 10,
            };
        }

        return min(100, $score);
    }

    /**
     * @param  list<array<string, mixed>>  $signals
     */
    protected function resolveRiskFlag(int $score, array $signals): string
    {
        $settings = $this->settings();

        if ($score >= $settings->blockedFraudScore()
            || collect($signals)->contains(fn (array $s) => ($s['type'] ?? '') === 'self_referral')) {
            return self::FLAG_BLOCKED;
        }

        if ($score >= $settings->highFraudScore()) {
            return self::FLAG_HIGH;
        }

        if ($score >= $settings->mediumFraudScore()) {
            return self::FLAG_MEDIUM;
        }

        return self::FLAG_LOW;
    }

    public function referralsBlocked(Vendor $affiliate): bool
    {
        return in_array((string) ($affiliate->affiliate_risk_flag ?? self::FLAG_LOW), [
            self::FLAG_BLOCKED,
        ], true);
    }

    /** @return Collection<int, Vendor> */
    public function flaggedAffiliates(?string $flag = null, int $limit = 50): Collection
    {
        return Vendor::query()
            ->where('category', 'affiliate')
            ->when($flag, fn ($q) => $q->where('affiliate_risk_flag', $flag))
            ->where('affiliate_risk_flag', '!=', self::FLAG_LOW)
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();
    }

    protected function normalizePhone(?string $phone): string
    {
        return preg_replace('/\D+/', '', (string) $phone) ?? '';
    }

    protected function settings(): AffiliateSettingsService
    {
        return app(AffiliateSettingsService::class);
    }
}
