<x-admin.review-section id="customer-overview" title="Overview" subtitle="Quick readiness check for loan processing">
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        @foreach ($dossier['checklist'] as $item)
            @php
                $tone = match ($item['tone']) {
                    'emerald' => 'bg-emerald-50 ring-emerald-200 text-emerald-800',
                    'amber'   => 'bg-amber-50 ring-amber-200 text-amber-900',
                    'red'     => 'bg-red-50 ring-red-200 text-red-800',
                    default   => 'bg-gray-50 ring-gray-200 text-gray-700',
                };
            @endphp
            <div class="rounded-xl ring-1 px-4 py-3 {{ $tone }}">
                <p class="text-[10px] uppercase tracking-widest font-semibold opacity-80">{{ $item['label'] }}</p>
                <p class="text-sm font-semibold mt-1">{{ $item['detail'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid lg:grid-cols-2 gap-6">
        <div>
            <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">Identity snapshot</h3>
            <dl class="grid sm:grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div><dt class="text-xs text-gray-500">NIDA</dt><dd class="font-mono font-medium mt-0.5">{{ $customer->national_id ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">Date of birth</dt><dd class="font-medium mt-0.5">{{ optional($customer->date_of_birth)->format('d M Y') ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">Gender</dt><dd class="font-medium mt-0.5">{{ ucfirst($customer->gender ?? '—') }}</dd></div>
                <div><dt class="text-xs text-gray-500">Membership</dt><dd class="font-medium mt-0.5">{{ $customer->isMembershipActive() ? 'Active' : 'Inactive' }}</dd></div>
            </dl>
        </div>
        <div>
            <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">Loan officer actions</h3>
            <ul class="space-y-2 text-sm">
                <li>
                    <a href="#customer-documents" class="font-semibold text-amber-700 hover:text-amber-800">Upload or verify documents →</a>
                </li>
                <li>
                    <a href="{{ route('admin.loan-applications.create') }}?customer={{ $customer->id }}" class="font-semibold text-amber-700 hover:text-amber-800">Start loan application →</a>
                </li>
                @if ($dossier['applications']->where('current_stage', 'disbursement')->isNotEmpty())
                    <li>
                        <a href="{{ route('admin.loans.disbursement') }}" class="font-semibold text-emerald-700 hover:text-emerald-800">Disbursement queue →</a>
                    </li>
                @endif
            </ul>
        </div>
    </div>
</x-admin.review-section>
