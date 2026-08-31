<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Formal trail when Screening disagrees with a reviewable system finding.
 * Does not create a second checklist. Hard policy failures cannot be accepted.
 */
class ScreeningExceptionService
{
    /** Reviewable bureau / profile discrepancies — not hard underwriting failures. */
    public const REVIEWABLE_CODES = [
        'spouse_missing_on_crb',
        'spouse_mismatch',
        'marital_mismatch',
        'children_mismatch',
        'employment_soft_mismatch',
        'crb_refer',
        'crb_name_unusable',
        'crb_no_record',
    ];

    /** Hard identity / policy failures — cannot be converted into an accepted discrepancy. */
    public const HARD_CODES = [
        'name_mismatch',
        'nida_dob_mismatch',
        'nida_impossible',
        'dob_mismatch',
        'gender_mismatch',
        'id_mismatch',
        'crb_reject',
        'crb_never_checked',
    ];

    /**
     * @return list<string>
     */
    public function reviewableCodes(): array
    {
        return self::REVIEWABLE_CODES;
    }

    public function isHardCode(string $code): bool
    {
        return in_array($code, self::HARD_CODES, true);
    }

    public function isReviewableCode(string $code): bool
    {
        return in_array($code, self::REVIEWABLE_CODES, true) && ! $this->isHardCode($code);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function isReviewableItem(array $item): bool
    {
        $code = (string) ($item['fail_reason_code'] ?? '');

        return $this->isReviewableCode($code);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function isAccepted(array $item): bool
    {
        return ($item['analyst_review'] ?? null) === 'accepted'
            && $this->isReviewableItem($item);
    }

    /**
     * @return array<string, mixed>
     */
    public function accept(
        LoanApplication $application,
        User $actor,
        string $code,
        string $reason,
        ?string $detail = null,
        string $person = 'borrower',
        ?int $memberId = null,
        ?int $guarantorLinkId = null,
        ?string $itemKey = null,
        ?string $systemLabel = null,
    ): array {
        if ($this->isHardCode($code) || ! $this->isReviewableCode($code)) {
            throw new \InvalidArgumentException('This system finding cannot be accepted. Hard policy failures stay blocking.');
        }

        $reason = trim($reason);
        if (strlen($reason) < 12) {
            throw new \InvalidArgumentException('Explain why you are accepting this discrepancy.');
        }

        $itemKey = $itemKey ?: $this->itemKeyForCode($code, $person);
        $severity = $this->severityFor($code);
        $payload = is_array($application->screening_payload) ? $application->screening_payload : [];
        $exceptions = is_array($payload['screening_exceptions'] ?? null) ? $payload['screening_exceptions'] : [];
        $existingIndex = collect($exceptions)->search(fn ($row) => ($row['code'] ?? '') === $code
            && ($row['person'] ?? 'borrower') === $person
            && (int) ($row['m'] ?? 0) === (int) $memberId
            && (int) ($row['g'] ?? 0) === (int) $guarantorLinkId);
        $exception = [
            'id' => is_int($existingIndex) ? (string) ($exceptions[$existingIndex]['id'] ?? Str::ulid()) : (string) Str::ulid(),
            'code' => $code,
            'item_key' => $itemKey,
            'person' => $person,
            'm' => $memberId,
            'g' => $guarantorLinkId,
            'gate' => str_contains((string) $itemKey, 'crb') || $code === 'crb_refer' || $code === 'crb_name_unusable' ? 'crb' : 'identity',
            'system_outcome' => $this->systemOutcomeLabel($code),
            'system_label' => $systemLabel ?: $detail,
            'analyst_outcome' => 'accepted',
            'reason' => $reason,
            'severity' => $severity,
            'by' => $actor->id,
            'by_name' => $actor->name,
            'at' => now()->toIso8601String(),
            'committee' => is_int($existingIndex) ? ($exceptions[$existingIndex]['committee'] ?? null) : null,
        ];
        if (is_int($existingIndex)) {
            $exceptions[$existingIndex] = $exception;
        } else {
            $exceptions[] = $exception;
        }
        $payload['screening_exceptions'] = $exceptions;

        $waivers = is_array($payload['discrepancy_waivers'] ?? null) ? $payload['discrepancy_waivers'] : [];
        $waivers[$this->waiverKey($code, $person, $memberId, $guarantorLinkId)] = [
            'by' => $actor->id,
            'by_name' => $actor->name,
            'at' => now()->toIso8601String(),
            'reason' => $reason,
            'detail' => $detail,
            'code' => $code,
            'person' => $person,
            'm' => $memberId,
            'g' => $guarantorLinkId,
        ];
        $payload['discrepancy_waivers'] = $waivers;
        $payload = $this->markChecklistAccepted($payload, $actor, $itemKey, $code, $reason, $person, $memberId, $guarantorLinkId);

        $application->forceFill(['screening_payload' => $payload])->save();

        return $exception;
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(LoanApplication $application): array
    {
        $rows = $this->all($application);
        $openMaterial = collect($rows)->filter(fn ($row) => ($row['severity'] ?? '') === 'material' && empty($row['committee']['status']));

        return [
            'total' => count($rows),
            'critical' => collect($rows)->where('severity', 'critical')->count(),
            'material' => collect($rows)->where('severity', 'material')->count(),
            'information' => collect($rows)->where('severity', 'information')->count(),
            'unacknowledged_material' => $openMaterial->count(),
            'items' => $rows,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(LoanApplication $application): array
    {
        $rows = data_get($application->screening_payload, 'screening_exceptions', []);
        if (! is_array($rows)) {
            return [];
        }

        $requests = $application->documentRequests()->orderBy('id')->get();

        return array_values(array_map(function ($row) use ($requests) {
            if (! is_array($row)) {
                return $row;
            }
            $itemKey = (string) ($row['item_key'] ?? '');
            $person = (string) ($row['person'] ?? 'borrower');
            $related = $requests->filter(function ($req) use ($itemKey, $person, $row) {
                if ($itemKey !== '' && (string) $req->checklist_item === $itemKey) {
                    return true;
                }

                return (string) $req->subject_kind === $person
                    && filled($req->request_reason)
                    && (int) ($req->loan_group_member_id ?? 0) === (int) ($row['m'] ?? 0);
            });
            $row['request_history'] = $related->map(function ($req) {
                $lifecycle = is_array($req->lifecycle) ? $req->lifecycle : [];
                $events = [
                    [
                        'label' => 'Requested '.$req->label,
                        'status' => $req->request_reason ?: 'Request sent',
                        'at' => optional($req->created_at)?->toIso8601String(),
                    ],
                ];
                foreach ($lifecycle['reminders'] ?? [] as $reminder) {
                    $events[] = [
                        'label' => 'Reminder',
                        'status' => ! empty($reminder['final']) ? 'Final reminder' : ('Day '.($reminder['day'] ?? '')),
                        'at' => $reminder['at'] ?? null,
                    ];
                }
                if ($req->status === 'uploaded' || $req->satisfied_at) {
                    $events[] = [
                        'label' => 'Replacement submitted',
                        'status' => ucfirst((string) $req->status),
                        'at' => optional($req->satisfied_at ?? $req->updated_at)?->toIso8601String(),
                    ];
                }
                if ($req->status === 'satisfied') {
                    $events[] = [
                        'label' => 'Screening accepted',
                        'status' => 'Satisfied',
                        'at' => optional($req->satisfied_at)?->toIso8601String(),
                    ];
                }
                if ($req->status === 'expired') {
                    $events[] = [
                        'label' => 'Expired',
                        'status' => 'Required information not provided',
                        'at' => $lifecycle['closed_at'] ?? null,
                    ];
                }

                return $events;
            })->flatten(1)->values()->all();

            return $row;
        }, $rows));
    }

    public function acknowledge(LoanApplication $application, User $actor, string $id): void
    {
        $this->patchCommittee($application, $id, [
            'status' => 'acknowledged',
            'by' => $actor->id,
            'by_name' => $actor->name,
            'at' => now()->toIso8601String(),
        ]);
    }

    public function requestClarification(LoanApplication $application, User $actor, string $id, string $note): void
    {
        $this->patchCommittee($application, $id, [
            'status' => 'clarification',
            'by' => $actor->id,
            'by_name' => $actor->name,
            'at' => now()->toIso8601String(),
            'note' => trim($note),
        ]);
    }

    public function waiverStored(LoanApplication $application, string $code, string $person = 'borrower', ?int $memberId = null, ?int $guarantorLinkId = null): bool
    {
        return $this->waiverFor($application, $code, $person, $memberId, $guarantorLinkId) !== null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function waiverFor(LoanApplication $application, string $code, string $person = 'borrower', ?int $memberId = null, ?int $guarantorLinkId = null): ?array
    {
        $waivers = data_get($application->screening_payload, 'discrepancy_waivers', []);
        if (! is_array($waivers)) {
            return null;
        }
        $row = $waivers[$this->waiverKey($code, $person, $memberId, $guarantorLinkId)] ?? $waivers[$code] ?? null;

        return is_array($row) ? $row : null;
    }

    public function flagLevel(array $flag, bool $resolved = false): string
    {
        if ($resolved) {
            return 'resolved';
        }

        return match ($flag['severity'] ?? 'info') {
            'critical' => 'critical',
            'warning' => 'needs_review',
            default => 'information',
        };
    }

    public function itemKeyForCode(string $code, string $person = 'borrower'): string
    {
        if (in_array($code, ['spouse_missing_on_crb', 'spouse_mismatch', 'marital_mismatch', 'children_mismatch'], true)) {
            return 'identity.marital_vs_crb';
        }
        if ($code === 'crb_name_unusable' || $code === 'crb_no_record') {
            return 'identity.name_vs_crb';
        }
        if ($code === 'crb_refer') {
            return match ($person) {
                'member' => 'member_wrap.crb_reviewed',
                'guarantor' => 'guarantor_wrap.crb_reviewed',
                default => 'credit_file.crb_reviewed',
            };
        }

        return 'identity.name_vs_crb';
    }

    private function severityFor(string $code): string
    {
        return match ($code) {
            'spouse_missing_on_crb', 'children_mismatch' => 'information',
            default => 'material',
        };
    }

    private function systemOutcomeLabel(string $code): string
    {
        return match ($code) {
            'crb_refer' => 'REFER',
            'crb_name_unusable' => 'REFER',
            'crb_no_record' => 'NO RECORD',
            default => 'DISCREPANCY',
        };
    }

    private function waiverKey(string $code, string $person, ?int $memberId, ?int $guarantorLinkId): string
    {
        if ($person === 'member' && $memberId) {
            return $code.':member:'.$memberId;
        }
        if ($person === 'guarantor' && $guarantorLinkId) {
            return $code.':guarantor:'.$guarantorLinkId;
        }

        return $code;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function markChecklistAccepted(
        array $payload,
        User $actor,
        string $itemKey,
        string $code,
        string $reason,
        string $person,
        ?int $memberId,
        ?int $guarantorLinkId,
    ): array {
        $subject = match ($person) {
            'member' => 'member:'.(int) $memberId,
            'guarantor' => 'guarantor:'.(int) $guarantorLinkId,
            default => 'borrower',
        };
        $root = is_array($payload['screening_checklist'] ?? null) ? $payload['screening_checklist'] : [];
        $bySubject = is_array($root['by_subject'] ?? null) ? $root['by_subject'] : [];
        $items = is_array($bySubject[$subject]['items'] ?? null) ? $bySubject[$subject]['items'] : [];
        $existing = is_array($items[$itemKey] ?? null) ? $items[$itemKey] : [];
        $items[$itemKey] = array_merge($existing, [
            'key' => $itemKey,
            'analyst_review' => 'accepted',
            'analyst_reason' => $reason,
            'analyst_at' => now()->toIso8601String(),
            'analyst_by' => $actor->id,
            'fail_reason_code' => $existing['fail_reason_code'] ?? $code,
            'source' => $existing['source'] ?? 'system',
        ]);
        $bySubject[$subject]['items'] = $items;
        $bySubject[$subject]['updated_at'] = now()->toIso8601String();
        $bySubject[$subject]['updated_by'] = $actor->id;
        $root['by_subject'] = $bySubject;
        $payload['screening_checklist'] = $root;

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $committee
     */
    private function patchCommittee(LoanApplication $application, string $id, array $committee): void
    {
        $payload = is_array($application->screening_payload) ? $application->screening_payload : [];
        $rows = is_array($payload['screening_exceptions'] ?? null) ? $payload['screening_exceptions'] : [];
        $found = false;
        foreach ($rows as $i => $row) {
            if (($row['id'] ?? '') === $id) {
                $rows[$i]['committee'] = $committee;
                $found = true;
                break;
            }
        }
        if (! $found) {
            throw new \InvalidArgumentException('That screening exception is not on this file.');
        }
        $payload['screening_exceptions'] = $rows;
        $application->forceFill(['screening_payload' => $payload])->save();
    }
}
