@props([
    'partner',
    'portal',
    'profileRoute',
    'updateRoute',
    'layoutComponent',
    'title',
    'subtitle' => null,
    'eyebrow' => null,
    'accountTabs' => [],
])

@php
    $meta = $partner->metadata ?? [];
    $activity = is_array($meta['activity'] ?? null) ? $meta['activity'] : [];
@endphp

<x-dynamic-component :component="$layoutComponent" :title="brand_title($title)" active="profile">

    <x-site.partner-account-tabs active="profile" :tabs="$accountTabs" />

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    @include('site.partner-account._shell', [
        'partner' => $partner,
        'portal' => $portal,
        'active' => 'activity',
        'profileRoute' => $profileRoute,
    ])

    <x-site.profile-section-card
        section-id="section-activity"
        icon="💼"
        :title="__('site.partner_account.activity_section')"
        :complete="filled($activity['type'] ?? null)"
        :collapsible="true"
        :default-open="true">
        <x-slot:view>
            <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-xs text-gray-500">{{ __('site.partner_account.activity_type') }}</dt>
                    <dd class="font-semibold text-gray-900 mt-0.5">{{ $activity['type'] ?? '—' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-xs text-gray-500">{{ __('site.partner_account.activity_details') }}</dt>
                    <dd class="font-semibold text-gray-900 mt-0.5 whitespace-pre-wrap">{{ $activity['details'] ?? '—' }}</dd>
                </div>
            </dl>
        </x-slot:view>
        <x-slot:form>
            <form method="POST" action="{{ route($updateRoute, ['section' => 'activity']) }}" class="space-y-4"
                  data-kf-autosave
                  data-kf-autosave-saving="{{ __('borrower.document_upload.saving') }}"
                  data-kf-autosave-saved="{{ __('borrower.document_upload.saved') }}"
                  data-kf-autosave-fail="{{ __('borrower.document_upload.could_not_save') }}"
                  data-kf-autosave-retry="{{ __('borrower.document_upload.retry') }}"
                  data-kf-account-shell>
                @csrf @method('PUT')
                <div>
                    <label class="block text-xs font-semibold text-brand mb-1">{{ __('site.partner_account.activity_type') }}</label>
                    <input name="activity_type" value="{{ old('activity_type', $activity['type'] ?? '') }}"
                           class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm"
                           placeholder="Trading · Services · Agriculture…">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-brand mb-1">{{ __('site.partner_account.activity_details') }}</label>
                    <textarea name="activity_details" rows="3"
                              class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm">{{ old('activity_details', $activity['details'] ?? '') }}</textarea>
                </div>
            </form>
        </x-slot:form>
    </x-site.profile-section-card>

</x-dynamic-component>
