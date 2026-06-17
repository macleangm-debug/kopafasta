<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PartnerApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PartnerApplicationController extends Controller
{
    public function index(): View
    {
        $applications = PartnerApplication::query()
            ->where('type', 'affiliate')
            ->latest()
            ->paginate(25);

        return view('admin.partner-applications.index', compact('applications'));
    }

    public function update(Request $request, PartnerApplication $partnerApplication): RedirectResponse
    {
        $data = $request->validate([
            'status'      => ['required', 'in:pending,approved,rejected'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $partnerApplication->fill($data);

        if ($partnerApplication->isDirty('status')) {
            $partnerApplication->reviewed_by = Auth::id();
            $partnerApplication->reviewed_at = now();
        }

        $partnerApplication->save();

        return back()->with('status', 'Affiliate application updated.');
    }
}
