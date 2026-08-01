<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\FaceVerificationService;
use App\Services\NotificationService;
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
        $nidaPhotoPath = $customer->kyc?->payload['nida_verification']['photo_path'] ?? null;

        return view('admin.face-verifications.show', compact('customer', 'photos', 'progress', 'nidaPhotoPath'));
    }

    public function approve(Customer $customer, FaceVerificationService $faces, NotificationService $notify): RedirectResponse
    {
        if ($customer->face_verification_status !== 'pending') {
            return back()->with('error', 'This customer is not awaiting face verification review.');
        }

        $faces->approve($customer, auth()->user());

        if ($customer->phone) {
            $notify->sendSms(
                $customer->phone,
                'Your face verification has been approved. You can now continue with loan applications on Kopafasta.',
                $customer,
                'face_verification_approved',
            );
        }

        $name = trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''));

        return redirect()
            ->route('admin.face-verifications.index')
            ->with('status', "Face verification approved for {$name}.");
    }

    public function reject(Request $request, Customer $customer, FaceVerificationService $faces, NotificationService $notify): RedirectResponse
    {
        $data = $request->validate([
            'notes' => ['required', 'string', 'max:500'],
        ]);

        if ($customer->face_verification_status !== 'pending') {
            return back()->with('error', 'This customer is not awaiting face verification review.');
        }

        $faces->reject($customer, auth()->user(), $data['notes']);

        if ($customer->phone) {
            $notify->sendSms(
                $customer->phone,
                'Your face verification was not approved. Reason: '.$data['notes'].'. Please recapture your photos in the borrower portal.',
                $customer,
                'face_verification_rejected',
            );
        }

        return redirect()
            ->route('admin.face-verifications.index')
            ->with('status', 'Face verification rejected. The borrower can re-upload photos.');
    }

    public function requestRetake(Request $request, Customer $customer, FaceVerificationService $faces, NotificationService $notify): RedirectResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $notes = trim((string) ($data['notes'] ?? '')) ?: 'Please retake clearer face photos.';

        $faces->beginRetake($customer, true);
        $customer->update(['face_rejection_notes' => $notes]);

        $notify->notifyInApp(
            $customer,
            $notes,
            'loan_updates',
            'face_retake_requested',
            __('borrower.nida.face_retake_requested_title'),
            route('site.borrower.face-verification'),
            __('borrower.nida.face_retake_requested_cta'),
            [
                'title_key' => 'borrower.nida.face_retake_requested_title',
                'body_key'  => 'borrower.nida.face_retake_requested_body',
                'params'    => ['notes' => $notes],
            ],
        );

        if ($customer->phone) {
            $notify->sendSms(
                $customer->phone,
                'Please retake your face photos on Kopafasta. '.$notes,
                $customer,
                'face_retake_requested',
            );
        }

        return back()->with('status', 'Borrower asked to retake face photos.');
    }
}
