@php
    $customer = $review['customer'];
    $photos = $review['face_photos'];
    $angles = $review['face_angles'];
    $nidaPhotoPath = $review['nida_photo_path'];
@endphp

<x-admin.review-section id="review-verification" title="Face & identity verification" subtitle="Compare live captures with NIDA reference photo">
    <x-slot:actions>
        @if (($customer->face_verification_status ?? '') === 'pending')
            <a href="{{ route('admin.face-verifications.show', $customer) }}"
               class="inline-flex items-center text-xs font-semibold text-white bg-amber-600 hover:bg-amber-700 px-3 py-1.5 rounded-lg">
                Open face review queue
            </a>
        @endif
    </x-slot:actions>

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
                </ul>
            </div>
        </div>
    </div>

    <div class="mb-6 grid sm:grid-cols-2 gap-4">
        <div class="rounded-lg ring-1 ring-gray-200 p-4">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest">NIDA reference</p>
            @if ($nidaPhotoPath)
                <div class="mt-3">
                    <x-admin.document-preview
                        :url="asset('storage/'.$nidaPhotoPath)"
                        label="Preview NIDA photo" />
                    <img src="{{ asset('storage/'.$nidaPhotoPath) }}" alt="NIDA photo" class="max-h-48 rounded-lg object-cover ring-1 ring-gray-200 mt-3">
                </div>
            @else
                <p class="text-sm text-gray-500 mt-3">NIDA database photo not stored yet.</p>
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
                            <x-admin.document-preview
                                :url="asset('storage/'.$photo->file_path)"
                                :label="'Preview '.$meta['label']"
                                variant="link" />
                            <img src="{{ asset('storage/'.$photo->file_path) }}" alt="{{ $meta['label'] }}" class="w-full rounded-lg object-cover max-h-40 mt-2">
                        </div>
                    @else
                        <div class="h-32 grid place-items-center text-xs text-gray-400 bg-gray-50 rounded-lg">Not uploaded</div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @if (($customer->face_verification_status ?? '') === 'pending')
        <div class="mt-5 flex flex-wrap gap-2">
            <form method="POST" action="{{ route('admin.face-verifications.approve', $customer) }}">
                @csrf
                <button type="submit" class="text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-4 py-2 rounded-lg">
                    Approve face verification
                </button>
            </form>
            <button type="button"
                    data-open-dialog="reject-face-{{ $customer->id }}"
                    class="text-sm font-semibold text-white bg-red-600 hover:bg-red-700 px-4 py-2 rounded-lg">
                Reject face verification
            </button>
            <dialog id="reject-face-{{ $customer->id }}" class="rounded-xl shadow-xl ring-1 ring-gray-200 w-full max-w-md p-0 backdrop:bg-black/40 open:flex open:flex-col">
                <form method="POST" action="{{ route('admin.face-verifications.reject', $customer) }}" class="p-6 space-y-4">
                    @csrf
                    <h4 class="font-semibold text-gray-900">Reject face verification</h4>
                    <textarea name="notes" required rows="3" maxlength="500" placeholder="Reason shown to borrower"
                              class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200"></textarea>
                    <div class="flex justify-end gap-2">
                        <button type="button" data-close-dialog="reject-face-{{ $customer->id }}" class="px-4 py-2 text-sm text-gray-600">Cancel</button>
                        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-semibold text-sm px-4 py-2 rounded-lg">Confirm reject</button>
                    </div>
                </form>
            </dialog>
        </div>
    @endif
</x-admin.review-section>
