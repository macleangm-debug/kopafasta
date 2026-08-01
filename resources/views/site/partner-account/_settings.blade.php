@props([
    'partner' => null,
    'supportRoute' => null,
])

<div class="max-w-2xl space-y-6">
    <x-site.profile-section-card :title="__('site.partner_account.settings_locale')" :collapsible="false" :default-open="true">
        <p class="text-sm text-gray-600">{{ __('site.partner_account.settings_locale_hint') }}</p>
        <p class="text-xs text-gray-500 mt-2">{{ __('site.partner_account.settings_locale_control') }}</p>
    </x-site.profile-section-card>

    <x-site.profile-section-card :title="__('site.partner_account.settings_account')" :collapsible="false" :default-open="true">
        <dl class="text-sm space-y-3">
            @if ($partner)
                <div class="flex justify-between gap-3">
                    <dt class="text-gray-500">{{ __('site.partner_account.display_name') }}</dt>
                    <dd class="font-semibold text-gray-900 text-right">{{ $partner->name }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-gray-500">{{ __('site.partner_account.partner_code') }}</dt>
                    <dd class="font-mono font-semibold text-brand text-right">{{ $partner->vendor_number ?? $partner->partner_number ?? '—' }}</dd>
                </div>
                @if (filled($partner->email ?? null))
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500">{{ __('site.partner_account.email') }}</dt>
                        <dd class="text-gray-900 text-right">{{ $partner->email }}</dd>
                    </div>
                @endif
            @endif
        </dl>
        @if ($supportRoute)
            <a href="{{ $supportRoute }}" class="inline-flex mt-4 text-sm font-semibold text-brand hover:underline">
                {{ __('site.partner_account.contact_support') }} →
            </a>
        @endif
    </x-site.profile-section-card>
</div>
