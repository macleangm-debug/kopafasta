<?php

namespace App\Console\Commands;

use App\Models\AffiliateEvent;
use App\Models\Vendor;
use App\Services\AffiliateSettingsService;
use App\Services\NotificationService;
use App\Services\PartnerPayoutRequestService;
use Illuminate\Console\Command;

class SendAffiliateDailyCommissionSummaries extends Command
{
    protected $signature = 'affiliate:send-daily-commission-summaries {--force : Ignore the configured delivery hour}';

    protected $description = 'Send one daily Affiliate commission summary instead of per-transaction notices';

    public function handle(
        AffiliateSettingsService $settings,
        NotificationService $notifier,
        PartnerPayoutRequestService $payouts,
    ): int {
        if (! $settings->dailyCommissionSummaryEnabled()) {
            $this->info('Daily commission summaries are disabled in Settings.');

            return self::SUCCESS;
        }

        $hour = (int) explode(':', $settings->dailyCommissionSummaryAt())[0];
        if (! $this->option('force') && (int) now()->format('G') !== $hour) {
            return self::SUCCESS;
        }

        $from = now()->copy()->startOfDay();
        $to = now()->copy()->endOfDay();
        $sent = 0;

        $grouped = AffiliateEvent::query()
            ->where('event_type', 'like', 'commission_%')
            ->whereBetween('created_at', [$from, $to])
            ->get()
            ->groupBy('partner_id');

        foreach ($grouped as $partnerId => $events) {
            $affiliate = Vendor::query()->find((int) $partnerId);
            if (! $affiliate || ! $affiliate->isAffiliate()) {
                continue;
            }

            $amount = (float) $events->sum('commission_amount');
            $count = $events->count();
            if ($amount <= 0 || $count < 1) {
                continue;
            }

            $available = $payouts->availableBalance($affiliate, 'affiliate_commission');
            $log = $notifier->notifyPartnerOnce(
                $affiliate,
                'affiliate_commission_daily_summary',
                [
                    'partner' => $affiliate->name,
                    'amount' => format_money($amount),
                    'count' => $count,
                    'balance' => format_money($available),
                    '_fallback_subject' => __('site.affiliate_portal.notify_daily_summary_subject'),
                    '_fallback_body' => __('site.affiliate_portal.notify_daily_summary_body', [
                        'amount' => format_money($amount),
                        'count' => $count,
                        'balance' => format_money($available),
                    ]),
                ],
                route('site.affiliate.performance', ['tab' => 'commissions']),
                'daily:'.$from->toDateString(),
                20,
            );

            if ($log) {
                $sent++;
            }
        }

        $this->info("Sent {$sent} daily commission summaries.");

        return self::SUCCESS;
    }
}
