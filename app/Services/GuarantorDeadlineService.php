<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoanApplication;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class GuarantorDeadlineService
{
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
            'status'               => 'awaiting_guarantor',
            'current_stage'        => 'awaiting_guarantor',
            'guarantor_deadline_at'=> now()->addDays($days),
        ]);

        $this->notifyBorrower(
            $application,
            'guarantor_deadline_started',
            __('borrower.loan_profile.guarantor_deadline_started_title'),
            __('borrower.loan_profile.guarantor_deadline_started_body', [
                'days' => $days,
                'date' => optional($application->guarantor_deadline_at)->timezone(config('app.timezone'))->format('d M Y'),
            ]),
            'borrower.loan_profile.guarantor_deadline_started_title',
            'borrower.loan_profile.guarantor_deadline_started_body',
            [
                'days' => $days,
                'date' => optional($application->guarantor_deadline_at)->timezone(config('app.timezone'))->format('d M Y'),
            ],
        );
    }

    public function clearDeadline(LoanApplication $application): void
    {
        if ($application->guarantor_deadline_at) {
            $application->update(['guarantor_deadline_at' => null]);
        }
    }

    /** @return array{deadline_at: ?Carbon, days_left: ?int, expired: bool, label: ?string} */
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
            ];
        }

        $daysLeft = (int) now()->startOfDay()->diffInDays($deadline->copy()->startOfDay(), false);

        return [
            'deadline_at' => $deadline,
            'days_left'   => $daysLeft,
            'expired'     => $application->status === 'expired' || $daysLeft < 0,
            'label'       => $daysLeft < 0
                ? __('borrower.loan_profile.guarantor_deadline_passed')
                : __('borrower.loan_profile.guarantor_deadline_days_left', [
                    'days' => max(0, $daysLeft),
                    'date' => $deadline->timezone(config('app.timezone'))->format('d M Y'),
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

                    $this->notifyBorrower(
                        $application,
                        'guarantor_deadline_expired',
                        __('borrower.loan_profile.guarantor_deadline_expired_title'),
                        __('borrower.loan_profile.guarantor_deadline_expired_body', [
                            'number' => $application->application_number,
                        ]),
                        'borrower.loan_profile.guarantor_deadline_expired_title',
                        'borrower.loan_profile.guarantor_deadline_expired_body',
                        ['number' => $application->application_number],
                    );

                    $expired->push($application);
                }
            });

        return $expired;
    }

    /** Remind borrowers approaching the deadline (3 days and 1 day left). */
    public function sendReminders(): int
    {
        $sent = 0;

        foreach ([3, 1] as $daysLeft) {
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
                        $this->notifyBorrower(
                            $application,
                            'guarantor_deadline_reminder_'.$daysLeft,
                            __('borrower.loan_profile.guarantor_deadline_reminder_title'),
                            __('borrower.loan_profile.guarantor_deadline_reminder_body', [
                                'days' => $daysLeft,
                                'date' => optional($application->guarantor_deadline_at)->timezone(config('app.timezone'))->format('d M Y'),
                            ]),
                            'borrower.loan_profile.guarantor_deadline_reminder_title',
                            'borrower.loan_profile.guarantor_deadline_reminder_body',
                            [
                                'days' => $daysLeft,
                                'date' => optional($application->guarantor_deadline_at)->timezone(config('app.timezone'))->format('d M Y'),
                            ],
                        );
                        $sent++;
                    }
                });
        }

        return $sent;
    }

    private function notifyBorrower(
        LoanApplication $application,
        string $template,
        string $title,
        string $body,
        string $titleKey,
        string $bodyKey,
        array $params,
    ): void {
        $customer = $application->customer;
        if (! $customer instanceof Customer) {
            return;
        }

        try {
            $this->notifications->notifyInApp(
                $customer,
                $body,
                'loan_updates',
                $template,
                $title,
                route('site.borrower.application', $application),
                __('borrower.loan_profile.guarantor_deadline_cta'),
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
