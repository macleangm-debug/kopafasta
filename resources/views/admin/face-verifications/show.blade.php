@php
    $name = trim($customer->first_name.' '.$customer->last_name);
    $angles = config('face_verification.angles', []);
@endphp
<x-admin.layout :title="'Face review — '.$name" heading="" subheading="">
    <x-admin.letterhead
        kicker="Face verification"
        :title="$name"
        subtitle="Face verification review">
        <x-slot:actions>
            <a href="{{ route('admin.face-verifications.index') }}" class="inline-flex items-center text-xs font-semibold text-brand bg-brand-gold hover:brightness-95 px-3 py-1.5 rounded-lg">Back to queue</a>
        </x-slot:actions>
    </x-admin.letterhead>

<div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-5 mb-6">
        <dl class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
            <div><dt class="text-gray-500">Phone</dt><dd class="font-medium">{{ $customer->phone ?? '—' }}</dd></div>
            <div><dt class="text-gray-500">NIDA</dt><dd class="font-medium font-mono">{{ $customer->national_id ?? '—' }}</dd></div>
            <div><dt class="text-gray-500">Status</dt><dd class="font-medium">{{ display_label($customer->face_verification_status, 'face_verification_status') }}</dd></div>
            <div><dt class="text-gray-500">Photos</dt><dd class="font-medium">{{ $progress['uploaded'] }}/{{ $progress['required'] }}</dd></div>
        </dl>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-5 mb-6">
        <h3 class="text-sm font-semibold text-gray-900 mb-3">NIDA reference photo</h3>
        @if ($nidaPhotoPath ?? null)
            <a href="{{ asset('storage/'.$nidaPhotoPath) }}" target="_blank">
                <img src="{{ asset('storage/'.$nidaPhotoPath) }}" alt="NIDA photo" class="max-h-72 rounded-lg object-cover ring-1 ring-gray-200">
            </a>
            <p class="text-xs text-gray-500 mt-2">Compare live captures with this NIDA database photo.</p>
        @else
            <p class="text-sm text-gray-500">NIDA photo not available yet. Compare live photos with the NIDA holding capture and verified identity details.</p>
        @endif
    </div>

    <div class="grid md:grid-cols-2 gap-6 mb-8">
        @foreach ($angles as $key => $meta)
            @php $photo = $photos[$key] ?? null; @endphp
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                    <p class="font-semibold text-sm">{{ $meta['label'] }}</p>
                    @if ($photo)
                        <span class="text-xs rounded-full px-2 py-0.5 bg-gray-100 text-gray-600">{{ display_label($photo->status, 'document_status') }}</span>
                    @endif
                </div>
                <div class="p-4">
                    @if ($photo)
                        <a href="{{ asset('storage/'.$photo->file_path) }}" target="_blank">
                            <img src="{{ asset('storage/'.$photo->file_path) }}" alt="{{ $meta['label'] }}" class="w-full rounded-lg object-cover max-h-72">
                        </a>
                    @else
                        <p class="text-sm text-gray-500 py-12 text-center">Not uploaded</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @if (in_array($customer->face_verification_status, ['pending', 'verified', 'rejected', 'incomplete', 'revision_required'], true))
        <div class="flex flex-wrap gap-3" x-data="{ rejectOpen: false, retakeOpen: false }">
            @if ($customer->face_verification_status === 'pending')
                <form method="POST" action="{{ route('admin.face-verifications.approve', $customer) }}">
                    @csrf
                    <button class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm px-5 py-2.5 rounded-lg">Approve face photos</button>
                </form>

                <button type="button" @click="rejectOpen = true" class="bg-red-600 hover:bg-red-700 text-white font-semibold text-sm px-5 py-2.5 rounded-lg">
                    Reject face photos
                </button>

                <div x-show="rejectOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-black/40" @click="rejectOpen = false"></div>
                    <div class="relative bg-white rounded-xl shadow-xl ring-1 ring-gray-200 w-full max-w-md p-6">
                        <h3 class="text-lg font-semibold text-gray-900">Reject face photos</h3>
                        <p class="text-sm text-gray-600 mt-1">The borrower will receive your reason by SMS.</p>
                        <form method="POST" action="{{ route('admin.face-verifications.reject', $customer) }}" class="mt-4 space-y-4">
                            @csrf
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Rejection reason</label>
                                <textarea name="notes" required maxlength="500" rows="4" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm" placeholder="e.g. NIDA not visible in holding photo"></textarea>
                            </div>
                            <div class="flex justify-end gap-2">
                                <button type="button" @click="rejectOpen = false" class="px-4 py-2 text-sm font-semibold text-gray-600 hover:text-gray-900">Cancel</button>
                                <button class="bg-red-600 hover:bg-red-700 text-white font-semibold text-sm px-5 py-2.5 rounded-lg">Confirm rejection</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            <button type="button" @click="retakeOpen = true" class="bg-amber-100 hover:bg-amber-200 text-amber-900 font-semibold text-sm px-5 py-2.5 rounded-lg">
                Request clearer photos
            </button>
            <div x-show="retakeOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="retakeOpen = false"></div>
                <div class="relative bg-white rounded-xl shadow-xl ring-1 ring-gray-200 w-full max-w-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900">Request clearer photos</h3>
                    <p class="text-sm text-gray-600 mt-1">Unlocks retake without a hard reject.</p>
                    <form method="POST" action="{{ route('admin.face-verifications.request-retake', $customer) }}" class="mt-4 space-y-4">
                        @csrf
                        <textarea name="notes" maxlength="500" rows="3" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm" placeholder="Optional guidance"></textarea>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="retakeOpen = false" class="px-4 py-2 text-sm font-semibold text-gray-600">Cancel</button>
                            <button class="bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-5 py-2.5 rounded-lg">Send request</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</x-admin.layout>
