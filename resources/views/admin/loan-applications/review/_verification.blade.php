@php
    $customer = $review['customer'];
    $photos = $review['face_photos'];
    $angles = $review['face_angles'];
    $nidaPhotoPath = $review['nida_photo_path'];
    $idDocs = $review['id_documents'] ?? collect();
    $nidaFront = $idDocs->get('national_id_front');
    $nidaBack = $idDocs->get('national_id_back');
    $altCodes = ['passport', 'voter_id', 'driving_license', 'other_id'];
    $altDocs = collect($altCodes)->mapWithKeys(fn ($code) => [$code => $idDocs->get($code)])->filter();
    $altTypes = collect($review['alternate_id_types'] ?? []);
    $altLabels = [
        'passport' => 'Passport',
        'voter_id' => 'Voter ID',
        'driving_license' => 'Driving licence',
        'other_id' => 'Other government ID',
    ];
    $frontFace = $photos['front'] ?? null;
    $idCompareDoc = $nidaFront ?? $altDocs->first();
    $idCompareLabel = $nidaFront ? 'NIDA card front' : ($idCompareDoc?->documentType?->name ?? 'Identification card');
    $idComparePath = $idCompareDoc?->file_path ?? $nidaPhotoPath;
    $idCompareIsBureau = ! $idCompareDoc && $nidaPhotoPath;
@endphp

@php $embedded = $embedded ?? false; @endphp

@if ($embedded)
<div class="space-y-5">
@else
<x-admin.review-section id="review-verification" title="Face & identity verification" subtitle="Compare front face capture with the identification card picture">
@endif
    <div class="mb-5 rounded-lg bg-amber-50 ring-1 ring-amber-200 px-4 py-3">
        <p class="text-sm font-semibold text-amber-950">Primary check — front face vs ID card</p>
        <p class="text-xs text-amber-900/90 mt-1">Confirm the person in the <strong>front face capture</strong> is the same person on the <strong>identification card picture</strong>. Other angles are supporting evidence only.</p>
    </div>

    <div class="mb-6 rounded-2xl ring-2 ring-brand/20 bg-white overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 bg-brand-muted/30 flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm font-bold text-gray-900">Side-by-side comparison</p>
            <p class="text-xs text-gray-500 font-mono">{{ $customer->national_id ?: 'NIDA number not on file' }}</p>
        </div>
        <div class="grid md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-gray-100">
            <div class="p-4">
                <p class="text-[10px] uppercase tracking-widest font-semibold text-brand mb-2">1. Front face capture</p>
                @if ($frontFace)
                    <button type="button"
                            onclick="window.kfOpenDocumentPreview(@js(asset('storage/'.$frontFace->file_path)), 'Front face', 'image')"
                            class="block w-full text-left group">
                        <img src="{{ asset('storage/'.$frontFace->file_path) }}" alt="Front face"
                             class="w-full max-h-72 object-cover rounded-xl ring-1 ring-gray-200 group-hover:ring-amber-400 transition cursor-zoom-in">
                        <span class="text-xs font-semibold text-amber-700 mt-2 inline-block">Enlarge front face (popup)</span>
                    </button>
                @else
                    <div class="h-48 grid place-items-center rounded-xl bg-gray-50 text-sm text-gray-400 ring-1 ring-gray-200">Front face not uploaded</div>
                @endif
            </div>
            <div class="p-4">
                <p class="text-[10px] uppercase tracking-widest font-semibold text-brand mb-2">2. Identification card</p>
                @if ($idComparePath)
                    <button type="button"
                            onclick="window.kfOpenDocumentPreview(@js(asset('storage/'.$idComparePath)), @js($idCompareIsBureau ? 'NIDA bureau photo' : $idCompareLabel), 'image')"
                            class="block w-full text-left group">
                        <img src="{{ asset('storage/'.$idComparePath) }}" alt="{{ $idCompareLabel }}"
                             class="w-full max-h-72 object-cover rounded-xl ring-1 ring-gray-200 group-hover:ring-amber-400 transition cursor-zoom-in">
                        <span class="text-xs font-semibold text-amber-700 mt-2 inline-block">
                            Enlarge {{ $idCompareIsBureau ? 'bureau photo (backup)' : $idCompareLabel }} (popup)
                        </span>
                    </button>
                    @if ($idCompareIsBureau)
                        <p class="mt-2 text-[11px] text-amber-800">No card image on file — showing bureau photo as backup.</p>
                    @endif
                @else
                    <div class="h-48 grid place-items-center rounded-xl bg-gray-50 text-sm text-gray-400 ring-1 ring-gray-200">No ID card image on file</div>
                @endif
                @if ($nidaBack)
                    <button type="button"
                            onclick="window.kfOpenDocumentPreview(@js(asset('storage/'.$nidaBack->file_path)), 'NIDA card back', 'image')"
                            class="mt-2 text-xs font-semibold text-brand hover:underline">Also view card back</button>
                @endif
            </div>
        </div>
    </div>

    <div class="mb-5 grid sm:grid-cols-2 gap-4">
        <div class="rounded-lg ring-1 ring-gray-200 p-4">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest">Capture progress</p>
            <p class="text-2xl font-bold text-gray-900 mt-2">{{ $review['face_progress']['uploaded'] }}/{{ $review['face_progress']['required'] }}</p>
            <p class="text-xs text-gray-500 mt-2">
                Status:
                <span class="font-semibold">{{ display_label($customer->face_verification_status, 'face_verification_status') ?: 'Not started' }}</span>
            </p>
        </div>
        <div class="rounded-lg bg-sky-50/80 ring-1 ring-sky-100 p-4">
            <p class="text-xs font-semibold text-sky-900 mb-1">Checklist</p>
            <ul class="space-y-1">
                <li class="text-xs text-sky-900 flex items-start gap-2"><span class="text-sky-600">✓</span><span>Same person on front face and ID card</span></li>
                @foreach (config('underwriting_document_guidance.face_verification.items', []) as $item)
                    <li class="text-xs text-sky-900 flex items-start gap-2"><span class="text-sky-600">✓</span><span>{{ $item }}</span></li>
                @endforeach
            </ul>
        </div>
    </div>

    <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-3">Other angles (supporting)</p>
    <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
        @foreach ($angles as $key => $meta)
            @continue($key === 'front')
            @php $photo = $photos[$key] ?? null; @endphp
            <div class="rounded-xl ring-1 ring-gray-200 overflow-hidden">
                <div class="px-3 py-2 border-b border-gray-100 flex items-center justify-between gap-2">
                    <p class="text-xs font-semibold text-gray-800">{{ $meta['label'] }}</p>
                    @if ($photo)
                        <span class="text-[10px] font-semibold rounded-full px-2 py-0.5 bg-gray-100 text-gray-600">
                            {{ display_label($photo->status, 'document_status') }}
                        </span>
                    @endif
                </div>
                <div class="p-3">
                    @if ($photo)
                        <button type="button"
                                onclick="window.kfOpenDocumentPreview(@js(asset('storage/'.$photo->file_path)), @js($meta['label']), 'image')"
                                class="block w-full text-left group">
                            <img src="{{ asset('storage/'.$photo->file_path) }}" alt="{{ $meta['label'] }}"
                                 class="w-full rounded-lg object-cover max-h-40 mt-1 ring-1 ring-gray-200 group-hover:ring-amber-400 transition cursor-zoom-in">
                            <span class="text-xs font-semibold text-amber-700 mt-2 inline-block">Preview {{ $meta['label'] }}</span>
                        </button>
                    @else
                        <div class="h-32 grid place-items-center text-xs text-gray-400 bg-gray-50 rounded-lg">Not uploaded</div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @if (in_array($customer->face_verification_status ?? '', ['pending', 'verified', 'rejected', 'incomplete', 'revision_required'], true))
        <div class="mt-5 flex flex-wrap gap-2">
            @if (($customer->face_verification_status ?? '') === 'pending')
                <form method="POST" action="{{ route('admin.face-verifications.approve', $customer) }}">
                    @csrf
                    <button type="submit" class="text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-4 py-2 rounded-lg">
                        Approve face photos
                    </button>
                </form>
                <button type="button"
                        data-open-dialog="reject-face-{{ $customer->id }}"
                        class="text-sm font-semibold text-white bg-red-600 hover:bg-red-700 px-4 py-2 rounded-lg">
                    Reject face photos
                </button>
                <dialog id="reject-face-{{ $customer->id }}" class="rounded-xl shadow-xl ring-1 ring-gray-200 w-full max-w-md p-0 backdrop:bg-black/40 open:flex open:flex-col">
                    <form method="POST" action="{{ route('admin.face-verifications.reject', $customer) }}" class="p-6 space-y-4">
                        @csrf
                        <h4 class="font-semibold text-gray-900">Reject face photos</h4>
                        <textarea name="notes" required rows="3" maxlength="500" placeholder="Reason shown to borrower"
                                  class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200"></textarea>
                        <div class="flex justify-end gap-2">
                            <button type="button" data-close-dialog="reject-face-{{ $customer->id }}" class="px-4 py-2 text-sm text-gray-600">Cancel</button>
                            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-semibold text-sm px-4 py-2 rounded-lg">Confirm reject</button>
                        </div>
                    </form>
                </dialog>
            @endif
            <button type="button"
                    data-open-dialog="retake-face-{{ $customer->id }}"
                    class="text-sm font-semibold text-amber-900 bg-amber-100 hover:bg-amber-200 px-4 py-2 rounded-lg">
                Request clearer photos
            </button>
            <dialog id="retake-face-{{ $customer->id }}" class="rounded-xl shadow-xl ring-1 ring-gray-200 w-full max-w-md p-0 backdrop:bg-black/40 open:flex open:flex-col">
                <form method="POST" action="{{ route('admin.face-verifications.request-retake', $customer) }}" class="p-6 space-y-4">
                    @csrf
                    <h4 class="font-semibold text-gray-900">Request clearer face photos</h4>
                    <p class="text-xs text-gray-500">Unlocks retake for the borrower without a hard reject. Use when images are blurry or incomplete.</p>
                    <textarea name="notes" rows="3" maxlength="500" placeholder="Optional guidance for the borrower"
                              class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200"></textarea>
                    <div class="flex justify-end gap-2">
                        <button type="button" data-close-dialog="retake-face-{{ $customer->id }}" class="px-4 py-2 text-sm text-gray-600">Cancel</button>
                        <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-4 py-2 rounded-lg">Send request</button>
                    </div>
                </form>
            </dialog>
        </div>
    @endif
@if ($embedded)
</div>
@else
</x-admin.review-section>
@endif
