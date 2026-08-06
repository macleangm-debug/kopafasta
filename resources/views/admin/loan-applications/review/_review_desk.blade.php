@php
    $deskPerson = request('review_person', request('person', 'borrower'));
    if (! in_array($deskPerson, ['borrower', 'guarantor', 'member'], true)) {
        $deskPerson = 'borrower';
    }
    $deskG = $deskPerson === 'guarantor' ? (int) request('review_g', request('g', 0)) : null;
    $deskM = $deskPerson === 'member' ? (int) request('review_m', request('m', 0)) : null;
    if ($deskPerson === 'guarantor' && (! $deskG || $deskG < 1)) {
        $firstG = collect($review['guarantors'] ?? [])->first();
        $deskG = (int) ($firstG['link_id'] ?? 0) ?: null;
        if (! $deskG) {
            $deskPerson = 'borrower';
        }
    }
    if ($deskPerson === 'member' && (! $deskM || $deskM < 1)) {
        $firstM = collect($groupReview['members'] ?? [])->first();
        $deskM = (int) ($firstM['id'] ?? 0) ?: null;
        if (! $deskM) {
            $deskPerson = 'borrower';
        }
    }

    $desk = app(\App\Services\ScreeningChecklistService::class)->deskViewModel(
        $record,
        $review,
        $groupReview ?? null,
        auth()->user(),
        $deskPerson,
        $deskG ?: null,
        $deskM ?: null,
    );
    $canEdit = (bool) ($desk['can_edit'] ?? false);
    $subjectUrl = function (array $s) use ($record) {
        return route('admin.loan-applications.show', array_filter([
            'loan_application' => $record,
            'review_person' => $s['person'],
            'review_g' => $s['g'],
            'review_m' => $s['m'],
        ])).'#review-desk';
    };
@endphp

<section id="review-desk" class="rounded-2xl bg-white ring-1 ring-brand/15 shadow-sm overflow-hidden scroll-mt-24">
    <div class="px-5 py-4 border-b border-gray-100 bg-gradient-to-r from-brand-muted/50 to-white flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">Assisted review</p>
            <h3 class="text-base font-bold text-gray-900 mt-0.5">Review checklist</h3>
            <p class="text-xs text-gray-500 mt-0.5">
                Evidence in place — mark Pass or Fail. Fail needs a reason. Committee can only read what screening recorded.
            </p>
        </div>
        <div class="rounded-xl bg-brand-muted/60 ring-1 ring-brand/15 px-4 py-2.5 text-right">
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">This subject</p>
            <p class="text-lg font-bold text-brand tabular-nums">{{ $desk['decided'] }}/{{ $desk['total'] }}</p>
            <p class="text-[11px] text-brand/80">
                {{ $desk['passed'] }} pass · {{ $desk['failed'] }} fail · {{ $desk['percent'] }}%
            </p>
        </div>
    </div>

    <div class="px-5 py-3 border-b border-gray-100 flex gap-2 overflow-x-auto">
        @foreach ($desk['subjects'] ?? [] as $s)
            <a href="{{ $subjectUrl($s) }}"
               @class([
                   'shrink-0 inline-flex flex-col rounded-xl px-3.5 py-2.5 text-left ring-1 transition min-w-[8.5rem]',
                   'bg-brand text-white ring-brand shadow-sm' => ($desk['subject'] ?? '') === $s['key'],
                   'bg-white text-gray-800 ring-gray-200 hover:bg-brand-muted/40' => ($desk['subject'] ?? '') !== $s['key'],
               ])>
                <span class="text-xs font-bold">{{ $s['label'] }}</span>
                <span @class([
                    'text-[11px] truncate max-w-[9rem]',
                    'text-white/80' => ($desk['subject'] ?? '') === $s['key'],
                    'text-gray-500' => ($desk['subject'] ?? '') !== $s['key'],
                ])>{{ $s['sublabel'] ?? '—' }}</span>
                <span class="mt-1 text-[10px] font-semibold tabular-nums">
                    {{ $s['done'] }}/{{ $s['total'] }}
                    @if ($s['complete'])
                        · Done
                    @elseif (($s['failed'] ?? 0) > 0)
                        · {{ $s['failed'] }} fail
                    @endif
                </span>
            </a>
        @endforeach
    </div>

    <div class="p-5 space-y-4">
        @if ($canEdit)
            <form method="POST" action="{{ route('admin.loan-applications.screening-checklist', $record) }}" class="space-y-4">
                @csrf
                <input type="hidden" name="person" value="{{ $deskPerson }}">
                @if ($deskG)
                    <input type="hidden" name="g" value="{{ $deskG }}">
                @endif
                @if ($deskM)
                    <input type="hidden" name="m" value="{{ $deskM }}">
                @endif

                @foreach ($desk['groups'] ?? [] as $group)
                    <div class="rounded-2xl ring-1 ring-gray-100 overflow-hidden">
                        <div class="px-4 py-3 bg-gradient-to-r from-brand-muted/30 to-white border-b border-gray-100">
                            <h4 class="text-sm font-bold text-gray-900">{{ $group['label'] }}</h4>
                        </div>
                        <ul class="divide-y divide-gray-50">
                            @foreach ($group['items'] as $item)
                                @php
                                    [$ig, $ik] = array_pad(explode('.', $item['key'], 2), 2, '');
                                    $fieldBase = "items[{$ig}][{$ik}]";
                                    $uid = 'rd-'.str_replace(['.', ':'], '-', $item['key']);
                                @endphp
                                <li class="p-4"
                                    x-data="{
                                        verdict: @js($item['verdict'] ?? ''),
                                        reason: @js($item['fail_reason_code'] ?? ''),
                                        open: {{ ($item['verdict'] ?? null) === null ? 'true' : 'false' }}
                                    }">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <button type="button" class="text-left min-w-0 flex-1" @click="open = !open">
                                            <p class="text-sm font-semibold text-gray-900">{{ $item['label'] }}</p>
                                            @if ($item['evidence']['hint'] ?? null)
                                                <p class="text-[11px] text-gray-500 mt-0.5">{{ $item['evidence']['hint'] }}</p>
                                            @endif
                                        </button>
                                        <div class="flex flex-wrap gap-1.5 shrink-0">
                                            <label class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-bold ring-1 cursor-pointer"
                                                   :class="verdict === 'pass' ? 'bg-emerald-50 text-emerald-900 ring-emerald-200' : 'bg-white text-gray-600 ring-gray-200'">
                                                <input type="radio" class="sr-only" name="{{ $fieldBase }}[verdict]" value="pass"
                                                       x-model="verdict" @change="open = true">
                                                Pass ✓
                                            </label>
                                            <label class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-bold ring-1 cursor-pointer"
                                                   :class="verdict === 'fail' ? 'bg-rose-50 text-rose-900 ring-rose-200' : 'bg-white text-gray-600 ring-gray-200'">
                                                <input type="radio" class="sr-only" name="{{ $fieldBase }}[verdict]" value="fail"
                                                       x-model="verdict" @change="open = true">
                                                Fail ✗
                                            </label>
                                            <label class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-bold ring-1 cursor-pointer"
                                                   :class="verdict === 'na' ? 'bg-slate-50 text-slate-700 ring-slate-200' : 'bg-white text-gray-600 ring-gray-200'">
                                                <input type="radio" class="sr-only" name="{{ $fieldBase }}[verdict]" value="na"
                                                       x-model="verdict" @change="open = true">
                                                N/A
                                            </label>
                                        </div>
                                    </div>

                                    <div x-show="open" x-cloak class="mt-3 space-y-3">
                                        @if (! empty($item['evidence']['photos']))
                                            <div class="flex gap-2 overflow-x-auto pb-1">
                                                @foreach ($item['evidence']['photos'] as $photo)
                                                    <a href="{{ $photo['url'] }}" target="_blank" rel="noopener"
                                                       class="shrink-0 w-20 h-20 rounded-xl overflow-hidden ring-1 ring-gray-200 bg-gray-50">
                                                        <img src="{{ $photo['url'] }}" alt="{{ $photo['label'] }}" class="w-full h-full object-cover">
                                                    </a>
                                                @endforeach
                                            </div>
                                        @endif
                                        @if (! empty($item['evidence']['rows']))
                                            <dl class="grid sm:grid-cols-2 gap-2">
                                                @foreach ($item['evidence']['rows'] as $row)
                                                    <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-2">
                                                        <dt class="text-[10px] uppercase tracking-widest text-gray-500">{{ $row['label'] }}</dt>
                                                        <dd class="text-sm font-semibold text-gray-900 mt-0.5 break-words">{{ $row['value'] }}</dd>
                                                    </div>
                                                @endforeach
                                            </dl>
                                        @endif

                                        <div x-show="verdict === 'fail'" x-cloak class="rounded-xl bg-rose-50/80 ring-1 ring-rose-100 p-3 space-y-2">
                                            <label class="block text-[10px] uppercase tracking-widest text-rose-800 font-semibold">Fail reason (required)</label>
                                            <select name="{{ $fieldBase }}[fail_reason_code]" x-model="reason"
                                                    class="w-full rounded-lg border-rose-200 text-sm focus:border-rose-400 focus:ring-rose-200">
                                                <option value="">Select reason…</option>
                                                @foreach ($item['fail_reasons'] as $code => $label)
                                                    <option value="{{ $code }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <textarea name="{{ $fieldBase }}[fail_reason_custom]" rows="2" x-show="reason === 'custom'" x-cloak
                                                      class="w-full rounded-lg border-rose-200 text-sm"
                                                      placeholder="Explain the fail reason…">{{ $item['fail_reason_custom'] ?? '' }}</textarea>
                                        </div>

                                        @if ($item['verdict'] === 'fail' && ($item['fail_reason_label'] ?? null))
                                            <p class="text-[11px] text-rose-800 font-medium">Recorded: {{ $item['fail_reason_label'] }}</p>
                                        @endif
                                        @if ($item['by_name'] || $item['at'])
                                            <p class="text-[11px] text-gray-400">
                                                @if ($item['by_name']){{ $item['by_name'] }}@endif
                                                @if ($item['at'])
                                                    · {{ \Illuminate\Support\Carbon::parse($item['at'])->timezone(config('app.timezone'))->format('d M Y, H:i') }}
                                                @endif
                                            </p>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach

                <div class="flex justify-end">
                    <button type="submit"
                            class="inline-flex items-center rounded-xl bg-brand px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-light">
                        Save review checklist
                    </button>
                </div>
            </form>
        @else
            <div class="space-y-4">
                @foreach ($desk['groups'] ?? [] as $group)
                    <div class="rounded-2xl ring-1 ring-gray-100 overflow-hidden">
                        <div class="px-4 py-3 bg-slate-50 border-b border-gray-100">
                            <h4 class="text-sm font-bold text-gray-900">{{ $group['label'] }}</h4>
                        </div>
                        <ul class="divide-y divide-gray-50">
                            @foreach ($group['items'] as $item)
                                <li class="p-4">
                                    <div class="flex items-start gap-3">
                                        <span @class([
                                            'mt-0.5 inline-flex h-6 min-w-[1.5rem] items-center justify-center rounded-md px-1.5 text-[10px] font-bold ring-1',
                                            'bg-emerald-50 text-emerald-800 ring-emerald-200' => ($item['verdict'] ?? '') === 'pass',
                                            'bg-rose-50 text-rose-800 ring-rose-200' => ($item['verdict'] ?? '') === 'fail',
                                            'bg-slate-50 text-slate-600 ring-slate-200' => ($item['verdict'] ?? '') === 'na',
                                            'bg-gray-50 text-gray-400 ring-gray-200' => ($item['verdict'] ?? null) === null,
                                        ])>
                                            {{ match ($item['verdict'] ?? null) { 'pass' => '✓', 'fail' => '✗', 'na' => 'N/A', default => '—' } }}
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-semibold text-gray-900">{{ $item['label'] }}</p>
                                            @if (($item['verdict'] ?? '') === 'fail' && ($item['fail_reason_label'] ?? null))
                                                <p class="text-xs text-rose-800 mt-1">{{ $item['fail_reason_label'] }}</p>
                                            @endif
                                            @if (! empty($item['evidence']['rows']))
                                                <dl class="mt-2 grid sm:grid-cols-2 gap-1.5">
                                                    @foreach (array_slice($item['evidence']['rows'], 0, 4) as $row)
                                                        <div class="text-[11px] text-gray-600">
                                                            <span class="font-semibold text-gray-500">{{ $row['label'] }}:</span>
                                                            {{ $row['value'] }}
                                                        </div>
                                                    @endforeach
                                                </dl>
                                            @endif
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
