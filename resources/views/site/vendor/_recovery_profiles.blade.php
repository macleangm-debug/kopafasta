@php
    $subjects = $collection_subjects ?? [];
    $person = request('person', 'borrower');
    if (! in_array($person, ['borrower', 'guarantor', 'member'], true)) {
        $person = 'borrower';
    }
    $gId = (int) request('g', 0);
    $mId = (int) request('m', 0);
    $tab = request('tab', 'personal');
    $allowedTabs = ['personal', 'residence', 'activity'];
    if (! in_array($tab, $allowedTabs, true)) {
        $tab = 'personal';
    }

    $selected = collect($subjects)->first(function ($row) use ($person, $gId, $mId) {
        if (($row['person'] ?? '') !== $person) {
            return false;
        }
        if ($person === 'guarantor') {
            return $gId <= 0 || (int) ($row['g'] ?? 0) === $gId;
        }
        if ($person === 'member') {
            return $mId <= 0 || (int) ($row['m'] ?? 0) === $mId;
        }

        return true;
    }) ?? collect($subjects)->first();

    $subjectUrl = function (array $row, ?string $tabKey = null) use ($assignment, $tab) {
        return route('site.partner.recovery-case', array_filter([
            'recoveryAssignment' => $assignment,
            'person' => $row['person'] ?? 'borrower',
            'g' => $row['g'] ?? null,
            'm' => $row['m'] ?? null,
            'tab' => $tabKey ?? $tab,
        ], fn ($v) => $v !== null && $v !== '')).'#collection-profiles';
    };

    $subjectReview = $selected['file'] ?? null;
    $subjectRecord = $record ?? $loan?->application;
@endphp

@if (! empty($subjects) && $subjectRecord)
    <div id="collection-profiles" class="glass-card rounded-2xl ring-1 ring-brand/10 overflow-hidden scroll-mt-24">
        <div class="px-5 pt-5 pb-3 border-b border-gray-100">
            <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">Collection file</p>
            <h2 class="text-lg font-bold text-gray-900 mt-0.5">Profile sections</h2>
            <p class="text-sm text-gray-500 mt-0.5">Borrower, guarantor, and group members — identity, next of kin, residence, and activity. No screening or CRB.</p>
        </div>

        @if (count($subjects) > 1)
            <div class="px-4 pt-3 flex gap-2 overflow-x-auto" role="tablist" aria-label="People on this loan">
                @foreach ($subjects as $row)
                    @php
                        $isActive = ($selected['key'] ?? '') === ($row['key'] ?? '');
                    @endphp
                    <a href="{{ $subjectUrl($row) }}"
                       role="tab"
                       aria-selected="{{ $isActive ? 'true' : 'false' }}"
                       @class([
                           'shrink-0 inline-flex flex-col rounded-xl px-3.5 py-2.5 text-left ring-1 transition min-w-[8.5rem]',
                           'bg-brand text-white ring-brand shadow-sm' => $isActive,
                           'bg-white text-gray-800 ring-gray-200 hover:bg-brand-muted/40' => ! $isActive,
                       ])>
                        <span class="text-xs font-bold truncate max-w-[11rem]">{{ $row['label'] }}</span>
                        <span class="mt-0.5 text-[10px] {{ $isActive ? 'text-white/80' : 'text-gray-500' }} truncate max-w-[11rem]">{{ $row['sublabel'] ?? '' }}</span>
                    </a>
                @endforeach
            </div>
        @endif

        <div class="px-3 pt-3 flex gap-1.5 overflow-x-auto border-b border-gray-100" role="tablist" aria-label="Profile sections">
            @foreach (['personal' => 'Personal', 'residence' => 'Residence', 'activity' => 'Activity'] as $key => $label)
                <a href="{{ $selected ? $subjectUrl($selected, $key) : '#' }}"
                   role="tab"
                   aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                   @class([
                       'shrink-0 rounded-xl px-4 py-2.5 text-xs font-semibold transition',
                       'bg-brand text-white shadow-sm' => $tab === $key,
                       'bg-transparent text-gray-600 hover:bg-brand-muted/50' => $tab !== $key,
                   ])>
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <div class="p-5">
            @if (! $subjectReview)
                <p class="text-sm text-gray-500">Profile is not complete for this person yet.</p>
            @elseif ($tab === 'personal')
                @include('admin.loan-applications.review._profile_personal', [
                    'review' => $subjectReview,
                    'record' => $subjectRecord,
                    'hideAdminLinks' => true,
                ])
            @elseif ($tab === 'residence')
                @include('admin.loan-applications.review._profile_residence', [
                    'review' => $subjectReview,
                    'record' => $subjectRecord,
                ])
            @else
                @include('admin.loan-applications.review._profile_activity', [
                    'review' => $subjectReview,
                    'record' => $subjectRecord,
                ])
            @endif
        </div>
    </div>
@endif
