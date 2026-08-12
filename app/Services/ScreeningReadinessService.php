<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\User;

/**
 * Layperson-facing readiness + suggested screening decision from checklist, CRB, and affordability.
 */
class ScreeningReadinessService
{
    public function __construct(
        private readonly ScreeningChecklistService $checklist,
        private readonly CrbCreditCheckService $crb,
    ) {}

    /**
     * @param  array<string, mixed>  $review
     * @param  array<string, mixed>|null  $groupReview
     * @param  list<array<string, mixed>>  $anomalies
     * @return array{
     *   ready: bool,
     *   suggestion: string,
     *   suggestion_label: string,
     *   headline: string,
     *   detail: string,
     *   tone: string,
     *   blockers: list<string>,
     *   signals: list<string>,
     *   checklist_done: int,
     *   checklist_total: int,
     *   checklist_failed: int,
     *   subjects_incomplete: int,
     *   subjects_total: int,
     * }
     */
    public function forApplication(
        LoanApplication $application,
        array $review,
        ?array $groupReview = null,
        array $anomalies = [],
        ?User $actor = null,
    ): array {
        $actor = $actor ?? auth()->user();
        $subjects = $this->checklist->deskSubjects($application, $review, $groupReview, $actor);

        $checklistDone = 0;
        $checklistTotal = 0;
        $checklistFailed = 0;
        $incomplete = [];
        $failedSubjects = [];

        foreach ($subjects as $subject) {
            $checklistDone += (int) ($subject['done'] ?? 0);
            $checklistTotal += (int) ($subject['total'] ?? 0);
            $checklistFailed += (int) ($subject['failed'] ?? 0);
            if (! ($subject['complete'] ?? false)) {
                $incomplete[] = trim(($subject['label'] ?? 'Subject').' · '.($subject['sublabel'] ?? ''));
            }
            if ((int) ($subject['failed'] ?? 0) > 0) {
                $failedSubjects[] = trim(($subject['label'] ?? 'Subject').' · '.($subject['sublabel'] ?? ''));
            }
        }

        $afford = (array) ($review['affordability'] ?? []);
        $affordVerdict = strtolower((string) ($afford['verdict'] ?? ''));
        if ($affordVerdict === '' && array_key_exists('pass', $afford)) {
            $affordVerdict = ($afford['pass'] ?? false) ? 'pass' : 'fail';
        }

        $crbSignal = $this->crbSignal($review, $groupReview);
        $criticalFlags = collect($anomalies)->where('severity', 'critical')->count();
        $warningFlags = collect($anomalies)->where('severity', 'warning')->count();

        $blockers = [];
        $signals = [];

        if ($incomplete !== []) {
            $blockers[] = 'Checklist incomplete for: '.implode(', ', array_slice($incomplete, 0, 4))
                .(count($incomplete) > 4 ? '…' : '');
        }
        if ($checklistFailed > 0) {
            $blockers[] = $checklistFailed.' checklist Fail'
                .($failedSubjects !== [] ? ' ('.implode(', ', array_slice($failedSubjects, 0, 3)).')' : '');
        }
        if ($affordVerdict === 'fail') {
            $blockers[] = 'Affordability fails — borrower capacity does not support the proposed instalment';
        } elseif ($affordVerdict === 'warn') {
            $signals[] = 'Affordability is near the limit (warn)';
        } elseif ($affordVerdict === 'pass') {
            $signals[] = 'Affordability pass';
        } else {
            $signals[] = 'Affordability not calculated yet';
        }

        $signals[] = 'CRB '.$crbSignal['label'].' → '.strtoupper($crbSignal['recommendation']);
        if ($crbSignal['recommendation'] === 'reject') {
            $blockers[] = 'CRB leans REJECT ('.$crbSignal['label'].')';
        } elseif ($crbSignal['recommendation'] === 'refer') {
            $signals[] = 'CRB leans REFER — do not auto-approve';
        }

        if ($criticalFlags > 0) {
            $blockers[] = $criticalFlags.' critical review flag'.($criticalFlags === 1 ? '' : 's');
        } elseif ($warningFlags > 0) {
            $signals[] = $warningFlags.' warning flag'.($warningFlags === 1 ? '' : 's');
        }

        $ready = $incomplete === [] && $checklistTotal > 0;
        $suggestion = $this->suggest($ready, $checklistFailed, $affordVerdict, $crbSignal['recommendation'], $criticalFlags);
        $labels = [
            'hold' => 'Hold — finish checklist',
            'approve' => 'Lean Approve',
            'reject' => 'Lean Reject',
            'counter' => 'Lean Counter-offer / Refer',
        ];

        return [
            'ready' => $ready,
            'suggestion' => $suggestion,
            'suggestion_label' => $labels[$suggestion] ?? strtoupper($suggestion),
            'headline' => $this->headline($ready, $suggestion),
            'detail' => $this->detail($ready, $suggestion, $checklistDone, $checklistTotal),
            'tone' => match ($suggestion) {
                'approve' => 'good',
                'reject' => 'bad',
                'counter' => 'warn',
                default => 'neutral',
            },
            'blockers' => array_values($blockers),
            'signals' => array_values($signals),
            'checklist_done' => $checklistDone,
            'checklist_total' => $checklistTotal,
            'checklist_failed' => $checklistFailed,
            'subjects_incomplete' => count($incomplete),
            'subjects_total' => count($subjects),
        ];
    }

    /**
     * @param  array<string, mixed>  $review
     * @param  array<string, mixed>|null  $groupReview
     * @return array{recommendation: string, label: string}
     */
    private function crbSignal(array $review, ?array $groupReview): array
    {
        $members = collect($groupReview['members'] ?? []);
        if ($members->isNotEmpty()) {
            $worst = 'approve';
            $labels = [];
            foreach ($members as $member) {
                $score = isset($member['crb_score']) && is_numeric($member['crb_score'])
                    ? (int) $member['crb_score']
                    : null;
                $band = $this->crb->scoreBandFeedback($score);
                $labels[] = ($member['name'] ?? 'Member').': '.$band['label'];
                $worst = $this->worseCrb($worst, $band['recommendation']);
            }

            return [
                'recommendation' => $worst,
                'label' => $labels !== [] ? 'group ('.implode('; ', array_slice($labels, 0, 3)).')' : 'group',
            ];
        }

        $crb = (array) ($review['crb'] ?? []);
        $score = isset($crb['score']) && is_numeric($crb['score']) ? (int) $crb['score'] : null;
        $band = $this->crb->scoreBandFeedback($score);
        $rec = strtolower((string) ($crb['recommendation'] ?? $band['recommendation']));
        if (! in_array($rec, ['approve', 'refer', 'reject'], true)) {
            $rec = $band['recommendation'];
        }

        return [
            'recommendation' => $rec,
            'label' => $band['label'],
        ];
    }

    private function worseCrb(string $current, string $next): string
    {
        $rank = ['approve' => 1, 'refer' => 2, 'reject' => 3];

        return ($rank[$next] ?? 2) > ($rank[$current] ?? 2) ? $next : $current;
    }

    private function suggest(
        bool $ready,
        int $checklistFailed,
        string $affordVerdict,
        string $crbRec,
        int $criticalFlags,
    ): string {
        if (! $ready) {
            return 'hold';
        }

        if ($checklistFailed > 0 || $affordVerdict === 'fail' || $crbRec === 'reject' || $criticalFlags > 0) {
            return 'reject';
        }

        if ($affordVerdict === 'warn' || $crbRec === 'refer') {
            return 'counter';
        }

        if ($crbRec === 'approve' && in_array($affordVerdict, ['pass', ''], true)) {
            return 'approve';
        }

        return 'counter';
    }

    private function headline(bool $ready, string $suggestion): string
    {
        if (! $ready) {
            return 'Not ready to decide yet';
        }

        return match ($suggestion) {
            'approve' => 'Ready — file leans Approve',
            'reject' => 'Ready — file leans Reject',
            'counter' => 'Ready — lean Counter-offer / careful Refer',
            default => 'Ready for Decision',
        };
    }

    private function detail(bool $ready, string $suggestion, int $done, int $total): string
    {
        $progress = $total > 0 ? "{$done}/{$total} checklist items reviewed" : 'No checklist items yet';

        if (! $ready) {
            return $progress.'. Finish every subject Pass/Fail, then open Decision.';
        }

        return match ($suggestion) {
            'approve' => $progress.'. Checklist clear, capacity and CRB support approval — confirm on Decision.',
            'reject' => $progress.'. Fail signals are strong enough to lean reject — record reasons on Decision.',
            'counter' => $progress.'. Mixed or moderate signals — prefer counter-offer or a cautious recommendation.',
            default => $progress.'. Open Decision to record your recommendation.',
        };
    }
}
