<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PartnerApplication;
use App\Services\PartnerApplicationDecisionService;
use App\Services\PartnerApplicationReviewService;
use App\Services\PartnerMatchResolutionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PartnerApplicationController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $type = $request->query('type');
        $status = $request->query('status');

        if ($status === 'approved') {
            return redirect()->route('admin.partners.index');
        }

        $applications = PartnerApplication::query()
            ->inQueue()
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
        $partnerMatches = app(PartnerMatchResolutionService::class)->matchesFor($partnerApplication);

        $performance = null;
        $partner = $partnerApplication->partner;
        if ($partner && $partner->category === 'affiliate') {
            $performance = app(\App\Services\AffiliateService::class)->stats($partner);
        }

        return view('admin.partner-applications.show', [
            'application' => $partnerApplication,
            'review' => $review,
            'requestCatalog' => app(PartnerApplicationDecisionService::class)->requestCatalog(),
            'existingDocumentOptions' => app(PartnerApplicationDecisionService::class)
                ->existingDocumentOptions($partnerApplication),
            'anomalies' => app(\App\Services\PartnerEnrollmentAnomalyService::class)
                ->forApplication($partnerApplication, $review),
            'partnerMatches' => $partnerMatches,
            'performance' => $performance,
        ]);
    }

    public function update(Request $request, PartnerApplication $partnerApplication): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,approved,rejected,needs_info'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
            'rejection_reason' => ['nullable', 'string', 'max:60'],
            'request_kind' => ['nullable', 'in:document,information'],
            'request_type' => ['nullable', 'string', 'max:60'],
            'request_mode' => ['nullable', 'in:new,replace'],
            'request_other_label' => ['nullable', 'string', 'max:120'],
            'request_explanation' => ['nullable', 'string', 'max:2000'],
            'replace_reason' => ['nullable', 'string', 'max:60'],
            'replace_reason_other' => ['nullable', 'string', 'max:200'],
        ]);

        try {
            $result = app(PartnerApplicationDecisionService::class)->execute($partnerApplication, $data);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->route('admin.partner-applications.show', $partnerApplication)
                ->withInput()
                ->withErrors($e->validator ?? $e->errors());
        }

        $application = $result['application'];
        $partner = $result['partner'];

        // Stay on canonical Partner/Application 360 — do not bounce to the old partner detail.
        if ($application->status === 'approved' && $partner) {
            return redirect()
                ->route('admin.partner-applications.show', $application)
                ->with('status', $result['message']);
        }

        return redirect()
            ->route('admin.partner-applications.show', $application)
            ->with('status', $result['message']);
    }

    public function resolveMatch(Request $request, PartnerApplication $partnerApplication): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:link,keep_separate'],
            'partner_id' => ['required', 'integer'],
            'confirm_despite_conflicts' => ['nullable', 'boolean'],
        ]);

        try {
            app(PartnerMatchResolutionService::class)->resolve($partnerApplication, [
                'decision' => $data['decision'],
                'partner_id' => (int) $data['partner_id'],
                'confirm_despite_conflicts' => (bool) ($data['confirm_despite_conflicts'] ?? false),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->route('admin.partner-applications.show', $partnerApplication)
                ->withInput()
                ->withErrors($e->validator ?? $e->errors());
        }

        $label = $data['decision'] === 'link'
            ? 'Match recorded: link to existing Partner on Approve.'
            : 'Match recorded: different people. Approval will not link to that Partner.';

        return redirect()
            ->route('admin.partner-applications.show', $partnerApplication)
            ->with('status', $label);
    }
}
