<x-admin.review-section id="customer-personal" title="Personal information" subtitle="Identity and contact — read-only (borrower edits in the app)">
    <dl class="grid md:grid-cols-2 gap-x-6 gap-y-4 text-sm">
        <div><dt class="text-xs uppercase tracking-wider text-gray-500">First name</dt><dd class="font-medium mt-0.5">{{ $customer->first_name ?: '—' }}</dd></div>
        <div><dt class="text-xs uppercase tracking-wider text-gray-500">Last name</dt><dd class="font-medium mt-0.5">{{ $customer->last_name ?: '—' }}</dd></div>
        <div><dt class="text-xs uppercase tracking-wider text-gray-500">National ID (NIDA)</dt><dd class="font-medium mt-0.5">{{ $customer->national_id ?: '—' }}</dd></div>
        <div><dt class="text-xs uppercase tracking-wider text-gray-500">Date of birth</dt><dd class="font-medium mt-0.5">{{ optional($customer->date_of_birth)->format('d M Y') ?? '—' }}</dd></div>
        <div><dt class="text-xs uppercase tracking-wider text-gray-500">Gender</dt><dd class="font-medium mt-0.5">{{ ucfirst($customer->gender ?? '—') }}</dd></div>
        <div><dt class="text-xs uppercase tracking-wider text-gray-500">Phone</dt><dd class="font-medium mt-0.5">{{ $customer->phone ?: '—' }}</dd></div>
        <div class="md:col-span-2"><dt class="text-xs uppercase tracking-wider text-gray-500">Email</dt><dd class="font-medium mt-0.5">{{ $customer->email ?: '—' }}</dd></div>
    </dl>
    @if ($customer->identity_locked)
        <p class="mt-4 text-xs text-amber-700 bg-amber-50 ring-1 ring-amber-200 rounded-lg px-3 py-2">
            Identity is locked after NIDA verification — name and NIDA cannot be changed.
        </p>
    @endif
</x-admin.review-section>
