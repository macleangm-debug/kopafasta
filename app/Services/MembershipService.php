<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\MembershipHistory;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * MembershipService — handles issuing, renewing, expiring and archiving memberships.
 *
 * All date math is done in TZ timezone (date columns), via CarbonImmutable::today().
 */
class MembershipService
{
    public const PREFIX = 'KPF-TZ-';

    /**
     * Configured membership settings with sane defaults.
     */
    public static function config(): array
    {
        $group = Setting::group('membership');
        return [
            'duration_days'     => (int) ($group['duration_days']     ?? 365),
            'registration_fee'  => (float) ($group['registration_fee'] ?? $group['renewal_fee'] ?? 10000),
            'renewal_fee'       => (float) ($group['renewal_fee']     ?? 10000),
            'grace_period_days' => (int) ($group['grace_period_days'] ?? 14),
            'max_expiry_years'  => (int) ($group['max_expiry_years']  ?? 1),
            'currency'          => (string) ($group['currency']       ?? 'TZS'),
        ];
    }

    /**
     * Issue a brand-new membership for a customer (on registration approval / first payment).
     */
    public function issue(
        Customer $customer,
        ?CarbonImmutable $startDate = null,
        ?string $paymentReference = null,
        ?int $actorUserId = null,
        ?float $feeAmount = null,
        ?string $channel = null,
        ?array $paymentBreakdown = null,
    ): Customer {
        return DB::transaction(function () use ($customer, $startDate, $paymentReference, $actorUserId, $feeAmount, $channel, $paymentBreakdown) {
            $cfg = self::config();
            $start = $startDate ?? CarbonImmutable::today();
            $expires = $start->addDays($cfg['duration_days']);

            if (empty($customer->member_no)) {
                $customer->member_no = $this->generateMemberNo();
            }
            $customer->membership_issued_at  = $start->toDateString();
            $customer->membership_expires_at = $expires->toDateString();
            $customer->membership_status     = 'active';
            $customer->reminders_sent        = [];
            $customer->save();

            MembershipHistory::create([
                'customer_id'               => $customer->id,
                'event'                     => 'issued',
                'issued_at'                 => $start->toDateString(),
                'expires_at'                => $expires->toDateString(),
                'renewal_count_after'       => $customer->renewal_count,
                'fee_amount'                => $paymentBreakdown['after_discount'] ?? $feeAmount ?? $cfg['registration_fee'],
                'referral_discount_amount'  => $paymentBreakdown['discount'] ?? null,
                'wallet_amount_used'        => $paymentBreakdown['wallet_applied'] ?? null,
                'cash_amount_paid'          => $paymentBreakdown['cash_due'] ?? null,
                'payment_reference'         => $paymentReference,
                'channel'                   => $channel ?? ($paymentReference ? 'system' : null),
                'actor_user_id'             => $actorUserId,
            ]);
$this->notify($customer, 'membership_issued');

            
            return $customer->refresh();
        });
    }

    /**
     * Renew membership. Extends from current expiry if still active/grace, otherwise from today.
     */
    public function renew(Customer $customer, ?string $paymentReference = null, ?string $channel = null, ?int $actorUserId = null): Customer
    {
        return DB::transaction(function () use ($customer, $paymentReference, $channel, $actorUserId) {
            $cfg = self::config();
            $today = CarbonImmutable::today();
            $previousExpiry = $customer->membership_expires_at
                ? CarbonImmutable::parse($customer->membership_expires_at)
                : null;

            // If still active or in grace -> extend from existing expiry; else from today.
            $base = ($previousExpiry && $today->lte($previousExpiry->addDays($cfg['grace_period_days'])))
                ? $previousExpiry
                : $today;

            $newExpiry = $base->addDays($cfg['duration_days']);

            if (empty($customer->member_no)) {
                $customer->member_no = $this->generateMemberNo();
            }
            if (empty($customer->membership_issued_at)) {
                $customer->membership_issued_at = $today->toDateString();
            }
            $customer->membership_expires_at    = $newExpiry->toDateString();
            $customer->last_renewal_at          = $today->toDateString();
            $customer->last_renewal_payment_ref = $paymentReference;
            $customer->renewal_count            = (int) ($customer->renewal_count ?? 0) + 1;
            $customer->membership_status        = 'active';
            $customer->reminders_sent           = [];
            $customer->save();

            MembershipHistory::create([
                'customer_id'         => $customer->id,
                'event'               => 'renewed',
                'issued_at'           => $customer->membership_issued_at,
                'expires_at'          => $newExpiry->toDateString(),
                'previous_expires_at' => $previousExpiry?->toDateString(),
                'renewal_count_after' => $customer->renewal_count,
                'fee_amount'          => $cfg['renewal_fee'],
                'payment_reference'   => $paymentReference,
                'channel'             => $channel,
                'actor_user_id'       => $actorUserId,
            ]);

            $this->notify($customer, 'membership_renewed');

            return $customer->refresh();
        });
    }

    /**
     * Mark a membership as expired (called by scheduled job once past grace).
     */
    public function markExpired(Customer $customer): void
    {
        if ($customer->membership_status === 'expired') {
            return;
        }
        $customer->membership_status = 'expired';
        $customer->save();

        MembershipHistory::create([
            'customer_id' => $customer->id,
            'event'       => 'expired',
            'expires_at'  => $customer->membership_expires_at,
        ]);
    }

    /**
     * Move into grace period (between expiry and expiry+grace_days).
     */
    public function markGrace(Customer $customer): void
    {
        if ($customer->membership_status === 'grace') {
            return;
        }
        $customer->membership_status = 'grace';
        $customer->save();

        MembershipHistory::create([
            'customer_id' => $customer->id,
            'event'       => 'grace_started',
            'expires_at'  => $customer->membership_expires_at,
        ]);
    }

    public function archive(Customer $customer, ?int $actorUserId = null): void
    {
        $customer->membership_status = 'archived';
        $customer->save();

        MembershipHistory::create([
            'customer_id'   => $customer->id,
            'event'         => 'archived',
            'actor_user_id' => $actorUserId,
        ]);
    }

    /**
     * Record that a reminder for $milestone days-out was sent on $today.
     * Returns true if it was newly recorded, false if already sent.
     */
    public function recordReminder(Customer $customer, string $milestone): bool
    {
        $sent = $customer->reminders_sent ?? [];
        $key = (string) $milestone;
        if (isset($sent[$key])) {
            return false;
        }
        $sent[$key] = CarbonImmutable::today()->toDateString();
        $customer->reminders_sent = $sent;
        $customer->save();
        return true;
    }

    /**
     * Generate a unique member number like KPF-TZ-X72A.
     */
    public function generateMemberNo(): string
    {
        do {
            $code = self::PREFIX . strtoupper(Str::random(4));
        } while (Customer::where('member_no', $code)->exists());

        return $code;
    }

    public function generatePaymentReference(Customer $customer): string
    {
        return app(CustomerPaymentService::class)->generateReference();
    }

    /**
     * Record a bank transfer submitted for manual verification.
     */
    public function recordPendingPayment(
        Customer $customer,
        string $paymentReference,
        string $channel = 'bank',
        ?int $actorUserId = null,
        ?array $paymentBreakdown = null,
    ): void {
        $cfg = self::config();
        $isFirstTime = ! $customer->hasMembership();
        $baseFee = $isFirstTime ? $cfg['registration_fee'] : $cfg['renewal_fee'];

        $history = MembershipHistory::create([
            'customer_id'              => $customer->id,
            'event'                    => 'payment_pending',
            'fee_amount'               => $paymentBreakdown['after_discount'] ?? $baseFee,
            'referral_discount_amount' => $paymentBreakdown['discount'] ?? null,
            'wallet_amount_used'       => $paymentBreakdown['wallet_applied'] ?? null,
            'cash_amount_paid'         => $paymentBreakdown['cash_due'] ?? null,
            'payment_reference'        => $paymentReference,
            'channel'                  => $channel,
            'actor_user_id'            => $actorUserId,
            'notes'                    => $isFirstTime ? 'Registration fee awaiting verification' : 'Renewal fee awaiting verification',
        ]);

        app(CustomerPaymentService::class)->create([
            'customer'       => $customer,
            'payment_type'   => 'registration_fee',
            'payment_method' => 'bank_transfer',
            'amount'         => $paymentBreakdown['after_discount'] ?? $baseFee,
            'reference'      => $paymentReference,
            'source'         => $history,
            'auto_verify'    => false,
        ]);
    }

    /**
     * Approve a pending bank transfer and activate or renew membership.
     */
    public function approvePendingPayment(MembershipHistory $pending, ?int $actorUserId = null, ?string $adminNotes = null): Customer
    {
        if (! $pending->isPending()) {
            throw new \InvalidArgumentException('This payment is not pending approval.');
        }

        return DB::transaction(function () use ($pending, $actorUserId, $adminNotes) {
            $customer = Customer::query()->lockForUpdate()->findOrFail($pending->customer_id);
            $ref = $pending->payment_reference;
            $channel = $pending->channel ?? 'bank';
            $fee = $pending->fee_amount !== null ? (float) $pending->fee_amount : null;
            $isRegistration = $pending->isRegistrationPayment() || ! $customer->hasMembership();
            $paymentBreakdown = [
                'discount'       => (float) ($pending->referral_discount_amount ?? 0),
                'wallet_applied' => (float) ($pending->wallet_amount_used ?? 0),
                'cash_due'       => (float) ($pending->cash_amount_paid ?? $fee ?? 0),
                'after_discount' => (float) ($fee ?? 0),
            ];

            if ($isRegistration && app(ReferralService::class)->referrer($customer)) {
                $base = (float) ($pending->fee_amount ?? 0) + (float) ($pending->referral_discount_amount ?? 0);
                app(ReferralService::class)->settleFee(
                    $customer,
                    $base,
                    (float) ($pending->wallet_amount_used ?? 0) > 0,
                    'registration_fee',
                    MembershipHistory::class,
                    (int) $pending->id,
                );
            } elseif ($isRegistration) {
                $base = (float) ($pending->fee_amount ?? 0) + (float) ($pending->referral_discount_amount ?? 0);
                app(AffiliateService::class)->accrueCommission(
                    $customer,
                    $base > 0 ? $base : (float) MembershipService::config()['registration_fee'],
                    'registration_fee',
                    MembershipHistory::class,
                    (int) $pending->id,
                );
            }

            $notes = $pending->notes;
            if ($adminNotes) {
                $notes = trim(($notes ?? '')."\nApproved: ".$adminNotes);
            }

            $pending->update([
                'event'         => 'payment_approved',
                'actor_user_id' => $actorUserId,
                'notes'         => $notes,
            ]);

            $customerPayment = CustomerPayment::query()
                ->where('source_type', MembershipHistory::class)
                ->where('source_id', $pending->id)
                ->pending()
                ->first();

            if ($customerPayment) {
                app(CustomerPaymentService::class)->verify($customerPayment, $actorUserId, $adminNotes);
            }

            if ($isRegistration) {
                return $this->issue($customer, null, $ref, $actorUserId, $fee, $channel, $paymentBreakdown);
            }

            return $this->renew($customer, $ref, $channel, $actorUserId);
        });
    }

    /**
     * Reject a pending bank transfer.
     */
    public function rejectPendingPayment(MembershipHistory $pending, ?int $actorUserId = null, ?string $adminNotes = null): void
    {
        if (! $pending->isPending()) {
            throw new \InvalidArgumentException('This payment is not pending approval.');
        }

        $notes = $pending->notes;
        if ($adminNotes) {
            $notes = trim(($notes ?? '')."\nRejected: ".$adminNotes);
        }

        $pending->update([
            'event'         => 'payment_rejected',
            'actor_user_id' => $actorUserId,
            'notes'         => $notes,
        ]);

        $customerPayment = CustomerPayment::query()
            ->where('source_type', MembershipHistory::class)
            ->where('source_id', $pending->id)
            ->pending()
            ->first();

        if ($customerPayment) {
            app(CustomerPaymentService::class)->reject($customerPayment, $actorUserId, $adminNotes);
        }
    }

    /**
     * Send a templated notification, swallowing any failure so the
     * transactional issue/renew flow never fails on messaging issues.
     */
    private function notify(Customer $customer, string $templateCode): void
    {
        try {
            $name = trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''));
            app(\App\Services\NotificationService::class)->notifyCustomer($customer, $templateCode, [
                'name'       => $name,
                'member_no'  => $customer->member_no ?? '',
                'issued_at'  => optional($customer->membership_issued_at)->format('d M Y') ?? '',
                'expires_at' => optional($customer->membership_expires_at)->format('d M Y') ?? '',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Membership notification failed', [
                'customer_id' => $customer->id,
                'template' => $templateCode,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
