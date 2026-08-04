<x-admin.review-section id="customer-overview" title="Overview" subtitle="Read-only readiness snapshot — borrower edits their own profile">
    @if ($dossier['profile_incomplete'] ?? false)
        <div class="mb-5 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-950">
            <p class="font-semibold">Incomplete profile ({{ $profile['percent'] }}%)</p>
            <p class="mt-1 text-amber-900/80">
                Missing:
                {{ collect($dossier['incomplete_sections'] ?? [])->pluck('label')->filter()->implode(', ') ?: 'required sections' }}.
                Staff cannot edit these fields here.
            </p>
        </div>
    @endif

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
            <div class="grid sm:grid-cols-2 gap-3 mb-4">
                <div class="rounded-xl overflow-hidden ring-1 ring-gray-200 bg-gray-50 aspect-[4/3] grid place-items-center">
                    @if ($dossier['face_photo_url'] ?? null)
                        <img src="{{ $dossier['face_photo_url'] }}" alt="Face" class="size-full object-cover">
                    @else
                        <span class="text-xs text-gray-400">No face photo</span>
                    @endif
                </div>
                <div class="rounded-xl overflow-hidden ring-1 ring-gray-200 bg-gray-50 aspect-[4/3] grid place-items-center">
                    @if ($dossier['id_photo_url'] ?? null)
                        <img src="{{ $dossier['id_photo_url'] }}" alt="ID" class="size-full object-cover">
                    @else
                        <span class="text-xs text-gray-400">No ID card image</span>
                    @endif
                </div>
            </div>
            <dl class="grid sm:grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div><dt class="text-xs text-gray-500">NIDA</dt><dd class="font-mono font-medium mt-0.5">{{ $customer->national_id ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">Date of birth</dt><dd class="font-medium mt-0.5">{{ optional($customer->date_of_birth)->format('d M Y') ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">Gender</dt><dd class="font-medium mt-0.5">{{ ucfirst($customer->gender ?? '—') }}</dd></div>
                <div><dt class="text-xs text-gray-500">Membership</dt><dd class="font-medium mt-0.5">{{ $customer->isMembershipActive() ? 'Active' : 'Inactive' }}</dd></div>
            </dl>
        </div>
        <div>
            <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">Staff notes</h3>
            <ul class="space-y-2 text-sm text-gray-600">
                <li>Profile fields are edited by the borrower in the app.</li>
                <li>KYC decisions and document requests happen on the loan application (screening).</li>
                <li>CRB refreshes only with an application fee payment.</li>
            </ul>
            @if ($dossier['applications']->isNotEmpty())
                <p class="mt-4">
                    <a href="#customer-applications" class="font-semibold text-brand hover:text-brand-light">View applications →</a>
                </p>
            @endif
        </div>
    </div>
</x-admin.review-section>
