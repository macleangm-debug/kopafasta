{{-- Shared Leader / Member / Guarantor switcher — sits ABOVE Review checklist / Profiles / Decision. --}}
@php
    $switcherWorkspace = $workspace ?? request('workspace', 'checklist');
    $deskPerson = request('review_person', request('person', 'borrower'));
    if (! in_array($deskPerson, ['borrower', 'guarantor', 'member'], true)) {
        $deskPerson = 'borrower';
    }
    $deskG = (int) request('review_g', request('g', 0)) ?: null;
    $deskM = (int) request('review_m', request('m', 0)) ?: null;

    $deskSubjects = app(\App\Services\ScreeningChecklistService::class)->deskViewModel(
        $record,
        $review,
        $groupReview ?? null,
        auth()->user(),
        $deskPerson,
        $deskG,
        $deskM,
    )['subjects'] ?? [];

    $personSwitcherUrl = function (array $s) use ($record, $switcherWorkspace) {
        $person = (string) ($s['person'] ?? 'borrower');
        $g = $s['g'] ?? null;
        $m = $s['m'] ?? null;

        return route('admin.loan-applications.show', array_filter([
            'loan_application' => $record,
            'workspace' => $switcherWorkspace,
            'person' => $person,
            'tab' => $switcherWorkspace === 'profiles' ? (request('tab') ?: 'personal') : null,
            'g' => $g,
            'm' => $m,
            'review_person' => $person,
            'review_g' => $g,
            'review_m' => $m,
        ], fn ($v) => $v !== null && $v !== '')).'#credit-workspace';
    };

    $activeKey = match ($deskPerson) {
        'guarantor' => 'guarantor:'.(int) $deskG,
        'member' => 'member:'.(int) $deskM,
        default => 'borrower',
    };
@endphp

@if (count($deskSubjects) > 1)
    <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm px-4 py-3 mb-3">
        <p class="text-[10px] uppercase tracking-[0.18em] text-brand font-bold mb-2">Who you are reviewing</p>
        <div class="flex gap-2 overflow-x-auto" role="tablist" aria-label="Loan subjects">
            @foreach ($deskSubjects as $s)
                @php
                    $sKey = (string) ($s['key'] ?? '');
                    $isActive = $sKey === $activeKey
                        || ($deskPerson === ($s['person'] ?? '') && empty($s['g']) && empty($s['m']) && $deskPerson === 'borrower');
                    if (($s['person'] ?? '') === 'guarantor') {
                        $isActive = $deskPerson === 'guarantor' && (int) ($s['g'] ?? 0) === (int) $deskG;
                    }
                    if (($s['person'] ?? '') === 'member') {
                        $isActive = $deskPerson === 'member' && (int) ($s['m'] ?? 0) === (int) $deskM;
                    }
                    if (($s['person'] ?? '') === 'borrower') {
                        $isActive = $deskPerson === 'borrower';
                    }
                @endphp
                <a href="{{ $personSwitcherUrl($s) }}"
                   role="tab"
                   aria-selected="{{ $isActive ? 'true' : 'false' }}"
                   @class([
                       'shrink-0 inline-flex items-center gap-2 rounded-xl px-3.5 py-2.5 text-left ring-1 transition min-w-[8.5rem]',
                       'bg-brand text-white ring-brand shadow-sm' => $isActive,
                       'bg-white text-gray-800 ring-gray-200 hover:bg-brand-muted/40' => ! $isActive,
                   ])>
                    <span class="size-8 rounded-lg overflow-hidden ring-1 ring-black/10 bg-white/20 grid place-items-center shrink-0">
                        @if (! empty($s['avatar_url']))
                            <img src="{{ $s['avatar_url'] }}" alt="" class="size-full object-cover">
                        @else
                            <span @class([
                                'text-[11px] font-bold',
                                'text-white' => $isActive,
                                'text-brand' => ! $isActive,
                            ])>{{ strtoupper(substr((string) ($s['sublabel'] ?? $s['label'] ?? '?'), 0, 1)) }}</span>
                        @endif
                    </span>
                    <span class="min-w-0">
                    <span class="text-xs font-bold truncate max-w-[11rem] block">
                        {{ $s['label'] ?? 'Subject' }}
                        @if (! empty($s['sublabel']))
                            · {{ \Illuminate\Support\Str::of($s['sublabel'])->explode(' ')->take(2)->implode(' ') }}
                        @endif
                    </span>
                    <span @class([
                        'mt-0.5 text-[10px] font-semibold tabular-nums',
                        'text-white/85' => $isActive,
                        'text-gray-500' => ! $isActive,
                    ])>
                        {{ (int) ($s['done'] ?? 0) }}/{{ (int) ($s['total'] ?? 0) }}
                        @if (($s['failed'] ?? 0) > 0)
                            · {{ (int) $s['failed'] }}✗
                        @elseif (! empty($s['complete']))
                            · Ready
                        @endif
                    </span>
                    </span>
                </a>
            @endforeach
        </div>
    </div>
@endif
