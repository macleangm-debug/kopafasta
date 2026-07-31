<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PartnerApplication;
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

    public function show(PartnerApplication $partnerApplication): View
    {
        $partnerApplication->load(['documents', 'partner', 'reviewer']);

        return view('admin.partner-applications.show', [
            'application' => $partnerApplication,
        ]);
    }

    public function update(Request $request, PartnerApplication $partnerApplication): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,approved,rejected'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
            'convert' => ['nullable', 'boolean'],
        ]);

        $partnerApplication->fill([
            'status' => $data['status'],
            'admin_notes' => $data['admin_notes'] ?? $partnerApplication->admin_notes,
        ]);

        if ($partnerApplication->isDirty('status')) {
            $partnerApplication->reviewed_by = Auth::id();
            $partnerApplication->reviewed_at = now();
        }

        $partnerApplication->save();

        $converted = null;
        if ($request->boolean('convert') && $partnerApplication->status === 'approved' && ! $partnerApplication->partner_id) {
            $converted = app(PartnerEnrollmentService::class)->convertToPartner(
                $partnerApplication->fresh('documents'),
                Auth::user(),
            );
        }

        $message = $converted
            ? 'Application approved and partner '.$converted->vendor_number.' created. Activation invite sent.'
            : 'Partner application updated.';

        return redirect()
            ->route('admin.partner-applications.show', $partnerApplication)
            ->with('status', $message);
    }
}
