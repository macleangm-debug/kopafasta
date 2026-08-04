<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerGuarantor;
use App\Models\LoanApplication;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class GuarantorDeadlineService
{
    /** Reminder milestones (days remaining) before the guarantor window closes. */
    public const REMINDER_DAYS = [7, 5, 3, 1];

    public function __construct(
        private readonly UnderwritingSettingsService $settings,
        private readonly NotificationService $notifications,
    ) {}

    public function deadlineDays(): int
    {
        return max(1, min(90, $this->settings->awaitingGuarantorDeadlineDays()));
    }

    public function markAwaiting(LoanApplication $application): void
    {
        $days = $this->deadlineDays();
        $application->update([
            'status'                => 'awaiting_guarantor',
            'current_stage'         => 'awaiting_guarantor',
            'guarantor_deadline_at' => now()->addDays($days),
        ]);

        $application->refresh();
        $date = optional($application->guarantor_deadline_at)->timezone(config('app.timezone'))->format('d M Y');

        $this->notifyParties(
            $application,
            'guarantor_deadline_started',
            __('borrower.loan_profile.guarantor_deadline_started_title'),
            __('borrower.loan_profile.guarantor_deadline_started_body', [
                'days' => $days,
                'date' => $date,
            ]),
            'borrower.loan_profile.guarantor_deadline_started_title',
            'borrower.loan_profile.guarantor_deadline_started_body',
            ['days' => $days, 'date' => $date],
            __('borrower.loan_profile.guarantor_deadline_started_guarantor_title'),
            __('borrower.loan_profile.guarantor_deadline_started_guarantor_body', [
                'days' => $days,
                'date' => $date,
            ]),
            'borrower.loan_profile.guarantor_deadline_started_guarantor_title',
            'borrower.loan_profile.guarantor_deadline_started_guarantor_body',
        );
    }

    public function clearDeadline(LoanApplication $application): void
    {
        if ($application->guarantor_deadline_at) {
            $application->update(['guarantor_deadline_at' => null]);
        }
    }

    /** @return array{deadline_at: ?Carbon, days_left: ?int, expired: bool, label: ?string, date: ?string} */
    public function progress(LoanApplication $application): array
    {
        $deadline = $application->guarantor_deadline_at
            ? Carbon::parse($application->guarantor_deadline_at)
            : null;

        if (! $deadline) {
            return [
                'deadline_at' => null,
                'days_left'   => null,
                'expired'     => $application->status === 'expired',
                'label'       => null,
                'date'        => null,
            ];
        }

        $daysLeft = (int) now()->startOfDay()->diffInDays($deadline->copy()->startOfDay(), false);
        $date = $deadline->timezone(config('app.timezone'))->format('d M Y');

        return [
            'deadline_at' => $deadline,
            'days_left'   => $daysLeft,
            'expired'     => $application->status === 'expired' || $daysLeft < 0,
            'date'        => $date,
            'label'       => $daysLeft < 0
                ? __('borrower.loan_profile.guarantor_deadline_passed')
                : __('borrower.loan_profile.guarantor_deadline_days_left', [
                    'days' => max(0, $daysLeft),
                    'date' => $date,
                ]),
        ];
    }

    /** @return Collection<int, LoanApplication> */
    public function expireStale(): Collection
    {
        $expired = collect();

        LoanApplication::query()
            ->with('customer')
            ->where('status', 'awaiting_guarantor')
            ->whereNotNull('guarantor_deadline_at')
            ->where('guarantor_deadline_at', '<', now())
            ->orderBy('id')
            ->chunkById(100, function ($rows) use (&$expired): void {
                foreach ($rows as $application) {
                    $application->update([
                        'status'        => 'expired',
                        'current_stage' => 'expired',
                    ]);

                    $this->notifyParties(
                        $application,
                        'guarantor_deadline_expired',
                        __('borrower.loan_profile.guarantor_deadline_expired_title'),
                        __('borrower.loan_profile.guarantor_deadline_expired_body', [
                            'number' => $application->application_number,
                        ]),
                        'borrower.loan_profile.guarantor_deadline_expired_title',
                        'borrower.loan_profile.guarantor_deadline_expired_body',
                        ['number' => $application->application_number],
                        __('borrower.loan_profile.guarantor_deadline_expired_guarantor_title'),
                        __('borrower.loan_profile.guarantor_deadline_expired_guarantor_body', [
                            'number' => $application->application_number,
                        ]),
                        'borrower.loan_profile.guarantor_deadline_expired_guarantor_title',
                        'borrower.loan_profile.guarantor_deadline_expired_guarantor_body',
                    );

                    $expired->push($application);
                }
            });

        return $expired;
    }

    /**
     * Remind borrower and guarantor(s) at 7, 5, 3, and 1 day(s) remaining.
     */
    public function sendReminders(): int
    {
        $sent = 0;

        foreach (self::REMINDER_DAYS as $daysLeft) {
            $start = now()->addDays($daysLeft)->startOfDay();
            $end = now()->addDays($daysLeft)->endOfDay();

            LoanApplication::query()
                ->with('customer')
                ->where('status', 'awaiting_guarantor')
                ->whereNotNull('guarantor_deadline_at')
                ->whereBetween('guarantor_deadline_at', [$start, $end])
                ->orderBy('id')
                ->chunkById(100, function ($rows) use (&$sent, $daysLeft): void {
                    foreach ($rows as $application) {
                        $date = optional($application->guarantor_deadline_at)
                            ->timezone(config('app.timezone'))
                            ->format('d M Y');

                        $this->notifyParties(
                            $application,
                            'guarantor_deadline_reminder_'.$daysLeft,
                            __('borrower.loan_profile.guarantor_deadline_reminder_title'),
                            __('borrower.loan_profile.guarantor_deadline_reminder_body', [
                                'days' => $daysLeft,
                                'date' => $date,
                            ]),
                            'borrower.loan_profile.guarantor_deadline_reminder_title',
                            'borrower.loan_profile.guarantor_deadline_reminder_body',
                            ['days' => $daysLeft, 'date' => $date],
                            __('borrower.loan_profile.guarantor_deadline_reminder_guarantor_title'),
                            __('borrower.loan_profile.guarantor_deadline_reminder_guarantor_body', [
                                'days' => $daysLeft,
                                'date' => $date,
                            ]),
                            'borrower.loan_profile.guarantor_deadline_reminder_guarantor_title',
                            'borrower.loan_profile.guarantor_deadline_reminder_guarantor_body',
                        );
                        $sent++;
                    }
                });
        }

        return $sent;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function notifyParties(
        LoanApplication $application,
        string $template,
        string $borrowerTitle,
        string $borrowerBody,
        string $borrowerTitleKey,
        string $borrowerBodyKey,
        array $params,
        string $guarantorTitle,
        string $guarantorBody,
        string $guarantorTitleKey,
        string $guarantorBodyKey,
    ): void {
        $borrower = $application->customer;
        if ($borrower instanceof Customer) {
            $this->safeNotify(
                $borrower,
                $borrowerBody,
                $template,
                $borrowerTitle,
                route('site.borrower.application', $application),
                __('borrower.loan_profile.guarantor_deadline_cta'),
                $borrowerTitleKey,
                $borrowerBodyKey,
                $params,
            );
        }

        foreach ($this->activeGuarantorTargets($application) as $target) {
            $this->safeNotify(
                $target['customer'],
                $guarantorBody,
                $template.'_guarantor',
                $guarantorTitle,
                $target['url'],
                __('borrower.loan_profile.guarantor_deadline_guarantor_cta'),
                $guarantorTitleKey,
                $guarantorBodyKey,
                $params,
            );
        }
    }

    /**
     * @return list<array{customer: Customer, url: string}>
     */
    private function activeGuarantorTargets(LoanApplication $application): array
    {
        $access = app(GuarantorAccessService::class);
        $targets = [];
        $seen = [];

        $links = CustomerGuarantor::query()
            ->with('invitation')
            ->where('loan_application_id', $application->id)
            ->whereIn('status', ['pending', 'approved'])
            ->get();

        foreach ($links as $link) {
            $customer = $access->guarantorCustomerForLink($link);
            if (! $customer instanceof Customer || isset($seen[$customer->id])) {
                continue;
            }
            $seen[$customer->id] = true;
            $targets[] = [
                'customer' => $customer,
                'url'      => $link->status === 'approved'
                    ? route('site.borrower.guaranteed.show', $link)
                    : route('site.borrower.guarantor-requests.show', $link),
            ];
        }

        return $targets;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function safeNotify(
        Customer $customer,
        string $body,
        string $template,
        string $title,
        string $actionUrl,
        string $actionLabel,
        string $titleKey,
        string $bodyKey,
        array $params,
    ): void {
        $prefs = $customer->user?->preferences['notifications'] ?? [];
        $wantsGuarantor = ! array_key_exists('guarantor_updates', $prefs) || ! empty($prefs['guarantor_updates']);
        if (! $wantsGuarantor) {
            return;
        }

        try {
            $this->notifications->notifyInApp(
                $customer,
                $body,
                'loan_updates',
                $template,
                $title,
                $actionUrl,
                $actionLabel,
                [
                    'title_key' => $titleKey,
                    'body_key'  => $bodyKey,
                    'params'    => $params,
                ],
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
