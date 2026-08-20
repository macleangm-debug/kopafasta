<x-admin.layout
    title="Coverage request"
    heading="Partner needed{{ filled($region) ? ' in '.$region : '' }}"
    subheading="Partner support desk · {{ $application->application_number }} · {{ $application->customer?->full_name }}">
    <div class="space-y-6">
        <div class="rounded-2xl bg-amber-50 ring-1 ring-amber-200 px-5 py-4">
            <p class="text-sm font-semibold text-amber-950">
                Screening needs a {{ $categoryLabel }}{{ filled($region) ? ' covering '.$region : '' }}.
            </p>
            <p class="text-xs text-amber-900/80 mt-1">
                Check existing partners first. If one is already based in {{ $region ?: 'this region' }} or can take the work, add the region on their profile. Only enroll a new partner when nobody fits.
            </p>
            <a href="{{ route('admin.loan-applications.show', ['loan_application' => $application, 'workspace' => 'checklist', 'desk_phase' => 'security', 'open_group' => 'collateral']) }}#review-desk"
               class="inline-flex mt-2 text-xs font-semibold text-brand hover:underline">
                Open credit file →
            </a>
        </div>

        @include('admin.partners._support_duties', ['compact' => true])

        <section class="rounded-2xl bg-white ring-1 ring-brand/15 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-brand/10">
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Option 1</p>
                <h2 class="text-base font-bold text-gray-900 mt-0.5">Add {{ $region ?: 'the region' }} on an existing partner</h2>
                <p class="text-xs text-gray-500 mt-1">Partners based in {{ $region ?: 'the said region' }} are listed first. You can still extend coverage for someone who already works elsewhere.</p>
            </div>
            @if ($candidates === [])
                <p class="px-5 py-8 text-sm text-gray-600">No existing {{ $categoryLabel }}s to extend. Enroll a new partner below.</p>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach ($candidates as $row)
                        @php $partner = $row['partner']; @endphp
                        <li class="px-5 py-4 flex flex-wrap items-start justify-between gap-3 {{ ! empty($row['connected']) ? 'bg-emerald-50/60' : '' }}">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900">
                                    {{ $partner->name }}
                                    @if (($partner->status ?? '') !== 'active')
                                        <span class="ml-1 text-[10px] font-bold uppercase tracking-wide text-amber-800">{{ $partner->status }}</span>
                                    @endif
                                    @if (! empty($row['connected']))
                                        <span class="ml-1 text-[10px] font-bold uppercase tracking-wide text-emerald-800">In {{ $region }}</span>
                                    @endif
                                </p>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    {{ $partner->phone ?: 'No phone' }}
                                    · Coverage: {{ $row['coverage'] }}
                                </p>
                                @if (! empty($row['connection']))
                                    <p class="text-xs text-emerald-800 mt-1">{{ $row['connection'] }}</p>
                                @endif
                            </div>
                            <div class="flex flex-wrap items-center gap-2 shrink-0">
                                <a href="{{ route('admin.partners.edit', $partner) }}"
                                   class="text-xs font-semibold text-gray-600 hover:underline">Edit partner</a>
                                @if (! empty($row['can_add_region']))
                                    <form method="POST" action="{{ route('admin.partners.coverage-request.add-region', [$application, $partner]) }}">
                                        @csrf
                                        <button type="submit" class="inline-flex text-xs font-semibold text-brand bg-brand-gold hover:brightness-95 px-3 py-2 rounded-lg">
                                            Add {{ $region }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="rounded-2xl bg-white ring-1 ring-brand/15 shadow-sm px-5 py-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Option 2</p>
                <h2 class="text-base font-bold text-gray-900 mt-0.5">Enroll a new {{ $categoryLabel }}</h2>
                <p class="text-xs text-gray-500 mt-1">Use this when nobody on the list can cover {{ $region ?: 'this region' }}. The last step of that form is portal access (invite / activate / draft), not a staff alert.</p>
            </div>
            <a href="{{ $createUrl }}" class="inline-flex text-sm font-semibold text-gray-700 bg-white ring-1 ring-gray-200 hover:bg-gray-50 px-4 py-2.5 rounded-xl">
                Open new-partner form
            </a>
        </section>
    </div>
</x-admin.layout>
