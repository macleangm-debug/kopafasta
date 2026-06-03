@if (($resumableDrafts ?? []) !== [])
    <div class="mb-8 rounded-2xl border border-amber-200 bg-amber-50/60 p-5">
        <h3 class="text-sm font-semibold text-amber-900 mb-1">{{ __('borrower.applications_list.drafts_title') }}</h3>
        <p class="text-xs text-amber-800 mb-4">{{ __('borrower.applications_list.drafts_hint') }}</p>
        <ul class="space-y-3">
            @foreach ($resumableDrafts as $draft)
                <li class="flex flex-wrap items-center justify-between gap-3 bg-white rounded-xl ring-1 ring-amber-200 px-4 py-3">
                    <div>
                        <p class="text-sm font-semibold text-gray-900">{{ $draft['label'] }}</p>
                        <p class="text-xs text-gray-600 mt-0.5">{{ $draft['detail'] }}</p>
                        @if (! empty($draft['saved_at']))
                            <p class="text-[11px] text-gray-400 mt-1">{{ __('borrower.applications_list.draft_saved', ['time' => $draft['saved_at']]) }}</p>
                        @endif
                    </div>
                    <a href="{{ $draft['url'] }}" class="inline-flex bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-4 py-2 rounded-full text-sm shrink-0">
                        {{ __('borrower.applications_list.resume') }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
@endif

<div class="flex items-center justify-between flex-wrap gap-3 mb-6">
    <div>
        <h2 class="text-lg font-semibold">{{ __('borrower.applications_list.title') }}</h2>
        <p class="text-sm text-gray-500">{{ __('borrower.applications_list.subtitle') }}</p>
    </div>
    <div class="flex items-center gap-2">
        <div class="inline-flex rounded-lg ring-1 ring-gray-200 bg-white p-0.5 text-xs">
            <a href="{{ route('site.borrower.loans', ['tab' => 'applications', 'view' => 'cards']) }}"
               class="px-3 py-1.5 rounded-md font-semibold {{ ($viewMode ?? 'cards') === 'cards' ? 'bg-amber-500 text-gray-900' : 'text-gray-600 hover:bg-gray-50' }}">
                {{ __('borrower.applications_list.cards') }}
            </a>
            <a href="{{ route('site.borrower.loans', ['tab' => 'applications', 'view' => 'table']) }}"
               class="px-3 py-1.5 rounded-md font-semibold {{ ($viewMode ?? 'cards') === 'table' ? 'bg-amber-500 text-gray-900' : 'text-gray-600 hover:bg-gray-50' }}">
                {{ __('borrower.applications_list.table') }}
            </a>
        </div>
        <a href="{{ route('site.borrower.apply') }}" class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">+ {{ __('borrower.new_application') }}</a>
    </div>
</div>

@if ($applications->isEmpty())
    <x-site.empty-state
        icon="📋"
        :title="__('borrower.applications_list.empty_title')"
        :description="__('borrower.applications_list.empty_desc')"
        :action-label="__('borrower.applications_list.empty_action')"
        :action-url="route('site.borrower.apply')"
    />
@elseif (($viewMode ?? 'cards') === 'table')
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3">{{ __('borrower.applications_list.reference') }}</th>
                        <th class="px-4 py-3">{{ __('borrower.applications_list.product') }}</th>
                        <th class="px-4 py-3">{{ __('borrower.applications_list.amount') }}</th>
                        <th class="px-4 py-3">{{ __('borrower.applications_list.tenure') }}</th>
                        <th class="px-4 py-3">{{ __('borrower.applications_list.status') }}</th>
                        <th class="px-4 py-3">{{ __('borrower.applications_list.submitted') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('borrower.applications_list.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($applications as $app)
                        @php
                            $isRejected = $app->status === 'rejected';
                            $badge = match (true) {
                                $isRejected => 'bg-red-100 text-red-700',
                                in_array($app->status, ['approved','disbursement','disbursed']) => 'bg-emerald-100 text-emerald-700',
                                $app->status === 'submitted' => 'bg-amber-100 text-amber-700',
                                $app->status === 'awaiting_guarantor' => 'bg-sky-100 text-sky-700',
                                default => 'bg-sky-100 text-sky-700',
                            };
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-mono text-xs">{{ $app->application_number }}</td>
                            <td class="px-4 py-3">{{ $app->product->name ?? '—' }}</td>
                            <td class="px-4 py-3 font-medium">TZS {{ number_format($app->requested_amount) }}</td>
                            <td class="px-4 py-3">{{ __('borrower.applications_list.tenure_short', ['count' => $app->requested_tenure_months]) }}</td>
                            <td class="px-4 py-3">
                                <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $badge }}">{{ ucfirst(str_replace('_',' ', $app->status)) }}</span>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500">{{ optional($app->submitted_at)->format('d M Y') ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('site.borrower.application', $app->id) }}" class="text-amber-600 font-semibold hover:underline text-xs">{{ __('borrower.applications_list.open') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@else
    <div class="grid sm:grid-cols-2 gap-4">
        @foreach ($applications as $app)
            @php
                $stages = ['submitted','screening','credit_appraisal','pre_approval','approval','disbursement','disbursed'];
                $idx = array_search($app->status, $stages);
                $pct = $idx === false ? 10 : (($idx + 1) / count($stages)) * 100;
                $isRejected = $app->status === 'rejected';
                $badge = match (true) {
                    $isRejected => 'bg-red-100 text-red-700',
                    in_array($app->status, ['approved','disbursement','disbursed']) => 'bg-emerald-100 text-emerald-700',
                    $app->status === 'submitted' => 'bg-amber-100 text-amber-700',
                    default => 'bg-sky-100 text-sky-700',
                };
                $stageLabel = ucfirst(str_replace('_',' ', $app->current_stage ?? $app->status));
            @endphp
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div>
                        <p class="font-mono font-semibold text-sm">{{ $app->application_number }}</p>
                        <p class="text-xs text-gray-500">{{ $app->product->name ?? '—' }}</p>
                    </div>
                    <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $badge }}">{{ ucfirst(str_replace('_',' ', $app->status)) }}</span>
                </div>
                <div class="grid grid-cols-2 gap-3 text-sm mb-4">
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.applications_list.requested') }}</p>
                        <p class="font-semibold">TZS {{ number_format($app->requested_amount) }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.applications_list.tenure') }}</p>
                        <p class="font-semibold">{{ __('borrower.applications_list.tenure_months', ['count' => $app->requested_tenure_months]) }}</p>
                    </div>
                </div>
                @if (! $isRejected)
                    <div class="mb-3">
                        <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-amber-500" style="width: {{ $pct }}%"></div>
                        </div>
                        <p class="text-[10px] text-gray-500 mt-1">{{ __('borrower.applications_list.stage', ['stage' => $stageLabel]) }}</p>
                    </div>
                @else
                    <p class="text-xs text-red-600 mb-3">{{ $app->rejection_reason ?? __('borrower.applications_list.rejected_default') }}</p>
                @endif
                <div class="flex items-center gap-2 text-xs">
                    <a href="{{ route('site.borrower.application', $app->id) }}" class="text-amber-600 font-medium hover:underline">{{ __('borrower.applications_list.open_upload') }}</a>
                    <span class="text-gray-300">·</span>
                    <a href="{{ route('site.apply.success', $app->id) }}" class="text-gray-500 hover:text-gray-700">{{ __('borrower.applications_list.receipt') }}</a>
                </div>
            </div>
        @endforeach
    </div>
@endif
