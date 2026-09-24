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

    @php
        $app360 = app(\App\Services\Application360Presenter::class)->forDraft($draft, $snapshot, $badge);
    @endphp
    @include('admin.loan-applications.review._application_360', [
        'record' => $draft,
        'app360' => $app360,
        'stageHistory' => collect(),
        'documentRequests' => collect(),
    ])

    <p class="text-[10px] uppercase tracking-widest text-slate-500 font-bold mb-3">Current incomplete details</p>

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

                <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-xs uppercase tracking-widest text-gray-500">{{ __('admin.application_drafts.profile_completion') }}</dt>
                        <dd class="mt-1 font-semibold text-gray-900">{{ $snapshot['profile_completion_percent'] }}%</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-widest text-gray-500">{{ __('admin.application_drafts.application_completion') }}</dt>
                        <dd class="mt-1 font-semibold text-gray-900">{{ $snapshot['application_completion_percent'] }}%</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-widest text-gray-500">{{ __('admin.application_drafts.current_step') }}</dt>
                        <dd class="mt-1 font-semibold text-gray-900">{{ $snapshot['current_step'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-widest text-gray-500">{{ __('admin.application_drafts.guarantor_status') }}</dt>
                        <dd class="mt-1 font-semibold text-gray-900">{{ $snapshot['guarantor_status'] }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs uppercase tracking-widest text-gray-500">{{ __('admin.application_drafts.last_activity') }}</dt>
                        <dd class="mt-1 font-semibold text-gray-900">
                            {{ optional($snapshot['last_activity'])->format('d M Y H:i') ?? '—' }}
                            @if ($snapshot['last_activity'])
                                <span class="text-gray-500 font-normal">({{ $snapshot['last_activity']->diffForHumans() }})</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>

            @php
                $memberFileUrl = $draft->customer
                    ? fn (string $tab) => route('admin.customers.show', ['customer' => $draft->customer, 'tab' => $tab]).'#member-file'
                    : null;
                $profileChips = [
                    ['key' => 'personal', 'label' => 'Personal', 'tab' => 'about', 'complete' => ! empty($snapshot['personal']['complete'])],
                    ['key' => 'kyc', 'label' => 'KYC', 'tab' => 'about', 'complete' => ! empty($snapshot['kyc']['complete'])],
                    ['key' => 'employment', 'label' => 'Employment', 'tab' => 'activity', 'complete' => ! empty($snapshot['employment']['complete'])],
                    ['key' => 'residence', 'label' => 'Residence', 'tab' => 'residence', 'complete' => ! empty($snapshot['residence']['complete'])],
                ];
            @endphp
            <div>
                <p class="text-[10px] uppercase tracking-widest text-slate-500 font-bold mb-2">Profile</p>
                <div class="flex gap-1 overflow-x-auto pb-1" role="tablist" aria-label="Profile sections">
                    @foreach ($profileChips as $chip)
                        @php
                            $chipClass = $chip['complete']
                                ? 'bg-emerald-50 text-emerald-800 ring-emerald-200'
                                : 'bg-amber-50 text-amber-950 ring-amber-200';
                            $mark = $chip['complete'] ? '✓' : '!';
                        @endphp
                        @if ($memberFileUrl)
                            <a href="{{ $memberFileUrl($chip['tab']) }}"
                               class="shrink-0 inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg ring-1 {{ $chipClass }}">
                                <span aria-hidden="true">{{ $mark }}</span>
                                {{ $chip['label'] }}
                            </a>
                        @else
                            <span class="shrink-0 inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg ring-1 {{ $chipClass }}">
                                <span aria-hidden="true">{{ $mark }}</span>
                                {{ $chip['label'] }}
                            </span>
                        @endif
                    @endforeach
                </div>
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
