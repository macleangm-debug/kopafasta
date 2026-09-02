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
        abort_if($loanApplication->isClosed(), 403, 'This application is closed and can only be viewed.');

        if ($request->boolean('dispatch_queued')) {
            $request->validate([
                'confirmed' => ['accepted'],
                'dispatch_queued' => ['accepted'],
            ]);
            $sent = $service->dispatchQueued($loanApplication);
            $this->auditAdmin('admin.loan_applications.document_requests_dispatched', $loanApplication, [
                'count' => $sent->count(),
                'labels' => $sent->pluck('label')->all(),
            ]);

            return redirect()
                ->route('admin.loan-applications.guided-screening', $loanApplication)
                ->with('status', $sent->count() === 1
                    ? '1 document request sent.'
                    : $sent->count().' document requests sent.');
        }

        $data = $request->validate([
            'type' => ['required', 'in:document,clarification'],
            'label' => ['nullable', 'string', 'max:120'],
            'labels' => ['nullable', 'array'],
            'labels.*' => ['string', 'max:120'],
            'presets' => ['nullable', 'array'],
            'presets.*' => ['string', 'max:120'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'due_at' => ['nullable', 'date', 'after_or_equal:today'],
            'open_item' => ['nullable', 'string', 'max:80'],
            'gate' => ['nullable', 'string', 'max:40'],
            'request_reason' => ['nullable', 'string', 'max:500'],
            'subject_kind' => ['nullable', 'in:borrower,member,guarantor'],
            'subject_customer_id' => ['nullable', 'integer'],
            'loan_group_member_id' => ['nullable', 'integer'],
            'request_subject' => ['nullable', 'string', 'max:64'],
            'review_person' => ['nullable', 'in:borrower,member,guarantor'],
            'review_m' => ['nullable', 'integer'],
            'review_g' => ['nullable', 'integer'],
            'ask_members' => ['sometimes', 'boolean'],
            'confirmed' => ['sometimes', 'accepted'],
            'intent' => ['nullable', 'in:collateral,documents'],
            'return_workspace' => ['nullable', 'in:checklist,profiles,guided'],
            'return_tab' => ['nullable', 'string', 'max:40'],
            'person' => ['nullable', 'in:borrower,member,guarantor'],
            'dispatch_queued' => ['sometimes', 'boolean'],
        ]);

        if (in_array($data['intent'] ?? '', ['collateral', 'documents'], true) && ! $request->boolean('confirmed')) {
            return back()->withErrors(['confirmed' => 'Review the request, then send it.'])->withInput();
        }

        $labels = collect($data['labels'] ?? [])
            ->merge($data['presets'] ?? [])
            ->push($data['label'] ?? null)
            ->map(fn ($label) => trim((string) $label))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($labels === []) {
            if ($request->boolean('ask_members')) {
                $labels = [ApplicationDocumentRequestService::COLLATERAL_PRESET_LABELS[0]];
            } else {
                return back()->withErrors(['label' => 'Select or enter at least one document to request.'])->withInput();
            }
        }

        [$subjectKind, $subjectCustomerId, $loanGroupMemberId] = $this->resolvePostedSubject(
            $loanApplication,
            $data,
        );

        $dueAt = now()->addDays(app(UnderwritingSettingsService::class)->documentRequestDefaultDueDays());

        try {
            if ($request->boolean('ask_members')) {
                $created = $service->createManyForActiveGroupMembers(
                    $loanApplication,
                    $request->user(),
                    $labels,
                    $data['instructions'] ?? null,
                    $dueAt,
                    $data['type'],
                );

                $this->auditAdmin('admin.loan_applications.document_requests_created', $loanApplication, [
                    'count' => $created->count(),
                    'labels' => $created->pluck('label')->all(),
                    'type' => $data['type'],
                    'subject_kind' => 'member',
                    'ask_members' => true,
                ]);

                return redirect()
                    ->route('admin.loan-applications.show', $this->reviewReturnParams(
                        $request,
                        $loanApplication,
                        'borrower',
                        null,
                    ))
                    ->with('status', $created->count().' collateral requests sent to group members.')
                    ->withFragment($this->returnFragment($request));
            }

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
                    dispatch: $request->input('return_workspace') !== 'guided',
                );

                $this->attachRequestContext($docRequest, $data);

                $this->auditAdmin('admin.loan_applications.document_request_created', $loanApplication, [
                    'request_id' => $docRequest->id,
                    'label' => $labels[0],
                    'type' => $data['type'],
                    'subject_kind' => $subjectKind,
                ]);

                if ($request->input('return_workspace') === 'guided') {
                    return redirect()
                        ->route('admin.loan-applications.guided-screening', $loanApplication)
                        ->with('status', 'Added to this review. Continue reviewing, then send all requests together.');
                }

                return redirect()
                    ->route('admin.loan-applications.show', $this->reviewReturnParams(
                        $request,
                        $loanApplication,
                        $subjectKind,
                        $loanGroupMemberId,
                    ))
                    ->with('status', 'Document request sent.')
                    ->withFragment($this->returnFragment($request));
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

        if ($created->isEmpty()) {
            return back()->with(
                'error',
                'Those items are already requested and waiting. Open the existing request instead of sending again.',
            );
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
            ->withFragment($this->returnFragment($request));
    }

    private function returnFragment(Request $request): string
    {
        if ($request->input('return_workspace') === 'profiles') {
            return $request->input('return_tab') === 'documents' ? 'borrower-file' : 'collateral-requests';
        }

        return 'checklist-documents';
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
        $returnWorkspace = (string) $request->input('return_workspace', '');
        $returnTab = (string) $request->input('return_tab', '');

        if ($returnWorkspace === 'profiles') {
            return array_filter([
                'loan_application' => $loanApplication,
                'workspace' => 'profiles',
                'tab' => $returnTab !== '' ? $returnTab : 'collateral',
                'person' => $person,
                'review_person' => $person,
                'review_m' => $person === 'member'
                    ? ($loanGroupMemberId ?: $request->input('review_m'))
                    : null,
                'review_g' => $person === 'guarantor' ? $request->input('review_g') : null,
            ], fn ($value) => $value !== null && $value !== '' && $value !== 0 && $value !== '0');
        }

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
        abort_if($documentRequest->application?->isClosed(), 403, 'This application is closed and can only be viewed.');

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if ($documentRequest->status !== 'uploaded') {
            return back()->with('error', 'Only uploaded requests can be marked satisfied.');
        }

        if ($service->borrowerActionKind($documentRequest) === 'collateral') {
            $application = $documentRequest->application;
            abort_unless($application, 404);

            return redirect()
                ->to($service->screeningReviewUrl($documentRequest, $application))
                ->with('error', 'Collateral requests clear when every collateral checklist item is reviewed.');
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
        abort_if($documentRequest->application?->isClosed(), 403, 'This application is closed and can only be viewed.');

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

    public function cancelMany(
        Request $request,
        LoanApplication $loanApplication,
        ApplicationDocumentRequestService $service,
    ): RedirectResponse {
        abort_if($loanApplication->isClosed(), 403, 'This application is closed and can only be viewed.');
        abort_unless(auth()->user()?->hasPermission('applications.request_documents'), 403);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'confirmed' => ['accepted'],
            'reason' => ['nullable', 'string', 'max:200'],
            'return_workspace' => ['nullable', 'in:checklist,profiles,guided'],
            'return_tab' => ['nullable', 'string', 'max:40'],
            'review_person' => ['nullable', 'in:borrower,member,guarantor'],
            'review_m' => ['nullable', 'integer'],
            'review_g' => ['nullable', 'integer'],
        ]);

        $rows = LoanApplicationDocumentRequest::query()
            ->where('loan_application_id', $loanApplication->id)
            ->whereIn('id', $data['ids'])
            ->get();

        $cancelled = 0;
        foreach ($rows as $row) {
            if ($row->status !== 'pending') {
                continue;
            }
            $service->cancelPending($row, $request->user(), $data['reason'] ?? null);
            $cancelled++;
            $this->auditAdmin('admin.loan_applications.document_request_cancelled', $loanApplication, [
                'request_id' => $row->id,
                'label' => $row->label,
                'reason' => $data['reason'] ?? null,
            ]);
        }

        if ($cancelled === 0) {
            return back()->with('error', 'Only a waiting request can be withdrawn.');
        }

        $person = (string) ($data['review_person'] ?? 'borrower');

        return redirect()
            ->route('admin.loan-applications.show', $this->reviewReturnParams(
                $request,
                $loanApplication,
                $person,
                isset($data['review_m']) ? (int) $data['review_m'] : null,
            ))
            ->with('status', $cancelled === 1 ? 'Request withdrawn.' : $cancelled.' requests withdrawn.')
            ->withFragment($this->returnFragment($request));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function attachRequestContext(LoanApplicationDocumentRequest $docRequest, array $data): void
    {
        $item = trim((string) ($data['open_item'] ?? ''));
        $gate = trim((string) ($data['gate'] ?? ''));
        $reason = trim((string) ($data['request_reason'] ?? ''));
        if ($item === '' && $gate === '' && $reason === '') {
            return;
        }
        $docRequest->forceFill(array_filter([
            'checklist_item' => $item !== '' ? $item : null,
            'gate' => $gate !== '' ? $gate : null,
            'request_reason' => $reason !== '' ? $reason : null,
        ], fn ($value) => $value !== null))->save();
    }
}
