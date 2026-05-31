<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\FaceVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FaceVerificationController extends Controller
{
    public function index(): View
    {
        $pending = Customer::query()
            ->where('face_verification_status', 'pending')
            ->with('kyc')
            ->latest('updated_at')
            ->paginate(25);

        $recent = Customer::query()
            ->whereIn('face_verification_status', ['verified', 'rejected'])
            ->latest('updated_at')
            ->limit(10)
            ->get();

        return view('admin.face-verifications.index', compact('pending', 'recent'));
    }

    public function show(Customer $customer, FaceVerificationService $faces): View
    {
        $photos = $faces->latestByAngle($customer);
        $progress = $faces->progress($customer);

        return view('admin.face-verifications.show', compact('customer', 'photos', 'progress'));
    }

    public function approve(Customer $customer, FaceVerificationService $faces): RedirectResponse
    {
        if ($customer->face_verification_status !== 'pending') {
            return back()->with('error', 'This customer is not awaiting face verification review.');
        }

        $faces->approve($customer, auth()->user());

        $name = trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''));

        return redirect()
            ->route('admin.face-verifications.index')
            ->with('status', "Face verification approved for {$name}.");
    }

    public function reject(Request $request, Customer $customer, FaceVerificationService $faces): RedirectResponse
    {
        $data = $request->validate([
            'notes' => ['required', 'string', 'max:500'],
        ]);

        if ($customer->face_verification_status !== 'pending') {
            return back()->with('error', 'This customer is not awaiting face verification review.');
        }

        $faces->reject($customer, auth()->user(), $data['notes']);

        return redirect()
            ->route('admin.face-verifications.index')
            ->with('status', 'Face verification rejected. The borrower can re-upload photos.');
    }
}
