@php $snapshot = $profile['snapshot'] ?? []; @endphp

@if ($snapshot !== [])
    <div class="glass-card p-5 mb-6">
        <div class="mb-4">
            <h2 class="font-semibold">{{ __('borrower.loan_profile.profile_snapshot_title') }}</h2>
            <p class="text-xs text-gray-500 mt-1">{{ __('borrower.loan_profile.profile_snapshot_hint') }}</p>
        </div>

        <div class="grid md:grid-cols-2 gap-4 mb-4">
            @foreach ([
                ['title' => __('borrower.loan_profile.sections.personal'), 'data' => $snapshot['personal'] ?? [], 'fields' => ['name' => __('borrower.loan_profile.fields.name'), 'phone' => __('borrower.loan_profile.fields.phone'), 'email' => __('borrower.loan_profile.fields.email'), 'nida' => __('borrower.loan_profile.fields.nida')]],
                ['title' => __('borrower.loan_profile.sections.kyc'), 'data' => $snapshot['kyc'] ?? [], 'fields' => ['nida' => __('borrower.loan_profile.fields.nida_status'), 'face' => __('borrower.loan_profile.fields.face_verification')]],
                ['title' => __('borrower.loan_profile.sections.employment'), 'data' => $snapshot['employment'] ?? [], 'fields' => ['type' => __('borrower.loan_profile.fields.activity'), 'income' => __('borrower.loan_profile.fields.income'), 'employer' => __('borrower.loan_profile.fields.employer')]],
                ['title' => __('borrower.loan_profile.sections.residence'), 'data' => $snapshot['residence'] ?? [], 'fields' => ['region' => __('borrower.loan_profile.fields.region'), 'district' => __('borrower.loan_profile.fields.district'), 'street' => __('borrower.loan_profile.fields.street')]],
            ] as $section)
                <div class="rounded-xl ring-1 ring-gray-200 p-4">
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <h3 class="text-sm font-semibold text-gray-900">{{ $section['title'] }}</h3>
                        <span class="text-xs font-semibold {{ ! empty($section['data']['complete']) ? 'text-emerald-700' : 'text-amber-700' }}">
                            {{ ! empty($section['data']['complete']) ? __('borrower.loan_profile.complete') : __('borrower.loan_profile.incomplete') }}
                        </span>
                    </div>
                    <dl class="space-y-2 text-sm">
                        @foreach ($section['fields'] as $key => $label)
                            <div>
                                <dt class="text-xs text-gray-500">{{ $label }}</dt>
                                <dd class="font-medium text-gray-900">{{ $section['data'][$key] ?? '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endforeach
        </div>

        <div class="rounded-xl ring-1 ring-gray-200 p-4 mb-4">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">{{ __('borrower.loan_profile.sections.guarantor') }}</h3>
            <p class="text-sm text-gray-700">{{ $snapshot['guarantor']['status'] ?? ($snapshot['guarantor_status'] ?? '—') }}</p>
            @if (! empty($snapshot['guarantor']['name']))
                <p class="text-xs text-gray-500 mt-1">{{ $snapshot['guarantor']['name'] }}</p>
            @endif
        </div>

        @if (! empty($snapshot['uploaded_documents']))
            <div class="rounded-xl ring-1 ring-gray-200 p-4">
                <h3 class="text-sm font-semibold text-gray-900 mb-3">{{ __('borrower.loan_profile.sections.documents') }}</h3>
                <ul class="flex flex-wrap gap-2">
                    @foreach ($snapshot['uploaded_documents'] as $doc)
                        <li>
                            @if (! empty($doc['url']))
                                <a href="{{ $doc['url'] }}" target="_blank" rel="noopener"
                                   class="inline-flex text-xs font-semibold text-amber-700 bg-amber-50 ring-1 ring-amber-200 px-3 py-1.5 rounded-lg hover:bg-amber-100">
                                    {{ $doc['label'] ?? __('borrower.loan_profile.document_fallback') }} ↗
                                </a>
                            @else
                                <span class="inline-flex text-xs text-gray-500 bg-gray-50 ring-1 ring-gray-200 px-3 py-1.5 rounded-lg">{{ $doc['label'] ?? __('borrower.loan_profile.document_fallback') }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @include('shared._draft_asset_media', ['snapshot' => $snapshot, 'heading' => __('borrower.loan_profile.sections.asset_documents')])
    </div>
@endif
