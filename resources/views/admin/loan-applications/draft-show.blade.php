<x-admin.layout
    :title="__('admin.application_drafts.view_application')"
    heading=""
    subheading="">

    <x-admin.letterhead
        kicker="Incomplete draft"
        :title="__('admin.application_drafts.view_application')"
        :subtitle="$draft->product?->name ?? __('admin.application_drafts.title')">
        <x-slot:actions>
            <a href="{{ route('admin.loan-applications.incomplete') }}" class="inline-flex items-center text-xs font-semibold text-white/90 ring-1 ring-white/25 hover:bg-white/10 px-3 py-1.5 rounded-lg">{{ __('admin.application_drafts.title') }}</a>
            @if ($draft->customer)
                <a href="{{ route('admin.customers.show', $draft->customer) }}" class="inline-flex items-center text-xs font-semibold text-brand bg-brand-gold hover:brightness-95 px-3 py-1.5 rounded-lg">{{ __('admin.application_drafts.view_customer_profile') }}</a>
            @endif
        </x-slot:actions>
    </x-admin.letterhead>

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6">
                <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">
                            {{ trim(($draft->customer?->first_name ?? '').' '.($draft->customer?->last_name ?? '')) ?: '—' }}
                        </h2>
                        <p class="text-sm text-gray-500">{{ $draft->customer?->phone }} · {{ $draft->product?->name }}</p>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-1 rounded text-xs font-semibold bg-blue-100 text-blue-800">{{ $badge['label'] }}</span>
                </div>

                @php
                    $journey = collect($snapshot['journey_steps'] ?? []);
                    $stepsTotal = max(1, (int) ($snapshot['application_steps_total'] ?? $journey->count() ?: 1));
                    $stepsDone = $journey->where('complete', true)->count();
                    $currentJourney = $journey->firstWhere('current', true);
                    $appPercent = (int) ($snapshot['application_completion_percent'] ?? 0);
                    $profileInfoComplete = ! empty($snapshot['profile_information']['complete']);
                    $identityPending = ! empty($snapshot['identity_verification']['pending']);
                @endphp

                <div class="rounded-xl bg-slate-50 ring-1 ring-slate-200 px-4 py-4 space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-[11px] font-bold uppercase tracking-widest text-slate-500">{{ __('admin.application_drafts.application_progress') ?? 'Application progress' }}</p>
                        <p class="text-xs font-semibold text-slate-600 tabular-nums">
                            {{ $appPercent }}%
                            <span class="font-normal text-slate-500">· {{ $stepsDone }} of {{ $stepsTotal }} steps complete</span>
                        </p>
                    </div>
                    <div class="h-2 rounded-full bg-white ring-1 ring-slate-200 overflow-hidden">
                        <div class="h-full rounded-full bg-brand" style="width: {{ min(100, max(0, $appPercent)) }}%"></div>
                    </div>

                    @if ($journey->isNotEmpty())
                        <ol class="space-y-2.5 pt-1">
                            @foreach ($journey as $step)
                                @php
                                    $done = ! empty($step['complete']);
                                    $current = ! empty($step['current']);
                                    $waiting = $step['waiting'] ?? null;
                                @endphp
                                <li class="flex items-start gap-3">
                                    <span @class([
                                        'mt-0.5 size-6 shrink-0 rounded-full grid place-items-center text-xs font-bold ring-1',
                                        'bg-emerald-50 text-emerald-800 ring-emerald-200' => $done,
                                        'bg-brand text-white ring-brand' => $current && ! $done,
                                        'bg-white text-slate-400 ring-slate-200' => ! $done && ! $current,
                                    ])>
                                        @if ($done)
                                            ✓
                                        @elseif ($current)
                                            →
                                        @else
                                            ○
                                        @endif
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p @class([
                                            'text-sm font-semibold',
                                            'text-emerald-900' => $done,
                                            'text-brand' => $current && ! $done,
                                            'text-slate-500' => ! $done && ! $current,
                                        ])>{{ $step['label'] }}</p>
                                        @if ($current && filled($waiting))
                                            <p class="text-xs font-medium text-amber-800 mt-0.5">{{ $waiting }}</p>
                                        @elseif ($current)
                                            <p class="text-xs text-slate-500 mt-0.5">Current step</p>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @else
                        <dl class="grid sm:grid-cols-2 gap-3 text-sm pt-1">
                            <div>
                                <dt class="text-xs uppercase tracking-widest text-gray-500">{{ __('admin.application_drafts.current_step') }}</dt>
                                <dd class="mt-1 font-semibold text-gray-900">{{ $snapshot['current_step'] }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase tracking-widest text-gray-500">{{ __('admin.application_drafts.guarantor_status') }}</dt>
                                <dd class="mt-1 font-semibold text-gray-900">{{ $snapshot['guarantor_status'] }}</dd>
                            </div>
                        </dl>
                    @endif

                    @if ($currentJourney && filled($currentJourney['waiting'] ?? null))
                        <p class="text-sm text-slate-700 border-t border-slate-200 pt-3">
                            Applicant information is in place. This application is currently
                            <span class="font-semibold text-amber-900">{{ strtolower((string) $currentJourney['waiting']) }}</span>.
                        </p>
                    @endif
                </div>

                <div class="mt-4 grid sm:grid-cols-2 gap-3">
                    <div class="rounded-xl bg-white ring-1 ring-gray-200 px-4 py-3">
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Profile information</p>
                        <p class="mt-1 text-sm font-bold {{ $profileInfoComplete ? 'text-emerald-800' : 'text-amber-800' }}">
                            {{ $profileInfoComplete ? 'Complete ✓' : ((int) ($snapshot['profile_information']['percent'] ?? $snapshot['profile_completion_percent'] ?? 0)).'% supplied' }}
                        </p>
                        <p class="text-xs text-gray-500 mt-0.5">Required profile fields for this member</p>
                    </div>
                    <div class="rounded-xl bg-white ring-1 ring-gray-200 px-4 py-3">
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Identity verification</p>
                        <p class="mt-1 text-sm font-bold {{ $identityPending ? 'text-amber-800' : 'text-emerald-800' }}">
                            {{ $identityPending ? 'Pending' : 'Verified ✓' }}
                        </p>
                        <p class="text-xs text-gray-500 mt-0.5">
                            NIDA {{ $snapshot['identity_verification']['nida'] ?? ($snapshot['kyc']['nida'] ?? '—') }}
                            · Face {{ $snapshot['identity_verification']['face'] ?? ($snapshot['kyc']['face'] ?? '—') }}
                        </p>
                    </div>
                </div>

                <div class="mt-4 text-sm text-gray-600">
                    <span class="font-semibold text-gray-800">Last activity:</span>
                    {{ optional($snapshot['last_activity'])->format('d M Y H:i') ?? '—' }}
                    @if ($snapshot['last_activity'])
                        <span class="text-gray-500">({{ $snapshot['last_activity']->diffForHumans() }})</span>
                    @endif
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                @foreach ([
                    ['title' => 'Personal information', 'data' => $snapshot['personal'] ?? [], 'fields' => ['name' => 'Name', 'phone' => 'Phone', 'email' => 'Email', 'nida' => 'NIDA']],
                    ['title' => 'Identity verification (operational)', 'data' => $snapshot['kyc'] ?? [], 'fields' => ['nida' => 'NIDA status', 'face' => 'Face verification'], 'identity' => true],
                    ['title' => 'Employment', 'data' => $snapshot['employment'] ?? [], 'fields' => ['type' => 'Activity', 'income' => 'Income range', 'employer' => 'Employer / business']],
                    ['title' => 'Residence', 'data' => $snapshot['residence'] ?? [], 'fields' => ['region' => 'Region', 'district' => 'District', 'street' => 'Street']],
                ] as $section)
                    <div class="bg-white rounded-xl ring-1 ring-gray-200 p-5">
                        <div class="flex items-center justify-between gap-2 mb-3">
                            <h3 class="text-sm font-semibold text-gray-900">{{ $section['title'] }}</h3>
                            @if (! empty($section['identity']))
                                <span class="text-xs font-semibold {{ ! empty($snapshot['identity_verification']['verified']) ? 'text-emerald-700' : 'text-amber-700' }}">
                                    {{ ! empty($snapshot['identity_verification']['verified']) ? 'Verified' : 'Pending' }}
                                </span>
                            @else
                                <span class="text-xs font-semibold {{ ! empty($section['data']['complete']) ? 'text-emerald-700' : 'text-amber-700' }}">
                                    {{ ! empty($section['data']['complete']) ? 'Complete' : 'Incomplete' }}
                                </span>
                            @endif
                        </div>
                        <dl class="space-y-2 text-sm">
                            @foreach ($section['fields'] as $key => $label)
                                <div>
                                    <dt class="text-xs text-gray-500">{{ $label }}</dt>
                                    <dd class="font-medium text-gray-900">{{ $section['data'][$key] ?? '—' }}</dd>
                                </div>
                            @endforeach
                        </dl>
                        @if (! empty($section['identity']))
                            <p class="text-[11px] text-gray-500 mt-3">Separate from profile information completion. Profile can be complete while verification is still pending.</p>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-3">Guarantor</h3>
                <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-xs uppercase tracking-widest text-gray-500">Status</dt>
                        <dd class="mt-1 font-semibold text-gray-900">{{ $snapshot['guarantor']['status'] ?? $snapshot['guarantor_status'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-widest text-gray-500">Name</dt>
                        <dd class="mt-1 font-semibold text-gray-900">{{ $snapshot['guarantor']['name'] ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-3">{{ __('admin.application_drafts.uploaded_documents') }}</h3>
                @if (! empty($snapshot['uploaded_documents']))
                    <ul class="space-y-2 text-sm text-gray-700">
                        @foreach ($snapshot['uploaded_documents'] as $doc)
                            <li class="flex items-center justify-between gap-3">
                                <span class="flex items-center gap-2">
                                    <span class="text-emerald-600">✓</span>
                                    <span>{{ is_array($doc) ? ($doc['label'] ?? 'Document') : $doc }}</span>
                                </span>
                                @if (is_array($doc) && ! empty($doc['url']))
                                    <a href="{{ $doc['url'] }}" target="_blank" class="text-xs font-semibold text-brand hover:underline">View</a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm text-gray-500">{{ __('admin.application_drafts.no_documents_yet') }}</p>
                @endif
            </div>

            @include('shared._draft_asset_media', ['snapshot' => $snapshot])
        </div>

        <div class="space-y-4">
            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-5 text-sm space-y-3">
                <h3 class="font-semibold text-gray-900">{{ __('admin.application_drafts.customer') }}</h3>
                @if ($draft->customer)
                    <p>{{ $draft->customer->phone }}</p>
                    <p class="text-gray-500">{{ $draft->customer->email }}</p>
                @endif
            </div>
            @if ($amount = app(\App\Services\LoanApplicationDraftService::class)->requestedAmount($draft))
                <div class="bg-white rounded-xl ring-1 ring-gray-200 p-5 text-sm">
                    <p class="text-xs uppercase tracking-widest text-gray-500">{{ __('admin.application_drafts.amount') }}</p>
                    <p class="text-xl font-bold text-gray-900 mt-1">{{ format_money($amount) }}</p>
                </div>
            @endif
        </div>
    </div>
</x-admin.layout>
