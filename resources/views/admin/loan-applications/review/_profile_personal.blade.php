@php
    $customer = $review['customer'];
@endphp

<div class="space-y-5">
    <div class="rounded-2xl ring-1 ring-brand/10 bg-brand-muted/20 p-5">
        <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Personal information</p>
                <p class="text-sm font-bold text-gray-900 mt-0.5">{{ $customer->full_name }}</p>
            </div>
            <a href="{{ route('admin.customers.show', $customer) }}" class="text-xs font-semibold text-brand hover:underline">Full customer record →</a>
        </div>
        @include('admin.loan-applications.review._field-grid', [
            'fields' => [
                ['label' => 'Date of birth', 'value' => optional($customer->date_of_birth)->format('d M Y') ?: '—'],
                ['label' => 'Gender', 'value' => filled($customer->gender) ? ucfirst($customer->gender) : '—'],
                ['label' => 'NIDA number', 'value' => $customer->national_id, 'class' => 'font-mono'],
                ['label' => 'Phone', 'value' => $customer->phone],
                ['label' => 'Email', 'value' => $customer->email],
                ['label' => 'Profile completion', 'value' => ($review['profile']['percent'] ?? 0).'%'],
                ['label' => 'Membership', 'value' => $customer->isMembershipActive() ? 'Active' : 'Inactive / expired'],
            ],
        ])
        @if (! $customer->date_of_birth || ! filled($customer->gender))
            <p class="mt-3 text-xs text-amber-800 bg-amber-50 ring-1 ring-amber-200 rounded-lg px-3 py-2">
                Date of birth and gender should come from registration / NIDA. Missing values need a profile update before committee can rely on them.
            </p>
        @endif
        <div class="mt-4 flex flex-wrap gap-2">
            <x-admin.badge :value="$customer->nida_verification_status ?? 'unverified'" group="nida_verification_status"
                :map="['verified'=>'bg-emerald-100 text-emerald-800','name_mismatch'=>'bg-amber-100 text-amber-800','multihit'=>'bg-amber-100 text-amber-800','unverified'=>'bg-gray-100 text-gray-700']" />
            <x-admin.badge :value="$customer->face_verification_status ?? 'none'" group="face_verification_status"
                :map="['verified'=>'bg-emerald-100 text-emerald-800','pending'=>'bg-amber-100 text-amber-800','rejected'=>'bg-red-100 text-red-800']" />
        </div>
    </div>

    <details class="group rounded-2xl ring-1 ring-gray-200 bg-white overflow-hidden" open>
        <summary class="cursor-pointer list-none px-5 py-3.5 flex items-center justify-between gap-3 border-b border-gray-100 [&::-webkit-details-marker]:hidden">
            <div>
                <p class="text-sm font-semibold text-gray-900">Next of kin</p>
                <p class="text-xs text-gray-500">Emergency contact on file</p>
            </div>
            <svg class="size-4 text-gray-400 transition group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
        </summary>
        <div class="p-5">
            @include('admin.loan-applications.review._field-grid', [
                'fields' => [
                    ['label' => 'Name', 'value' => $customer->nok_name],
                    ['label' => 'Relationship', 'value' => kin_relationship_label($customer->nok_relationship)],
                    ['label' => 'Phone', 'value' => $customer->nok_phone],
                ],
            ])
        </div>
    </details>

    <details class="group rounded-2xl ring-1 ring-gray-200 bg-white overflow-hidden">
        <summary class="cursor-pointer list-none px-5 py-3.5 flex items-center justify-between gap-3 border-b border-gray-100 [&::-webkit-details-marker]:hidden">
            <div>
                <p class="text-sm font-semibold text-gray-900">Face & identity</p>
                <p class="text-xs text-gray-500">Compare face captures with the ID card on the Face tab</p>
            </div>
        </summary>
        <div class="p-5">
            <a href="{{ route('admin.loan-applications.show', array_filter([
                    'loan_application' => $record,
                    'tab' => 'face',
                    'person' => request('person', 'borrower'),
                    'g' => request('g'),
                ])) }}#borrower-file"
               class="inline-flex text-sm font-semibold text-brand hover:underline">
                Open Face tab →
            </a>
            <div class="mt-3 flex flex-wrap gap-2">
                <x-admin.badge :value="$customer->face_verification_status ?? 'none'" group="face_verification_status"
                    :map="['verified'=>'bg-emerald-100 text-emerald-800','pending'=>'bg-amber-100 text-amber-800','rejected'=>'bg-red-100 text-red-800']" />
            </div>
        </div>
    </details>
</div>
