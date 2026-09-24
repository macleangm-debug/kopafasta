{{-- Application 360 — Member-360 visual language; canonical next-action services only. --}}
@php
    $app360 = $app360 ?? app(\App\Services\Application360Presenter::class)->forApplication(
        $record,
        auth()->user(),
        $stageHistory ?? null,
        $documentRequests ?? null,
    );
    $next = $app360['next'] ?? [];
    $lifecycle = $app360['lifecycle'] ?? [];
    $people = $app360['people'] ?? [];
    $participants = $app360['participants'] ?? $people;
    $readiness = $app360['readiness'] ?? [];
    $timeline = $app360['timeline'] ?? [];
    $percent = isset($app360['progress_percent']) ? (int) $app360['progress_percent'] : null;
    $isDraft = ! empty($app360['is_draft']);
    $isGroup = ! empty($app360['is_group']);
    $defaultKey = $participants[0]['key'] ?? 'p0';
    $previousGuarantors = $app360['previous_guarantors'] ?? [];
@endphp

<section id="application-360" class="mb-6 space-y-4">
    <p class="text-[10px] uppercase tracking-widest text-slate-500 font-bold px-0.5">Application 360</p>

    {{-- Needs Attention --}}
    <div class="rounded-2xl bg-white ring-1 ring-brand/15 shadow-sm px-5 py-4">
        <div class="flex flex-col lg:flex-row lg:items-stretch gap-4">
            <div class="min-w-0 flex-1 space-y-3">
                <p class="text-[10px] uppercase tracking-widest text-brand font-bold">Needs Attention / Next Action</p>
                <div class="grid sm:grid-cols-2 gap-3">
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">What is missing?</p>
                        <p class="text-sm font-semibold text-slate-900 mt-1">{{ $next['reason'] ?? $next['missing'] ?? 'Continue' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">Who needs to act?</p>
                        <p class="text-sm font-semibold text-slate-900 mt-1">{{ $next['who'] ?? 'Staff' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">Deadline</p>
                        <p class="text-sm font-semibold text-slate-900 mt-1">
                            @if (! empty($next['deadline']))
                                {{ is_string($next['deadline']) ? $next['deadline'] : format_app_datetime($next['deadline'], 'd M Y') }}
                            @else
                                —
                            @endif
                        </p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">What happens next?</p>
                        <p class="text-sm font-semibold text-slate-900 mt-1">{{ $next['headline'] ?? 'Continue' }}</p>
                    </div>
                </div>
            </div>
            <div class="shrink-0 flex lg:items-center" x-data="{ notifyOpen: false }">
                @if (($next['cta_kind'] ?? '') === 'confirm_notify' && ! empty($next['href']))
                    <button type="button" @click="notifyOpen = true"
                            class="w-full lg:w-auto inline-flex justify-center items-center px-5 py-3 rounded-xl bg-brand text-white text-sm font-bold shadow-sm hover:bg-brand-light">
                        {{ $next['cta'] }}
                    </button>
                    <x-site.action-panel title="Notify borrower to replace guarantor" open="notifyOpen">
                        <form method="POST" action="{{ $next['href'] }}" class="space-y-4" data-no-draft>
                            @csrf
                            <input type="hidden" name="confirmed" value="1">
                            <p class="text-sm text-slate-700">
                                {{ __('borrower.guarantor_supplement.notify_replace_confirm') }}
                            </p>
                            <p class="text-sm text-slate-600">
                                The borrower will receive the existing in-app notification
                                @if (filled($next['who'] ?? null))
                                    and any SMS/email already configured for this event
                                @endif.
                                They choose the replacement on the guarantor step.
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <button type="submit" class="inline-flex items-center px-4 py-2.5 rounded-xl bg-brand text-white text-sm font-bold">
                                    Send reminder
                                </button>
                                <button type="button" @click="notifyOpen = false"
                                        class="inline-flex items-center px-4 py-2.5 rounded-xl bg-white ring-1 ring-slate-200 text-sm font-semibold text-slate-700">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </x-site.action-panel>
                @elseif (! empty($next['href']) && ($next['cta_kind'] ?? '') !== 'waiting')
                    <a href="{{ $next['href'] }}"
                       class="w-full lg:w-auto inline-flex justify-center items-center px-5 py-3 rounded-xl bg-brand text-white text-sm font-bold shadow-sm hover:bg-brand-light">
                        {{ $next['cta'] ?? ($isDraft ? 'Continue application' : 'Continue') }}
                    </a>
                @elseif (($next['cta_kind'] ?? '') === 'waiting')
                    <span class="w-full lg:w-auto inline-flex justify-center items-center px-5 py-3 rounded-xl bg-amber-50 text-amber-950 text-sm font-bold ring-1 ring-amber-200">
                        {{ $next['cta'] ?? 'Waiting' }}
                    </span>
                @endif
            </div>
        </div>
    </div>

    {{-- Participants --}}
    @if (count($participants) > 0)
        <div x-data="{ p: @js($defaultKey) }" class="rounded-2xl bg-white ring-1 ring-slate-200 px-4 sm:px-5 py-4">
            <div class="flex items-end justify-between gap-3 mb-3">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-slate-500 font-bold">Participants</p>
                    <p class="text-[11px] text-slate-500 mt-0.5">{{ $isGroup ? 'Group' : 'Borrower / Guarantor' }}</p>
                </div>
                <p class="text-[11px] text-slate-500">{{ collect($people)->where('kind', '!=', 'all')->count() }} active participant{{ collect($people)->where('kind', '!=', 'all')->count() === 1 ? '' : 's' }}</p>
            </div>

            <div class="flex gap-2 overflow-x-auto pb-2 -mx-1 px-1 snap-x snap-mandatory"
                 style="-webkit-overflow-scrolling: touch;">
                @foreach ($participants as $person)
                    @php
                        $pkey = $person['key'] ?? ('p'.$loop->index);
                        $chipLabel = $person['label'] ?? $person['role'] ?? $person['name'];
                    @endphp
                    <button type="button"
                            @click="p = @js($pkey)"
                            :class="p === @js($pkey) ? 'bg-brand text-white ring-brand' : 'bg-slate-50 text-slate-700 ring-slate-200 hover:bg-slate-100'"
                            class="snap-start shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold ring-1 transition">
                        {{ $chipLabel }}
                        @if (($person['kind'] ?? '') !== 'all' && isset($person['completion_percent']))
                            <span class="tabular-nums opacity-80">{{ (int) $person['completion_percent'] }}%</span>
                        @endif
                    </button>
                @endforeach
            </div>

            @foreach ($participants as $person)
                @php $pkey = $person['key'] ?? ('p'.$loop->index); @endphp
                <div x-show="p === @js($pkey)" x-cloak class="mt-4 space-y-4">
                    @if (($person['kind'] ?? '') === 'all')
                        <div class="rounded-xl bg-slate-50 ring-1 ring-slate-200 px-4 py-3">
                            <p class="text-sm font-bold text-slate-900">Group readiness</p>
                            <p class="text-sm text-slate-700 mt-1">{{ $person['aggregate_label'] ?? '' }}</p>
                        </div>
                    @else
                        <div class="grid lg:grid-cols-[minmax(0,1fr)_13.5rem] gap-4 items-start">
                            <div class="min-w-0">
                                <p class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">{{ $person['role'] ?? 'Participant' }}</p>
                                <p class="text-sm font-bold text-slate-900">{{ $person['name'] ?? '—' }}</p>
                                @if (! empty($person['is_member']))
                                    <p class="text-sm text-slate-700 mt-2">
                                        KYC / Profile —
                                        <span class="font-semibold">{{ ! empty($person['kyc_ready']) || (int) ($person['completion_percent'] ?? 0) >= 100 ? 'Complete' : 'In progress' }}</span>
                                    </p>
                                    @if (! empty($person['income_proof']))
                                        <p class="text-sm text-slate-700 mt-1">
                                            Proof of Income —
                                            <span class="font-semibold">{{ $person['income_proof']['status_label'] ?? 'Missing' }}</span>
                                        </p>
                                    @endif
                                    <div class="mt-3 flex gap-1.5 overflow-x-auto pb-1" aria-label="Profile sections" style="-webkit-overflow-scrolling: touch;">
                                        @forelse ($person['completion_cards'] ?? [] as $card)
                                            @php
                                                $done = ! empty($card['complete']);
                                                $tone = $done
                                                    ? 'bg-emerald-50 text-emerald-800 ring-emerald-200'
                                                    : 'bg-amber-50 text-amber-950 ring-amber-200';
                                            @endphp
                                            <span class="shrink-0 inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[11px] font-semibold ring-1 {{ $tone }}">
                                                <span aria-hidden="true">{{ $done ? '✓' : '!' }}</span>
                                                {{ $card['label'] }}
                                            </span>
                                        @empty
                                            <span class="text-[11px] text-slate-500">No profile sections yet</span>
                                        @endforelse
                                    </div>
                                @else
                                    <p class="text-sm font-semibold text-slate-800 mt-2">
                                        {{ $person['invitation_status'] ?? $person['readiness'] ?? 'Invited' }}
                                    </p>
                                    @if (! empty($person['contact']))
                                        <p class="text-sm text-slate-600 mt-1">{{ $person['contact'] }}</p>
                                    @endif
                                    <p class="text-sm text-slate-600 mt-1">{{ $person['member_note'] ?? 'Invited as guarantor — not a member yet.' }}</p>
                                @endif
                            </div>
                            @if (! empty($person['is_member']))
                                <div class="rounded-xl bg-slate-50 ring-1 ring-slate-200 px-3 py-3">
                                    <p class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">Profile completion</p>
                                    <p class="text-lg font-bold text-slate-900 tabular-nums mt-1">{{ (int) ($person['completion_percent'] ?? 0) }}%</p>
                                    <div class="mt-2 h-1.5 max-w-[7rem] rounded-full bg-slate-200 overflow-hidden">
                                        <div class="h-full rounded-full bg-brand" style="width: {{ max(0, min(100, (int) ($person['completion_percent'] ?? 0))) }}%"></div>
                                    </div>
                                    <p class="text-xs font-semibold text-slate-700 mt-2">{{ $person['readiness'] ?? 'Ready' }}</p>
                                    @if (! empty($person['href']))
                                        <a href="{{ $person['href'] }}"
                                           class="mt-3 inline-flex w-full justify-center items-center px-3 py-2 rounded-lg bg-brand text-white text-[11px] font-bold hover:bg-brand-light">
                                            Open Member 360
                                        </a>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif

                    @if ($isDraft && ($person['kind'] ?? '') !== 'all' && count($readiness) > 0)
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold mb-2">Application progress</p>
                            <div class="flex gap-1.5 overflow-x-auto pb-1">
                                @foreach ($readiness as $row)
                                    @php
                                        $state = $row['state'] ?? 'upcoming';
                                        $tone = match ($state) {
                                            'complete' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
                                            'current' => 'bg-brand/10 text-brand ring-brand/25 font-bold',
                                            'attention' => 'bg-amber-50 text-amber-950 ring-amber-200 font-bold',
                                            default => 'bg-slate-50 text-slate-500 ring-slate-200',
                                        };
                                        $mark = match ($state) {
                                            'complete' => '✓',
                                            'current' => '●',
                                            'attention' => '!',
                                            default => '○',
                                        };
                                    @endphp
                                    <span class="shrink-0 inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[11px] ring-1 {{ $tone }}">
                                        <span aria-hidden="true">{{ $mark }}</span> {{ $row['label'] }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if (($person['kind'] ?? '') !== 'all')
                        @php
                            $docs = $person['documents'] ?? [];
                            $holderGroups = collect([
                                'identity' => 'Identity',
                                'residence' => 'Residence',
                                'financial' => 'Financial',
                                'business' => 'Business',
                                'collateral' => 'Collateral',
                                'other' => 'Other',
                            ])->filter(fn ($_, $key) => collect($docs)->contains(fn ($doc) => ($doc['category'] ?? '') === $key))
                                ->map(fn ($label, $key) => ['key' => $key, 'label' => $label])
                                ->values()
                                ->all();
                        @endphp
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold mb-2">Documents</p>
                            @if (empty($person['is_member']))
                                <p class="text-sm text-slate-500">No member file — this person was invited as a guarantor and has not registered.</p>
                            @elseif ($docs === [])
                                <p class="text-sm text-slate-500">No documents on file for this person.</p>
                            @else
                                <x-admin.document-holder :items="$docs" :groups="$holderGroups" :expanded="false" />
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach

            @if (count($previousGuarantors) > 0)
                <div class="mt-4 pt-3 border-t border-slate-100">
                    <p class="text-[10px] uppercase tracking-widest text-slate-500 font-bold">Previous guarantor</p>
                    <ul class="mt-2 space-y-1.5">
                        @foreach ($previousGuarantors as $row)
                            <li class="flex flex-wrap items-center gap-2 text-sm">
                                <span class="font-semibold text-slate-900">{{ $row['name'] ?? 'Guarantor' }}</span>
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-md text-[11px] font-bold bg-rose-50 text-rose-800 ring-1 ring-rose-200">{{ $row['status'] ?? 'Rejected' }}</span>
                                @if (! empty($row['at']))
                                    <span class="text-xs text-slate-500">{{ $row['at'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif

    @unless ($isDraft)
        {{-- Journey / Readiness (submitted files only) --}}
        <div>
            <p class="text-[10px] uppercase tracking-widest text-slate-500 font-bold mb-2 px-0.5">Journey / readiness</p>
            <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-2">
                @foreach ($readiness as $row)
                    @php
                        $state = $row['state'] ?? 'upcoming';
                        if ($state === 'na') {
                            continue;
                        }
                        $tone = match ($state) {
                            'complete' => 'bg-emerald-50 ring-emerald-200 text-emerald-900',
                            'current' => 'bg-brand/5 ring-brand/20 text-brand',
                            'attention' => 'bg-amber-50 ring-amber-200 text-amber-950',
                            default => 'bg-slate-50 ring-slate-200 text-slate-600',
                        };
                        $mark = match ($state) {
                            'complete' => '✓ Complete',
                            'current' => '● Current',
                            'attention' => '! Needs attention',
                            default => '○ Upcoming',
                        };
                    @endphp
                    @if (! empty($row['href']) && in_array($state, ['current', 'attention'], true))
                        <a href="{{ $row['href'] }}" class="rounded-xl ring-1 px-3 py-2.5 {{ $tone }} block hover:brightness-95">
                            <p class="text-xs font-bold">{{ $row['label'] }}</p>
                            <p class="text-[11px] mt-0.5 opacity-90">{{ $mark }}</p>
                            @if (! empty($row['detail']) && $state !== 'complete')
                                <p class="text-[11px] mt-1 font-semibold">{{ $row['detail'] }}</p>
                            @endif
                        </a>
                    @else
                        <div class="rounded-xl ring-1 px-3 py-2.5 {{ $tone }}">
                            <p class="text-xs font-bold">{{ $row['label'] }}</p>
                            <p class="text-[11px] mt-0.5 opacity-90">{{ $mark }}</p>
                            @if (! empty($row['detail']) && $state !== 'complete')
                                <p class="text-[11px] mt-1 font-semibold">{{ $row['detail'] }}</p>
                            @endif
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        @if (count($lifecycle) > 0)
            <div class="rounded-2xl bg-white ring-1 ring-slate-200 px-4 py-3">
                <p class="text-[10px] uppercase tracking-widest text-slate-500 font-bold mb-2">Lifecycle</p>
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($lifecycle as $step)
                        @php
                            $tone = match ($step['state'] ?? '') {
                                'complete' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
                                'current' => 'bg-brand/10 text-brand ring-brand/25 font-bold',
                                'attention' => 'bg-amber-50 text-amber-950 ring-amber-200 font-bold',
                                default => 'bg-slate-50 text-slate-500 ring-slate-200',
                            };
                            $mark = match ($step['state'] ?? '') {
                                'complete' => '✓',
                                'current' => '●',
                                'attention' => '!',
                                default => '○',
                            };
                        @endphp
                        @if (! empty($step['href']))
                            <a href="{{ $step['href'] }}" class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[11px] ring-1 {{ $tone }}">
                                <span aria-hidden="true">{{ $mark }}</span> {{ $step['label'] }}
                            </a>
                        @else
                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[11px] ring-1 {{ $tone }}">
                                <span aria-hidden="true">{{ $mark }}</span> {{ $step['label'] }}
                            </span>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif
    @endunless

    @if (count($timeline) > 0)
        <details class="rounded-2xl ring-1 ring-slate-200 bg-white">
            <summary class="cursor-pointer list-none px-4 py-3 text-xs font-bold text-slate-700 flex items-center justify-between">
                <span>Application timeline</span>
                <span class="text-slate-400 font-normal">{{ count($timeline) }} events</span>
            </summary>
            <ol class="border-t border-slate-100 px-4 py-3 space-y-2 max-h-64 overflow-y-auto">
                @foreach ($timeline as $event)
                    <li class="text-[11px] text-slate-700">
                        <span class="text-slate-400 tabular-nums">{{ $event['at'] }}</span>
                        <span class="mx-1 text-slate-300">·</span>
                        {{ $event['label'] }}
                    </li>
                @endforeach
            </ol>
        </details>
    @endif
</section>
