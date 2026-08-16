<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuditsActions;
use App\Http\Controllers\Controller;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDocumentRequest;
use App\Services\ApplicationDocumentRequestService;
use App\Services\UnderwritingSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LoanApplicationDocumentRequestController extends Controller
{
    use AuditsActions;

    public function store(
        Request $request,
        LoanApplication $loanApplication,
        ApplicationDocumentRequestService $service,
    ): RedirectResponse {
        $data = $request->validate([
            'type' => ['required', 'in:document,clarification'],
            'label' => ['nullable', 'string', 'max:120'],
            'labels' => ['nullable', 'array'],
            'labels.*' => ['string', 'max:120'],
            'presets' => ['nullable', 'array'],
            'presets.*' => ['string', 'max:120'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'due_at' => ['nullable', 'date', 'after_or_equal:today'],
            'subject_kind' => ['nullable', 'in:borrower,member,guarantor'],
            'subject_customer_id' => ['nullable', 'integer'],
            'loan_group_member_id' => ['nullable', 'integer'],
            'request_subject' => ['nullable', 'string', 'max:64'],
            'review_person' => ['nullable', 'in:borrower,member,guarantor'],
            'review_m' => ['nullable', 'integer'],
            'review_g' => ['nullable', 'integer'],
        ]);

        $labels = collect($data['labels'] ?? [])
            ->merge($data['presets'] ?? [])
            ->push($data['label'] ?? null)
            ->map(fn ($label) => trim((string) $label))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($labels === []) {
            return back()->withErrors(['label' => 'Select or enter at least one document to request.'])->withInput();
        }

        [$subjectKind, $subjectCustomerId, $loanGroupMemberId] = $this->resolvePostedSubject(
            $loanApplication,
            $data,
        );

        $dueAt = isset($data['due_at'])
            ? new \DateTimeImmutable($data['due_at'])
            : now()->addDays(app(UnderwritingSettingsService::class)->documentRequestDefaultDueDays());

        try {
            if (count($labels) === 1) {
                $docRequest = $service->create(
                    $loanApplication,
                    $request->user(),
                    $labels[0],
                    $data['instructions'] ?? null,
                    $dueAt,
                    $data['type'],
                    $subjectKind,
                    $subjectCustomerId,
                    $loanGroupMemberId,
                );

                $this->auditAdmin('admin.loan_applications.document_request_created', $loanApplication, [
                    'request_id' => $docRequest->id,
                    'label' => $labels[0],
                    'type' => $data['type'],
                    'subject_kind' => $subjectKind,
                ]);

                return redirect()
                    ->route('admin.loan-applications.show', $this->reviewReturnParams(
                        $request,
                        $loanApplication,
                        $subjectKind,
                        $loanGroupMemberId,
                    ))
                    ->with('status', 'Document request sent.')
                    ->withFragment('checklist-documents');
            }

            $created = $service->createMany(
                $loanApplication,
                $request->user(),
                $labels,
                $data['instructions'] ?? null,
                $dueAt,
                $data['type'],
                $subjectKind,
                $subjectCustomerId,
                $loanGroupMemberId,
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['request_subject' => $e->getMessage()])->withInput();
        }

        $this->auditAdmin('admin.loan_applications.document_requests_created', $loanApplication, [
            'count' => $created->count(),
            'labels' => $created->pluck('label')->all(),
            'type' => $data['type'],
            'subject_kind' => $subjectKind,
        ]);

        return redirect()
            ->route('admin.loan-applications.show', $this->reviewReturnParams(
                $request,
                $loanApplication,
                $subjectKind,
                $loanGroupMemberId,
            ))
            ->with('status', $created->count().' document requests sent.')
            ->withFragment('checklist-documents');
    }

    /**
     * Person on this desk wins. A composer that posts borrower by mistake must
     * not attach a member/guarantor ask to the leader — or to every sibling.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: string, 1: ?int, 2: ?int}
     */
    private function resolvePostedSubject(LoanApplication $loanApplication, array $data): array
    {
        $requestSubject = (string) ($data['request_subject'] ?? '');
        $reviewPerson = (string) ($data['review_person'] ?? '');
        $reviewM = (int) ($data['review_m'] ?? 0);
        $reviewG = (int) ($data['review_g'] ?? 0);
        $subjectCustomerId = isset($data['subject_customer_id']) ? (int) $data['subject_customer_id'] : null;
        $loanGroupMemberId = isset($data['loan_group_member_id']) ? (int) $data['loan_group_member_id'] : null;

        if (str_starts_with($requestSubject, 'member:')) {
            return ['member', $subjectCustomerId, (int) substr($requestSubject, strlen('member:'))];
        }
        if (str_starts_with($requestSubject, 'guarantor:')) {
            return ['guarantor', (int) substr($requestSubject, strlen('guarantor:')), null];
        }

        if ($reviewPerson === 'member') {
            return ['member', $subjectCustomerId, $reviewM > 0 ? $reviewM : $loanGroupMemberId];
        }

        if ($reviewPerson === 'guarantor') {
            if (! $subjectCustomerId && $reviewG > 0) {
                $link = $loanApplication->customerGuarantors()->with('invitation')->find($reviewG);
                $fromInvite = (int) ($link?->invitation?->guarantor_customer_id ?? 0);
                $subjectCustomerId = $fromInvite > 0 ? $fromInvite : null;
            }

            return ['guarantor', $subjectCustomerId, null];
        }

        return ['borrower', $loanApplication->customer_id ? (int) $loanApplication->customer_id : $subjectCustomerId, null];
    }

    /**
     * @return array<string, mixed>
     */
    private function reviewReturnParams(
        Request $request,
        LoanApplication $loanApplication,
        string $subjectKind,
        ?int $loanGroupMemberId,
    ): array {
        $person = in_array($subjectKind, ['borrower', 'member', 'guarantor'], true)
            ? $subjectKind
            : 'borrower';

        return array_filter([
            'loan_application' => $loanApplication,
            'workspace' => 'checklist',
            'capacity_tab' => 'documents',
            'review_person' => $person,
            'review_m' => $person === 'member'
                ? ($loanGroupMemberId ?: $request->input('review_m'))
                : null,
            'review_g' => $person === 'guarantor' ? $request->input('review_g') : null,
        ], fn ($value) => $value !== null && $value !== '' && $value !== 0 && $value !== '0');
    }

    public function satisfy(
        Request $request,
        LoanApplicationDocumentRequest $documentRequest,
        ApplicationDocumentRequestService $service,
    ): RedirectResponse {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if ($documentRequest->status !== 'uploaded') {
            return back()->with('error', 'Only uploaded requests can be marked satisfied.');
        }

        $service->markSatisfied($documentRequest, $request->user(), $data['notes'] ?? null);

        $this->auditAdmin('admin.loan_applications.document_request_satisfied', $documentRequest->application, [
            'request_id' => $documentRequest->id,
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()
            ->route('admin.loan-applications.show', $documentRequest->loan_application_id)
            ->with('status', 'Request marked as satisfied.');
    }

    public function reject(
        Request $request,
        LoanApplicationDocumentRequest $documentRequest,
        ApplicationDocumentRequestService $service,
    ): RedirectResponse {
        $data = $request->validate([
            'notes' => ['required', 'string', 'max:500'],
        ]);

        if (! in_array($documentRequest->status, ['uploaded'], true)) {
            return back()->with('error', 'Only uploaded requests can be rejected.');
        }

        $service->reject($documentRequest, $request->user(), $data['notes']);

        $this->auditAdmin('admin.loan_applications.document_request_rejected', $documentRequest->application, [
            'request_id' => $documentRequest->id,
            'notes' => $data['notes'],
        ]);

        return redirect()
            ->route('admin.loan-applications.show', $documentRequest->loan_application_id)
            ->with('status', 'Request rejected — borrower can re-upload.');
    }
}
