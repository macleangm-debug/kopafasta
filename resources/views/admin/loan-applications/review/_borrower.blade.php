@php
    $customer = $review['customer'];
@endphp

<x-admin.review-section id="review-borrower" title="Borrower profile" subtitle="Identity, residence, activity and next of kin">
    <div class="grid lg:grid-cols-2 gap-6">
        <div>
            <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">Identity</h3>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div><dt class="text-xs text-gray-500">NIDA number</dt><dd class="font-medium font-mono mt-0.5">{{ $customer->national_id ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">Full name</dt><dd class="font-medium mt-0.5">{{ $customer->full_name }}</dd></div>
                <div><dt class="text-xs text-gray-500">Date of birth</dt><dd class="font-medium mt-0.5">{{ optional($customer->date_of_birth)->format('d M Y') ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">Gender</dt><dd class="font-medium mt-0.5">{{ ucfirst($customer->gender ?? '—') }}</dd></div>
                <div><dt class="text-xs text-gray-500">Phone</dt><dd class="font-medium mt-0.5">{{ $customer->phone ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">Email</dt><dd class="font-medium mt-0.5">{{ $customer->email ?? '—' }}</dd></div>
            </dl>
        </div>

        <div>
            <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">Verification snapshot</h3>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div>
                    <dt class="text-xs text-gray-500">NIDA status</dt>
                    <dd class="mt-1 flex flex-wrap items-center gap-2">
                        <x-admin.badge :value="$customer->nida_verification_status ?? 'unverified'" group="nida_verification_status"
                            :map="['verified'=>'bg-emerald-100 text-emerald-800','name_mismatch'=>'bg-amber-100 text-amber-800','multihit'=>'bg-amber-100 text-amber-800','unverified'=>'bg-gray-100 text-gray-700']" />
                        @if ($customer->no_physical_nida_card)
                            <span class="inline-flex text-[10px] font-semibold rounded-full px-2 py-0.5 bg-amber-100 text-amber-900">No physical card photos</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Face verification</dt>
                    <dd class="mt-1">
                        <x-admin.badge :value="$customer->face_verification_status ?? 'none'" group="face_verification_status"
                            :map="['verified'=>'bg-emerald-100 text-emerald-800','pending'=>'bg-amber-100 text-amber-800','rejected'=>'bg-red-100 text-red-800']" />
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Profile completion</dt>
                    <dd class="font-semibold mt-0.5">{{ $review['profile']['percent'] }}%</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Membership</dt>
                    <dd class="font-medium mt-0.5">{{ $customer->isMembershipActive() ? 'Active' : 'Inactive / expired' }}</dd>
                </div>
            </dl>
            <a href="{{ route('admin.customers.show', $customer) }}" class="inline-flex mt-4 text-xs font-semibold text-brand hover:text-brand-light">
                Open full customer record →
            </a>
        </div>

        <div>
            <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">Residence</h3>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div><dt class="text-xs text-gray-500">Region</dt><dd class="font-medium mt-0.5">{{ $customer->region ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">District</dt><dd class="font-medium mt-0.5">{{ $customer->district ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">Ward</dt><dd class="font-medium mt-0.5">{{ $customer->ward ?? '—' }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-xs text-gray-500">Street / address</dt><dd class="font-medium mt-0.5">{{ $customer->street ?? $customer->address ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">LGA officer</dt><dd class="font-medium mt-0.5">{{ $customer->lga_officer_name ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">Officer position</dt><dd class="font-medium mt-0.5">{{ $customer->lga_officer_position ?? '—' }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-xs text-gray-500">Officer phone</dt><dd class="font-medium mt-0.5">{{ $customer->lga_officer_phone ?? '—' }}</dd></div>
            </dl>
        </div>

        <div>
            <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">Activity & income</h3>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div><dt class="text-xs text-gray-500">Activity type</dt><dd class="font-medium mt-0.5">{{ $review['activity_label'] ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">Income range</dt><dd class="font-medium mt-0.5">{{ $review['income_label'] ?? '—' }}</dd></div>
                @if ($review['business_name'])
                    <div class="sm:col-span-2"><dt class="text-xs text-gray-500">Business / employer</dt><dd class="font-medium mt-0.5">{{ $review['business_name'] }}</dd></div>
                @endif
                <div class="sm:col-span-2"><dt class="text-xs text-gray-500">Loan purpose</dt>
                    <dd class="font-medium mt-0.5">
                        {{ format_loan_purpose_display(
                            $record->purpose,
                            data_get($record->screening_payload, 'purpose_other'),
                            $record->screening_payload
                        ) }}
                    </dd>
                </div>
            </dl>
        </div>

        <div class="lg:col-span-2">
            <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">Next of kin</h3>
            <dl class="grid grid-cols-1 sm:grid-cols-3 gap-x-4 gap-y-3 text-sm">
                <div><dt class="text-xs text-gray-500">Name</dt><dd class="font-medium mt-0.5">{{ $customer->nok_name ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">Relationship</dt><dd class="font-medium mt-0.5">{{ kin_relationship_label($customer->nok_relationship) ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">Phone</dt><dd class="font-medium mt-0.5">{{ $customer->nok_phone ?? '—' }}</dd></div>
            </dl>
        </div>
    </div>
</x-admin.review-section>
