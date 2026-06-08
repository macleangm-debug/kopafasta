@props(['customer'])

@php
    $nidaUploaded = app(\App\Services\ProfileValidationService::class)->nationalIdUploadsComplete($customer);
    $nidaVerified = app(\App\Services\NidaVerificationService::class)->isVerified($customer);
    $faceStatus = $customer->face_verification_status ?? 'incomplete';
    $faceComplete = $faceStatus === 'verified';
    $facePending = $faceStatus === 'pending';
@endphp

<div class="mb-6 rounded-2xl border border-gray-200 bg-white p-5">
    <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold mb-3">{{ __('borrower.kyc_tab.verification_status') }}</p>
    <ul class="space-y-2.5">
        <li class="flex items-center justify-between gap-3 text-sm">
            <span class="text-gray-700">{{ __('borrower.profile.nida_front') }}</span>
            @if ($nidaUploaded)
                <span class="inline-flex items-center gap-1 text-emerald-700 font-semibold">✓ {{ __('borrower.profile.verification_uploaded') }}</span>
            @else
                <span class="inline-flex items-center gap-1 text-amber-700 font-semibold">⚠ {{ __('borrower.profile.verification_required') }}</span>
            @endif
        </li>
        <li class="flex items-center justify-between gap-3 text-sm">
            <span class="text-gray-700">{{ __('borrower.nida.title') }}</span>
            @if ($nidaVerified)
                <span class="inline-flex items-center gap-1 text-emerald-700 font-semibold">✓ {{ __('borrower.kyc_tab.status.verified') }}</span>
            @else
                <span class="inline-flex items-center gap-1 text-amber-700 font-semibold">⚠ {{ __('borrower.profile.verification_required') }}</span>
            @endif
        </li>
        <li class="flex items-center justify-between gap-3 text-sm">
            <span class="text-gray-700">{{ __('borrower.nida.face_title') }}</span>
            @if ($faceComplete)
                <span class="inline-flex items-center gap-1 text-emerald-700 font-semibold">✓ {{ __('borrower.profile.verification_complete') }}</span>
            @elseif ($facePending)
                <span class="inline-flex items-center gap-1 text-sky-700 font-semibold">⏳ {{ __('borrower.kyc_tab.status.in_review') }}</span>
            @else
                <a href="{{ route('site.borrower.face-verification') }}" class="inline-flex items-center gap-1 text-amber-700 font-semibold hover:underline">
                    ⚠ {{ __('borrower.profile.face_verification_required') }}
                </a>
            @endif
        </li>
    </ul>
</div>
