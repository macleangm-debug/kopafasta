<x-admin.layout
    :title="__('admin.application_drafts.view_application')"
    :heading="__('admin.application_drafts.view_application')"
    :subheading="$draft->product?->name ?? __('admin.application_drafts.title')">

    <div class="mb-4">
        <a href="{{ route('admin.loan-applications.incomplete') }}" class="text-sm text-gray-500 hover:text-gray-700">← {{ __('admin.application_drafts.title') }}</a>
    </div>

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
                        <dt class="text-xs uppercase tracking-widest text-gray-500">{{ __('admin.application_drafts.guarantor_status') }}</dt>
                        <dd class="mt-1 font-semibold text-gray-900">{{ $snapshot['guarantor_status'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-widest text-gray-500">{{ __('admin.application_drafts.current_step') }}</dt>
                        <dd class="mt-1 font-semibold text-gray-900">{{ $snapshot['current_step'] }}</dd>
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

            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-3">{{ __('admin.application_drafts.uploaded_documents') }}</h3>
                @if (! empty($snapshot['uploaded_documents']))
                    <ul class="space-y-2 text-sm text-gray-700">
                        @foreach ($snapshot['uploaded_documents'] as $doc)
                            <li class="flex items-center gap-2">
                                <span class="text-emerald-600">✓</span>
                                <span>{{ $doc }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm text-gray-500">{{ __('admin.application_drafts.no_documents_yet') }}</p>
                @endif
            </div>
        </div>

        <div class="space-y-4">
            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-5 text-sm space-y-3">
                <h3 class="font-semibold text-gray-900">{{ __('admin.application_drafts.customer') }}</h3>
                @if ($draft->customer)
                    <p>{{ $draft->customer->phone }}</p>
                    <p class="text-gray-500">{{ $draft->customer->email }}</p>
                    <a href="{{ route('admin.customers.show', $draft->customer) }}"
                       class="inline-flex text-amber-700 font-semibold hover:underline">{{ __('admin.application_drafts.view_customer_profile') }} →</a>
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
