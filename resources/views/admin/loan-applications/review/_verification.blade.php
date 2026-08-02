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
@endphp

<x-admin.review-section id="review-verification" title="Face & identity verification" subtitle="Match live captures to the NIDA card (or alternate ID) after application submission">
    <div class="mb-5 rounded-lg bg-sky-50/80 ring-1 ring-sky-100 px-4 py-3">
        <p class="text-[10px] uppercase tracking-widest font-semibold text-sky-800 mb-2">What to verify</p>
        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <p class="text-xs font-semibold text-sky-900 mb-1">Signature check</p>
                <ul class="space-y-1">
                    @foreach (config('underwriting_document_guidance.signature_check.items', []) as $item)
                        <li class="text-xs text-sky-900 flex items-start gap-2"><span class="text-sky-600 shrink-0">✓</span><span>{{ $item }}</span></li>
                    @endforeach
                </ul>
            </div>
            <div>
                <p class="text-xs font-semibold text-sky-900 mb-1">Face verification</p>
                <ul class="space-y-1">
                    @foreach (config('underwriting_document_guidance.face_verification.items', []) as $item)
                        <li class="text-xs text-sky-900 flex items-start gap-2"><span class="text-sky-600 shrink-0">✓</span><span>{{ $item }}</span></li>
                    @endforeach
                    <li class="text-xs text-sky-900 flex items-start gap-2"><span class="text-sky-600 shrink-0">✓</span><span>Match live face to NIDA card front (preferred) or bureau photo / alternate ID.</span></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="mb-6 grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="rounded-lg ring-1 ring-gray-200 p-4">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest">NIDA number</p>
            <p class="mt-2 font-mono text-sm font-semibold text-gray-900">{{ $customer->national_id ?: '—' }}</p>
            @if ($customer->no_physical_nida_card)
                <p class="mt-2 text-xs text-amber-800 bg-amber-50 ring-1 ring-amber-200 rounded-lg px-2.5 py-1.5">No physical NIDA card — compare using alternate ID below.</p>
                @if ($altTypes->isNotEmpty())
                    <p class="mt-2 text-xs text-gray-600">Declared: {{ $altTypes->map(fn ($t) => $altLabels[$t] ?? $t)->implode(', ') }}</p>
                @endif
                @if (filled($review['alternate_id_notes'] ?? null))
                    <p class="mt-1 text-xs text-gray-500">{{ $review['alternate_id_notes'] }}</p>
                @endif
            @endif
        </div>
        <div class="rounded-lg ring-1 ring-gray-200 p-4">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest">NIDA card / ID photo</p>
            @if ($nidaFront)
                <button type="button"
                        onclick="window.kfOpenDocumentPreview(@js(asset('storage/'.$nidaFront->file_path)), 'NIDA card front', 'image')"
                        class="mt-3 block w-full text-left group">
                    <img src="{{ asset('storage/'.$nidaFront->file_path) }}" alt="NIDA card front"
                         class="max-h-40 w-full rounded-lg object-cover ring-1 ring-gray-200 group-hover:ring-amber-400 transition cursor-zoom-in">
                    <span class="text-xs font-semibold text-amber-700 mt-2 inline-block">Preview NIDA card front</span>
                </button>
            @elseif ($altDocs->isNotEmpty())
                @php $firstAlt = $altDocs->first(); @endphp
                <button type="button"
                        onclick="window.kfOpenDocumentPreview(@js(asset('storage/'.$firstAlt->file_path)), @js($firstAlt->documentType?->name ?? 'Alternate ID'), 'image')"
                        class="mt-3 block w-full text-left group">
                    <img src="{{ asset('storage/'.$firstAlt->file_path) }}" alt="Alternate ID"
                         class="max-h-40 w-full rounded-lg object-cover ring-1 ring-gray-200 group-hover:ring-amber-400 transition cursor-zoom-in">
                    <span class="text-xs font-semibold text-amber-700 mt-2 inline-block">Preview {{ $firstAlt->documentType?->name ?? 'alternate ID' }}</span>
                </button>
            @elseif ($nidaPhotoPath)
                <button type="button"
                        onclick="window.kfOpenDocumentPreview(@js(asset('storage/'.$nidaPhotoPath)), 'NIDA bureau photo', 'image')"
                        class="mt-3 block w-full text-left group">
                    <img src="{{ asset('storage/'.$nidaPhotoPath) }}" alt="NIDA bureau photo"
                         class="max-h-40 w-full rounded-lg object-cover ring-1 ring-gray-200 group-hover:ring-amber-400 transition cursor-zoom-in">
                    <span class="text-xs font-semibold text-amber-700 mt-2 inline-block">Preview bureau NIDA photo</span>
                </button>
            @else
                <p class="text-sm text-gray-500 mt-3">No NIDA card or alternate ID image on file yet.</p>
            @endif
            @if ($nidaBack)
                <button type="button"
                        onclick="window.kfOpenDocumentPreview(@js(asset('storage/'.$nidaBack->file_path)), 'NIDA card back', 'image')"
                        class="mt-2 text-xs font-semibold text-brand hover:underline">Also view card back</button>
            @endif
        </div>
        <div class="rounded-lg ring-1 ring-gray-200 p-4">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest">Capture progress</p>
            <p class="text-2xl font-bold text-gray-900 mt-2">{{ $review['face_progress']['uploaded'] }}/{{ $review['face_progress']['required'] }}</p>
            <p class="text-sm text-gray-600 mt-1">angles uploaded</p>
            <p class="text-xs text-gray-500 mt-3">
                Status:
                <span class="font-semibold">{{ display_label($customer->face_verification_status, 'face_verification_status') ?: 'Not started' }}</span>
            </p>
        </div>
    </div>

    @if ($altDocs->count() > 1 || ($nidaFront && $altDocs->isNotEmpty()))
        <div class="mb-6">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-3">Alternate ID uploads</p>
            <div class="grid sm:grid-cols-2 md:grid-cols-4 gap-3">
                @foreach ($altDocs as $code => $doc)
                    <button type="button"
                            onclick="window.kfOpenDocumentPreview(@js(asset('storage/'.$doc->file_path)), @js($doc->documentType?->name ?? $code), 'image')"
                            class="rounded-lg ring-1 ring-gray-200 p-3 text-left hover:ring-amber-400 transition">
                        <img src="{{ asset('storage/'.$doc->file_path) }}" alt="{{ $doc->documentType?->name }}"
                             class="w-full max-h-28 rounded object-cover">
                        <span class="mt-2 block text-xs font-semibold text-gray-800">{{ $doc->documentType?->name ?? $code }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid md:grid-cols-2 xl:grid-cols-4 gap-4">
        @foreach ($angles as $key => $meta)
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
                        <div>
                            <button type="button"
                                    onclick="window.kfOpenDocumentPreview(@js(asset('storage/'.$photo->file_path)), @js($meta['label']), 'image')"
                                    class="block w-full text-left group">
                                <img src="{{ asset('storage/'.$photo->file_path) }}" alt="{{ $meta['label'] }}"
                                     class="w-full rounded-lg object-cover max-h-40 mt-1 ring-1 ring-gray-200 group-hover:ring-amber-400 transition cursor-zoom-in">
                                <span class="text-xs font-semibold text-amber-700 mt-2 inline-block">Preview {{ $meta['label'] }}</span>
                            </button>
                        </div>
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
</x-admin.review-section>
