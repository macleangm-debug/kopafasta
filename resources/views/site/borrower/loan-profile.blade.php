<x-site.borrower-layout
    :title="brand_title($profile['summary']['application_number'] ?? __('borrower.loan_profile.title'))"
    active="loans">

    @php
        $summary = $profile['summary'];
        $status = $profile['status'];
        $progress = $profile['progress'];
        $toneClasses = [
            'gray'    => 'bg-gray-100 text-gray-700',
            'amber'   => 'bg-amber-100 text-amber-700',
            'sky'     => 'bg-sky-100 text-sky-700',
            'emerald' => 'bg-emerald-100 text-emerald-700',
            'red'     => 'bg-red-100 text-red-700',
            'orange'  => 'bg-orange-100 text-orange-700',
        ];
        $statusBadge = $toneClasses[$status['tone']] ?? $toneClasses['sky'];
        $application = $profile['application'] ?? null;
        $draft = $profile['draft'] ?? null;
    @endphp

    <div class="mb-4">
        <a href="{{ route('site.borrower.loans', ['tab' => 'applications']) }}" class="text-xs text-gray-500 hover:text-gray-700">
            {{ __('borrower.loan_profile.back') }}
        </a>
    </div>

    <div class="flex items-start justify-between gap-3 mb-4 flex-wrap">
        <div>
            <p class="text-xs uppercase tracking-widest text-amber-600 mb-1">{{ __('borrower.loan_profile.label') }}</p>
            <h1 class="text-2xl sm:text-3xl font-bold">{{ $summary['product_name'] }}</h1>
            <p class="text-sm text-gray-500 mt-1 font-mono">{{ $summary['application_number'] }}</p>
            @if (! empty($summary['loan_number']))
                <p class="text-xs text-emerald-700 mt-1 font-mono">{{ __('borrower.loan_profile.loan_number') }}: {{ $summary['loan_number'] }}</p>
            @endif
        </div>
        <span class="inline-flex text-xs font-semibold rounded-full px-3 py-1.5 {{ $statusBadge }}">{{ $status['label'] }}</span>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    @if (($status['code'] ?? '') === 'offer_declined')
        <div class="mb-4 rounded-xl bg-amber-50 ring-amber-200 text-amber-900 ring-1 px-4 py-4 text-sm">
            <p class="font-semibold text-base">{{ __('borrower.applications_list.statuses.offer_declined') }}</p>
            @if (! empty($status['detail']))
                <p class="mt-1">{{ $status['detail'] }}</p>
            @endif
        </div>
    @elseif (($status['code'] ?? '') === 'rejected')
        <div class="mb-4 rounded-xl bg-red-50 ring-red-200 text-red-800 ring-1 px-4 py-4 text-sm">
            <p class="font-semibold text-base">{{ __('borrower.applications_list.statuses.rejected') }}</p>
            @if (! empty($status['detail']))
                <p class="mt-1">{{ $status['detail'] }}</p>
            @endif
        </div>
    @elseif (! empty($status['detail']))
        <div class="mb-4 rounded-xl bg-amber-50 ring-amber-200 text-amber-900 ring-1 px-4 py-3 text-sm">
            {{ $status['detail'] }}
        </div>
    @endif

    @include('site.borrower.loan-profile._action_panel', ['profile' => $profile])

    @if (! ($profile['is_draft'] ?? false) && ! empty($profile['disbursement_checklist']))
        @include('site.borrower.loan-profile._disbursement_checklist', ['checklist' => $profile['disbursement_checklist']])
    @endif

    {{-- Application summary --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-5 mb-6">
        <h2 class="font-semibold mb-4">{{ __('borrower.loan_profile.summary_title') }}</h2>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.applications_list.loan_type') }}</p>
                <p class="font-semibold mt-1">{{ $summary['loan_type'] }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.applications_list.amount') }}</p>
                <p class="font-semibold mt-1">{{ $summary['requested_amount'] ? format_money($summary['requested_amount']) : '—' }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.applications_list.tenure') }}</p>
                <p class="font-semibold mt-1">
                    @if (! empty($summary['requested_tenure']))
                        {{ __('borrower.applications_list.tenure_months', ['count' => $summary['requested_tenure']]) }}
                    @else
                        —
                    @endif
                </p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loan_profile.interest_rate') }}</p>
                <p class="font-semibold mt-1">{{ $summary['interest_rate_label'] ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.applications_list.created') }}</p>
                <p class="font-semibold mt-1">{{ optional($summary['created_at'])->format('d M Y') ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.applications_list.last_updated') }}</p>
                <p class="font-semibold mt-1">{{ optional($summary['updated_at'])->format('d M Y') ?? '—' }}</p>
            </div>
        </div>
    </div>

    {{-- Profile completion --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-5 mb-6">
        <div class="flex items-center justify-between gap-3 mb-4">
            <h2 class="font-semibold">{{ __('borrower.loan_profile.profile_completion') }}</h2>
            <span class="text-sm font-bold text-amber-700">{{ $progress['profile_percent'] ?? $progress['percent'] }}%</span>
        </div>
        <div class="h-2 bg-gray-100 rounded-full overflow-hidden mb-5">
            <div class="h-full bg-amber-500 transition-all" style="width: {{ $progress['profile_percent'] ?? $progress['percent'] }}%"></div>
        </div>

        <div class="grid md:grid-cols-2 gap-6">
            @if (! empty($progress['missing']))
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-2">{{ __('borrower.loan_profile.missing') }}</p>
                    <ul class="space-y-1.5">
                        @foreach ($progress['missing'] as $item)
                            <li class="text-sm text-gray-700 flex items-start gap-2">
                                <span class="text-amber-500 mt-0.5">○</span>
                                <span>{{ $item }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if (! empty($progress['completed']))
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-2">{{ __('borrower.loan_profile.completed') }}</p>
                    <ul class="space-y-1.5">
                        @foreach ($progress['completed'] as $item)
                            <li class="text-sm text-emerald-700 flex items-start gap-2">
                                <span class="mt-0.5">✓</span>
                                <span>{{ $item }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>

    @if (! empty($progress['timeline']))
        <div class="bg-white rounded-2xl border border-gray-200 p-5 mb-6">
            <h2 class="font-semibold mb-4">{{ $progress['timeline_title'] ?? __('borrower.loan_profile.application_progress') }}</h2>
            <ul class="space-y-2">
                @foreach ($progress['timeline'] as $step)
                    @php
                        $isLoanProgress = (bool) ($progress['is_loan_progress'] ?? false);
                        $complete = (bool) ($step['complete'] ?? false);
                        $current = (bool) ($step['current'] ?? false);
                        $icon = $complete ? '✓' : ($current ? '⏳' : ($isLoanProgress ? '○' : (($step['current'] ?? false) ? '→' : '○')));
                        $tone = $complete ? 'text-emerald-700' : ($current ? 'text-amber-900 font-semibold' : 'text-gray-600');
                    @endphp
                    <li class="flex items-start gap-2 text-sm {{ $tone }}">
                        <span class="mt-0.5">{{ $icon }}</span>
                        <span>{{ $step['label'] }}</span>
                    </li>
                @endforeach
            </ul>
            @if (($progress['is_loan_progress'] ?? false) && in_array($status['code'] ?? '', ['disbursed', 'closed'], true))
                <p class="mt-4 text-sm font-semibold text-emerald-700">{{ __('borrower.loan_progress.status_active') }}</p>
            @endif
        </div>
    @endif

    {{-- Missing requirements with upload links --}}
    @if (! empty($profile['missing_requirements']))
        <div class="bg-white rounded-2xl border border-gray-200 p-5 mb-6">
            <h2 class="font-semibold mb-1">{{ __('borrower.loan_profile.missing_requirements_title') }}</h2>
            <p class="text-xs text-gray-500 mb-4">{{ __('borrower.loan_profile.missing_requirements_hint') }}</p>
            <ul class="divide-y divide-gray-100">
                @foreach ($profile['missing_requirements'] as $requirement)
                    <li class="py-3 flex items-center justify-between gap-3 flex-wrap">
                        <span class="text-sm font-medium text-gray-900">{{ $requirement['label'] }}</span>
                        <a href="{{ $requirement['upload_url'] }}"
                           class="inline-flex items-center bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-4 py-2 rounded-full text-xs">
                            {{ __('borrower.loan_profile.upload') }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (! ($profile['is_draft'] ?? false) && $application)
        @include('site.borrower.loan-profile._submitted', ['profile' => $profile, 'application' => $application, 'customer' => $customer])
    @endif

</x-site.borrower-layout>
