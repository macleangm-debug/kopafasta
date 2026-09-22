@php
    $app360 = app(\App\Services\Application360Presenter::class)->forDraft($draft, $snapshot, $badge);
    $snapshot = $app360['draft_snapshot'] ?? $snapshot;
    $journey = collect($snapshot['journey_steps'] ?? []);
    $profileInfoComplete = ! empty($snapshot['profile_information']['complete']);
    $identityPending = ! empty($snapshot['identity_verification']['pending']);
@endphp

<x-admin.layout
    :title="$app360['application_number'] ?? __('admin.application_drafts.view_application')"
    heading=""
    subheading="">

    {{-- Same Application 360 surface used for submitted files --}}
    @include('admin.loan-applications.review._application_360', [
        'record' => $draft,
        'app360' => $app360,
        'stageHistory' => collect(),
        'documentRequests' => collect(),
    ])

    {{-- Current-stage incomplete details (preserved draft functionality) --}}
    <div class="space-y-4 mb-6">
        <p class="text-[10px] uppercase tracking-widest text-slate-500 font-bold px-0.5">Current incomplete details</p>

        @if ($journey->isNotEmpty())
            <div class="rounded-2xl bg-white ring-1 ring-slate-200 px-4 sm:px-5 py-4">
                <p class="text-xs font-bold text-slate-800 mb-3">{{ __('admin.application_drafts.application_progress') }}</p>
                <ol class="space-y-2.5">
                    @foreach ($journey as $step)
                        @php
                            $done = ! empty($step['complete']);
                            $current = ! empty($step['current']);
                        @endphp
                        <li class="flex items-start gap-3">
                            <span @class([
                                'mt-0.5 size-6 shrink-0 rounded-full grid place-items-center text-xs font-bold ring-1',
                                'bg-emerald-50 text-emerald-800 ring-emerald-200' => $done,
                                'bg-brand text-white ring-brand' => $current && ! $done,
                                'bg-white text-slate-400 ring-slate-200' => ! $done && ! $current,
                            ])>
                                @if ($done) ✓ @elseif ($current) → @else ○ @endif
                            </span>
                            <div class="min-w-0 flex-1">
                                <p @class([
                                    'text-sm font-semibold',
                                    'text-emerald-900' => $done,
                                    'text-brand' => $current && ! $done,
                                    'text-slate-500' => ! $done && ! $current,
                                ])>{{ $step['label'] }}</p>
                                @if ($current)
                                    <p class="text-xs text-slate-500 mt-0.5">Current step</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif

        <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-3">
            @foreach ([
                ['title' => 'Personal information', 'data' => $snapshot['personal'] ?? [], 'fields' => ['name' => 'Name', 'phone' => 'Phone', 'email' => 'Email', 'nida' => 'NIDA']],
                ['title' => 'Identity verification', 'data' => $snapshot['kyc'] ?? [], 'fields' => ['nida' => 'NIDA status', 'face' => 'Face verification'], 'identity' => true],
                ['title' => 'Employment', 'data' => $snapshot['employment'] ?? [], 'fields' => ['type' => 'Activity', 'income' => 'Income range', 'employer' => 'Employer / business']],
                ['title' => 'Residence', 'data' => $snapshot['residence'] ?? [], 'fields' => ['region' => 'Region', 'district' => 'District', 'street' => 'Street']],
            ] as $section)
                <div class="rounded-2xl bg-white ring-1 ring-slate-200 px-4 py-3">
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <h3 class="text-xs font-bold text-slate-900">{{ $section['title'] }}</h3>
                        @if (! empty($section['identity']))
                            <span class="text-[11px] font-semibold {{ $identityPending ? 'text-amber-700' : 'text-emerald-700' }}">
                                {{ $identityPending ? 'Pending' : 'Verified' }}
                            </span>
                        @else
                            <span class="text-[11px] font-semibold {{ ! empty($section['data']['complete']) ? 'text-emerald-700' : 'text-amber-700' }}">
                                {{ ! empty($section['data']['complete']) ? 'Complete' : 'Incomplete' }}
                            </span>
                        @endif
                    </div>
                    <dl class="space-y-1.5 text-sm">
                        @foreach ($section['fields'] as $key => $label)
                            @php $value = $section['data'][$key] ?? null; @endphp
                            @if ($key === 'email' && is_string($value) && str_contains($value, '@phone.kopafasta.local'))
                                @continue
                            @endif
                            <div>
                                <dt class="text-[10px] uppercase tracking-widest text-slate-500">{{ $label }}</dt>
                                <dd class="font-medium text-slate-900">{{ $value ?: '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endforeach
        </div>

        <div class="grid sm:grid-cols-2 gap-3">
            <div class="rounded-2xl bg-white ring-1 ring-slate-200 px-4 py-3">
                <h3 class="text-xs font-bold text-slate-900 mb-2">Guarantor</h3>
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-slate-500">Status</dt>
                        <dd class="mt-1 font-semibold text-slate-900">{{ $snapshot['guarantor']['status'] ?? $snapshot['guarantor_status'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-slate-500">Name</dt>
                        <dd class="mt-1 font-semibold text-slate-900">{{ $snapshot['guarantor']['name'] ?? '—' }}</dd>
                    </div>
                </dl>
            </div>
            <div class="rounded-2xl bg-white ring-1 ring-slate-200 px-4 py-3">
                <h3 class="text-xs font-bold text-slate-900 mb-2">{{ __('admin.application_drafts.uploaded_documents') }}</h3>
                @if (! empty($snapshot['uploaded_documents']))
                    <ul class="space-y-1.5 text-sm text-slate-700">
                        @foreach ($snapshot['uploaded_documents'] as $doc)
                            <li class="flex items-center justify-between gap-3">
                                <span class="flex items-center gap-2 min-w-0">
                                    <span class="text-emerald-600">✓</span>
                                    <span class="truncate">{{ is_array($doc) ? ($doc['label'] ?? 'Document') : $doc }}</span>
                                </span>
                                @if (is_array($doc) && ! empty($doc['url']))
                                    <a href="{{ $doc['url'] }}" target="_blank" class="shrink-0 text-xs font-semibold text-brand hover:underline">View</a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm text-slate-500">{{ __('admin.application_drafts.no_documents_yet') }}</p>
                @endif
            </div>
        </div>

        @include('shared._draft_asset_media', ['snapshot' => $snapshot])

        <div class="flex flex-wrap gap-2 text-xs">
            <a href="{{ route('admin.loan-applications.incomplete') }}"
               class="inline-flex items-center px-3 py-2 rounded-lg bg-white ring-1 ring-slate-200 font-semibold text-slate-700 hover:bg-slate-50">
                ← {{ __('admin.application_drafts.title') }}
            </a>
            @if ($draft->customer)
                <a href="{{ route('admin.customers.show', $draft->customer) }}"
                   class="inline-flex items-center px-3 py-2 rounded-lg bg-brand text-white font-semibold hover:bg-brand-light">
                    {{ __('admin.application_drafts.view_customer_profile') }} →
                </a>
            @endif
        </div>
    </div>
</x-admin.layout>
