<x-site.borrower-layout title="Face verification — Kopafasta" active="kyc">

    <div class="max-w-4xl">
        <p class="text-xs uppercase tracking-widest text-amber-600 mb-1">Identity verification</p>
        <h1 class="text-2xl sm:text-3xl font-bold mb-1">Face verification</h1>
        <p class="text-sm text-gray-500 mb-6">Capture four photos before you can apply for a loan. Use your phone camera in good lighting.</p>

        @if (session('status'))
            <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <div class="mb-6 rounded-2xl bg-white ring-1 ring-gray-200 p-5">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <div>
                    <p class="font-semibold text-gray-900">Verification status</p>
                    <p class="text-sm text-gray-500 mt-1">{{ $progress['uploaded'] }} of {{ $progress['required'] }} photos captured</p>
                </div>
                <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $status[1] }}">{{ $status[0] }}</span>
            </div>

            @if ($customer->face_verification_status === 'rejected' && $customer->face_rejection_notes)
                <div class="mb-4 rounded-lg bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">
                    <p class="font-medium">Previous submission rejected</p>
                    <p class="mt-1">{{ $customer->face_rejection_notes }}</p>
                </div>
            @endif

            <div class="h-2.5 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full bg-amber-500 transition-all" style="width: {{ $progress['percent'] }}%"></div>
            </div>

            @if ($customer->face_verification_status === 'verified')
                <p class="text-sm text-emerald-700 mt-4">Your face verification is approved. You can start a loan application.</p>
            @elseif ($customer->face_verification_status === 'pending')
                <p class="text-sm text-sky-700 mt-4">All photos submitted. Our team is reviewing them — usually within 24 hours.</p>
            @endif
        </div>

        @php
            $locked = in_array($customer->face_verification_status, ['pending', 'verified'], true);
        @endphp

        <div class="grid lg:grid-cols-2 gap-6">
            @foreach ($angles as $key => $meta)
                @php
                    $photo = $photos[$key] ?? null;
                    $existingUrl = $photo ? asset('storage/'.$photo->file_path) : null;
                @endphp
                <x-site.face-capture
                    :angle="$key"
                    :label="$meta['label']"
                    :instruction="$meta['instruction']"
                    :upload-url="route('site.borrower.face-verification.store', ['angle' => $key])"
                    :existing-url="$existingUrl"
                    :disabled="$locked"
                />
            @endforeach
        </div>

        <div class="mt-8 flex flex-wrap gap-3">
            <a href="{{ route('site.borrower.profile', ['section' => 'kyc']) }}" class="inline-flex bg-white ring-1 ring-gray-200 hover:bg-gray-50 text-gray-700 font-semibold px-5 py-2.5 rounded-full text-sm">
                Back to KYC profile
            </a>
            @if ($customer->face_verification_status === 'verified')
                <a href="{{ route('site.borrower.apply') }}" class="inline-flex bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
                    Apply for a loan →
                </a>
            @endif
        </div>
    </div>

    @stack('scripts')
</x-site.borrower-layout>
