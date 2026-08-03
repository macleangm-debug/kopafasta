<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PartnerApplication;
use App\Services\PartnerApplicationReviewService;
use App\Services\PartnerEnrollmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PartnerApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $type = $request->query('type');
        $status = $request->query('status');

        $applications = PartnerApplication::query()
            ->with(['documents', 'partner'])
            ->when($type === 'affiliate', fn ($q) => $q->where(function ($inner) {
                $inner->where('type', 'affiliate')->orWhere('partner_category', 'affiliate');
            }))
            ->when($type === 'service', fn ($q) => $q->where(function ($inner) {
                $inner->where('type', 'service')->orWhere(function ($nested) {
                    $nested->where('type', '!=', 'affiliate')
                        ->where(fn ($x) => $x->whereNull('partner_category')->orWhere('partner_category', '!=', 'affiliate'));
                });
            }))
            ->when($type === 'collection', fn ($q) => $q->where('partner_category', 'debt_collector'))
            ->when(filled($status), fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.partner-applications.index', [
            'applications' => $applications,
            'filterType' => $type,
            'filterStatus' => $status,
        ]);
    }

    public function show(PartnerApplication $partnerApplication, PartnerApplicationReviewService $reviewService): View
    {
        $partnerApplication->load(['documents', 'partner', 'reviewer']);
        $review = $reviewService->dossier($partnerApplication);

        return view('admin.partner-applications.show', [
            'application' => $partnerApplication,
            'review'      => $review,
            'anomalies'   => app(\App\Services\PartnerEnrollmentAnomalyService::class)
                ->forApplication($partnerApplication, $review),
        ]);
    }

    public function update(Request $request, PartnerApplication $partnerApplication): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,approved,rejected,needs_info'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
            'rejection_reason' => ['nullable', 'string', 'max:60'],
        ]);

        $notes = $data['admin_notes'] ?? $partnerApplication->admin_notes;
        if ($data['status'] === 'rejected' && filled($data['rejection_reason'] ?? null)) {
            $reasonLabel = PartnerApplicationReviewService::REJECTION_REASON_CODES[$data['rejection_reason']] ?? $data['rejection_reason'];
            $notes = trim('['.$reasonLabel.'] '.($notes ?? ''));
        }

        $partnerApplication->fill([
            'status' => $data['status'],
            'admin_notes' => $notes,
        ]);

        if ($partnerApplication->isDirty('status')) {
            $partnerApplication->reviewed_by = Auth::id();
            $partnerApplication->reviewed_at = now();
        }

        $partnerApplication->save();

        $converted = null;
        if ($partnerApplication->status === 'approved' && ! $partnerApplication->partner_id) {
            $converted = app(PartnerEnrollmentService::class)->convertToPartner(
                $partnerApplication->fresh('documents'),
                Auth::user(),
            );
        }

        $message = $converted
            ? 'Partner approved. Partner code '.$converted->vendor_number.' is ready — they can activate via Track status / Activate account (no SMS).'
            : match ($partnerApplication->status) {
                'approved'    => 'Partner application approved.',
                'rejected'    => 'Partner application rejected.',
                'needs_info'  => 'Applicant will see your notes on the tracking page and can resubmit.',
                default       => 'Partner application updated.',
            };

        return redirect()
            ->route('admin.partner-applications.show', $partnerApplication->fresh('partner'))
            ->with('status', $message);
    }
}
