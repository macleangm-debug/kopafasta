<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuditsActions;
use App\Http\Controllers\Controller;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDocumentRequest;
use App\Services\ApplicationDocumentRequestService;
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
            'type'                 => ['required', 'in:document,clarification'],
            'label'                => ['nullable', 'string', 'max:120'],
            'labels'               => ['nullable', 'array'],
            'labels.*'             => ['string', 'max:120'],
            'presets'              => ['nullable', 'array'],
            'presets.*'            => ['string', 'max:120'],
            'instructions'         => ['nullable', 'string', 'max:2000'],
            'due_at'               => ['nullable', 'date', 'after_or_equal:today'],
            'subject_kind'         => ['nullable', 'in:borrower,member,guarantor'],
            'subject_customer_id'  => ['nullable', 'integer'],
            'loan_group_member_id' => ['nullable', 'integer'],
            'request_subject'      => ['nullable', 'string', 'max:64'],
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

        $subjectKind = $data['subject_kind'] ?? 'borrower';
        $subjectCustomerId = isset($data['subject_customer_id']) ? (int) $data['subject_customer_id'] : null;
        $loanGroupMemberId = isset($data['loan_group_member_id']) ? (int) $data['loan_group_member_id'] : null;

        if (! empty($data['request_subject'])) {
            if ($data['request_subject'] === 'borrower') {
                $subjectKind = 'borrower';
                $loanGroupMemberId = null;
            } elseif (str_starts_with($data['request_subject'], 'member:')) {
                $subjectKind = 'member';
                $loanGroupMemberId = (int) substr($data['request_subject'], strlen('member:'));
            } elseif (str_starts_with($data['request_subject'], 'guarantor:')) {
                $subjectKind = 'guarantor';
                $subjectCustomerId = (int) substr($data['request_subject'], strlen('guarantor:'));
            }
        }

        $dueAt = isset($data['due_at'])
            ? new \DateTimeImmutable($data['due_at'])
            : now()->addDays(app(\App\Services\UnderwritingSettingsService::class)->documentRequestDefaultDueDays());

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
                    'label'      => $labels[0],
                    'type'       => $data['type'],
                    'subject_kind' => $subjectKind,
                ]);

                return redirect()
                    ->route('admin.loan-applications.show', $loanApplication)
                    ->with('status', 'Document request sent to borrower.');
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
            'count'  => $created->count(),
            'labels' => $created->pluck('label')->all(),
            'type'   => $data['type'],
            'subject_kind' => $subjectKind,
        ]);

        return redirect()
            ->route('admin.loan-applications.show', $loanApplication)
            ->with('status', $created->count().' document requests sent to borrower.');
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
            'notes'      => $data['notes'] ?? null,
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
            'notes'      => $data['notes'],
        ]);

        return redirect()
            ->route('admin.loan-applications.show', $documentRequest->loan_application_id)
            ->with('status', 'Request rejected — borrower can re-upload.');
    }
}
