@php
    $customer = $review['customer'] ?? null;
    $product = $review['product'] ?? null;
    $authority = app(\App\Services\CreditAuthorityService::class);
    $whyRequired = $authority->managementRequirementReason($record)
        ?: data_get($record->credit_appraisal_payload, 'awaiting_management.reason');
    $committee = data_get($record->credit_appraisal_payload, 'committee_approval', []);
    $screeningType = $record->recommendation_type;
    $approvedAmount = (float) ($record->offered_amount ?: $record->recommended_amount ?: $record->requested_amount);
    $afford = $affordability ?? [];
    $risk = $review['risk'] ?? [];
    $guarantors = $review['guarantors'] ?? [];
    $collateral = $review['collateral'] ?? $record->collateralAssets;
    $grade = $customer?->grade_code ?? $customer?->grade ?? null;
    $trust = $review['trust'] ?? $customer?->trust_score ?? null;
    $plus = $customer?->plus_status ?? $customer?->has_plus ?? null;
@endphp

<section id="management-approval-pack" class="space-y-4 mb-6 scroll-mt-24">
    <div>
        <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">Management approval pack</p>
        <h2 class="text-lg font-bold text-gray-900 mt-0.5">Authorize this committee-approved facility</h2>
        <p class="text-sm text-gray-500 mt-0.5">
            Credit Committee has already decided. Confirm or send back — do not re-screen. Grade, Plus, and Trust inform the pack; they cannot skip this step.
        </p>
    </div>

    @if ($whyRequired)
        <div class="rounded-2xl bg-amber-50 ring-1 ring-amber-200 px-5 py-4">
            <p class="text-[10px] uppercase tracking-widest text-amber-800 font-bold">Why Management is required</p>
            <p class="text-sm font-semibold text-amber-950 mt-1">{{ $whyRequired }}</p>
        </div>
    @endif

    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 px-4 py-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500">Customer</p>
            <p class="font-bold text-gray-900 mt-1">{{ trim(($customer?->first_name ?? '').' '.($customer?->last_name ?? '')) ?: '—' }}</p>
            <p class="text-xs text-gray-500 mt-0.5">{{ $customer?->customer_number }}</p>
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 px-4 py-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500">Product</p>
            <p class="font-bold text-gray-900 mt-1">{{ $product?->name ?? '—' }}</p>
            <p class="text-xs text-gray-500 mt-0.5">{{ $product?->code }}</p>
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 px-4 py-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500">Requested</p>
            <p class="font-bold text-gray-900 mt-1">{{ format_money((float) $record->requested_amount) }}</p>
        </div>
        <div class="rounded-2xl bg-brand-muted/50 ring-1 ring-brand/15 px-4 py-4">
            <p class="text-[10px] uppercase tracking-widest text-brand">Committee amount</p>
            <p class="font-bold text-brand mt-1">{{ format_money($approvedAmount) }}</p>
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-3">
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 px-5 py-4 space-y-2">
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Screening recommendation</p>
            <p class="text-sm font-semibold text-gray-900">{{ $screeningType ? str_replace('_', ' ', ucfirst((string) $screeningType)) : '—' }}</p>
            @if ($record->recommended_amount)
                <p class="text-sm text-gray-600">Recommended {{ format_money((float) $record->recommended_amount) }}</p>
            @endif
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 px-5 py-4 space-y-2">
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Committee decision</p>
            <p class="text-sm font-semibold text-gray-900">{{ str_replace('_', ' ', ucfirst((string) ($committee['outcome'] ?? 'approve'))) }}</p>
            @if (! empty($committee['conditions']) || ! empty($committee['notes']))
                <p class="text-sm text-gray-600">{{ $committee['conditions'] ?? $committee['notes'] }}</p>
            @endif
        </div>
    </div>

    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 text-sm">
        <div class="rounded-2xl bg-white ring-1 ring-gray-100 px-4 py-3">
            <p class="text-[10px] uppercase tracking-widest text-gray-500">Affordability</p>
            <p class="font-semibold text-gray-900 mt-1">{{ ($afford['verdict'] ?? ($afford['pass'] ?? false ? 'pass' : '—')) }}</p>
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-gray-100 px-4 py-3">
            <p class="text-[10px] uppercase tracking-widest text-gray-500">Risk / Grade / Trust</p>
            <p class="font-semibold text-gray-900 mt-1">
                {{ $risk['band'] ?? '—' }}
                @if ($grade) · Grade {{ $grade }} @endif
                @if ($trust) · Trust {{ $trust }} @endif
            </p>
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-gray-100 px-4 py-3">
            <p class="text-[10px] uppercase tracking-widest text-gray-500">Collateral / guarantors</p>
            <p class="font-semibold text-gray-900 mt-1">
                {{ is_countable($collateral) ? count($collateral) : 0 }} pledged
                · {{ is_countable($guarantors) ? count($guarantors) : 0 }} guarantors
            </p>
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-gray-100 px-4 py-3">
            <p class="text-[10px] uppercase tracking-widest text-gray-500">Plus</p>
            <p class="font-semibold text-gray-900 mt-1">{{ $plus ? 'Member' : 'Not a Plus member' }}</p>
        </div>
    </div>

    @include('admin.loan-applications.review._collateral_portfolio', [
        'viewer' => \App\Services\CollateralCardService::VIEWER_MANAGEMENT,
    ])

    <div class="rounded-2xl bg-white ring-1 ring-brand/10 px-5 py-4">
        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold mb-3">Management decision</p>
        @include('admin.loan-applications._workflow_actions')
    </div>
</section>
