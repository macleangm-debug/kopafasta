<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\CustomerGuarantor;
use App\Models\GuarantorInvitation;
use App\Services\GuarantorInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicGuarantorController extends Controller
{
    public function show(string $token): View|RedirectResponse
    {
        $invitation = GuarantorInvitation::query()
            ->where('token', $token)
            ->with(['borrower', 'application.product'])
            ->firstOrFail();

        if ($invitation->isExpired() && $invitation->isPending()) {
            $invitation->update(['status' => 'expired']);

            return view('site.guarantor.expired', compact('invitation'));
        }

        if (! $invitation->isPending()) {
            return view('site.guarantor.responded', compact('invitation'));
        }

        return view('site.guarantor.show', compact('invitation'));
    }

    public function accept(string $token, GuarantorInvitationService $service): RedirectResponse
    {
        $invitation = GuarantorInvitation::query()->where('token', $token)->firstOrFail();

        if (! $invitation->isPending() || $invitation->isExpired()) {
            return back()->with('error', 'This invitation is no longer active.');
        }

        if ($invitation->type === 'internal' && $invitation->guarantor_customer_id) {
            $link = $invitation->customerGuarantor;
            if ($link) {
                $service->approve($link);
            }
        } else {
            $invitation->update([
                'status'       => 'accepted',
                'responded_at' => now(),
            ]);
        }

        return redirect()
            ->route('site.guarantor.show', $token)
            ->with('status', 'Thank you. Your acceptance has been recorded.');
    }

    public function reject(Request $request, string $token, GuarantorInvitationService $service): RedirectResponse
    {
        $invitation = GuarantorInvitation::query()->where('token', $token)->firstOrFail();

        if (! $invitation->isPending() || $invitation->isExpired()) {
            return back()->with('error', 'This invitation is no longer active.');
        }

        $notes = $request->validate(['notes' => ['nullable', 'string', 'max:500']])['notes'] ?? null;

        if ($link = $invitation->customerGuarantor) {
            $service->reject($link, $notes);
        } else {
            $invitation->update([
                'status'         => 'rejected',
                'responded_at'   => now(),
                'response_notes' => $notes,
            ]);
        }

        return redirect()
            ->route('site.guarantor.show', $token)
            ->with('status', 'Invitation declined.');
    }
}
