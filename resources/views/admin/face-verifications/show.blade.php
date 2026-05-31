@php
    $name = trim($customer->first_name.' '.$customer->last_name);
    $angles = config('face_verification.angles', []);
@endphp
<x-admin.layout :title="'Face review — '.$name" :heading="$name" subheading="Face verification review">
    <div class="mb-4">
        <a href="{{ route('admin.face-verifications.index') }}" class="text-sm text-amber-700 hover:underline">← Back to queue</a>
    </div>

    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-5 mb-6">
        <dl class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
            <div><dt class="text-gray-500">Phone</dt><dd class="font-medium">{{ $customer->phone ?? '—' }}</dd></div>
            <div><dt class="text-gray-500">NIDA</dt><dd class="font-medium font-mono">{{ $customer->national_id ?? '—' }}</dd></div>
            <div><dt class="text-gray-500">Status</dt><dd class="font-medium capitalize">{{ str_replace('_', ' ', $customer->face_verification_status) }}</dd></div>
            <div><dt class="text-gray-500">Photos</dt><dd class="font-medium">{{ $progress['uploaded'] }}/{{ $progress['required'] }}</dd></div>
        </dl>
    </div>

    <div class="grid md:grid-cols-2 gap-6 mb-8">
        @foreach ($angles as $key => $meta)
            @php $photo = $photos[$key] ?? null; @endphp
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                    <p class="font-semibold text-sm">{{ $meta['label'] }}</p>
                    @if ($photo)
                        <span class="text-xs rounded-full px-2 py-0.5 bg-gray-100 text-gray-600">{{ ucfirst(str_replace('_', ' ', $photo->status)) }}</span>
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

    @if ($customer->face_verification_status === 'pending')
        <div class="flex flex-wrap gap-3">
            <form method="POST" action="{{ route('admin.face-verifications.approve', $customer) }}">
                @csrf
                <button class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm px-5 py-2.5 rounded-lg">Approve face verification</button>
            </form>

            <form method="POST" action="{{ route('admin.face-verifications.reject', $customer) }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Rejection notes (shown to borrower)</label>
                    <input name="notes" required maxlength="500" class="rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm w-80" placeholder="e.g. NIDA not visible in holding photo">
                </div>
                <button class="bg-red-600 hover:bg-red-700 text-white font-semibold text-sm px-5 py-2.5 rounded-lg">Reject</button>
            </form>
        </div>
    @endif
</x-admin.layout>
