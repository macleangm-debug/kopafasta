<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDocumentRequest;
use App\Services\ApplicationDocumentRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LoanApplicationDocumentRequestController extends Controller
{
    public function store(
        Request $request,
        LoanApplication $loanApplication,
        ApplicationDocumentRequestService $service,
    ): RedirectResponse {
        $data = $request->validate([
            'type'         => ['required', 'in:document,clarification'],
            'label'        => ['required', 'string', 'max:120'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'due_at'       => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $service->create(
            $loanApplication,
            $request->user(),
            $data['label'],
            $data['instructions'] ?? null,
            isset($data['due_at']) ? new \DateTimeImmutable($data['due_at']) : null,
            $data['type'],
        );

        return redirect()
            ->route('admin.loan-applications.show', $loanApplication)
            ->with('status', 'Document request sent to borrower.');
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

        return redirect()
            ->route('admin.loan-applications.show', $documentRequest->loan_application_id)
            ->with('status', 'Request rejected — borrower can re-upload.');
    }
}
