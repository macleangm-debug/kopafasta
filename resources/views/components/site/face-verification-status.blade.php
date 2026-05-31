@props([
    'customer',
    'photos',
    'angles',
    'status' => null,
])

@php
    $statusKey = $customer->face_verification_status ?? 'incomplete';
    $statusBadge = match ($statusKey) {
        'verified' => ['Approved', 'bg-emerald-100 text-emerald-800'],
        'pending'  => ['Pending review', 'bg-sky-100 text-sky-800'],
        'rejected' => ['Rejected', 'bg-red-100 text-red-800'],
        default    => ['Incomplete', 'bg-amber-100 text-amber-800'],
    };
@endphp

<div class="bg-white rounded-2xl ring-1 ring-gray-200 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="font-semibold text-gray-900">Submitted face photos</h2>
            <p class="text-sm text-gray-500 mt-0.5">Review what you submitted for underwriting.</p>
        </div>
        <span class="text-xs font-semibold rounded-full px-3 py-1 {{ $statusBadge[1] }}">{{ $statusBadge[0] }}</span>
    </div>

    @if ($customer->face_rejection_notes && $statusKey === 'rejected')
        <div class="mx-5 mt-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">
            <p class="font-medium">Rejection reason</p>
            <p class="mt-1">{{ $customer->face_rejection_notes }}</p>
        </div>
    @endif

    <div class="p-5 grid sm:grid-cols-2 gap-4">
        @foreach ($angles as $key => $meta)
            @php $photo = $photos[$key] ?? null; @endphp
            <div class="rounded-xl ring-1 ring-gray-200 overflow-hidden">
                <div class="px-3 py-2 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
                    <p class="text-sm font-medium text-gray-900">{{ $meta['label'] ?? $key }}</p>
                    @if ($photo)
                        <span class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">{{ ucfirst(str_replace('_', ' ', $photo->status)) }}</span>
                    @endif
                </div>
                <div class="aspect-[4/5] bg-gray-100">
                    @if ($photo)
                        <a href="{{ asset('storage/'.$photo->file_path) }}" target="_blank" class="block w-full h-full">
                            <img src="{{ asset('storage/'.$photo->file_path) }}" alt="{{ $meta['label'] ?? $key }}"
                                 class="w-full h-full object-cover">
                        </a>
                    @else
                        <div class="w-full h-full flex items-center justify-center text-sm text-gray-400">Not submitted</div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @if ($statusKey === 'pending')
        <div class="px-5 pb-5">
            <p class="text-sm text-gray-600">Our underwriting team compares these live photos with your NIDA record. You will be notified once approved.</p>
        </div>
    @endif
</div>
