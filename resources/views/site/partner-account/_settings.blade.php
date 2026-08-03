@props([
    'partner' => null,
    'supportRoute' => null,
    'pinUpdateRoute' => null,
])

@php
    $user = auth()->user();
    $hasPin = (bool) ($user?->pin_set_at);
@endphp

@if (session('status'))
    <div
        x-data
        x-init="
            $nextTick(() => window.showBorrowerFeedback && window.showBorrowerFeedback({
                title: @js(__('borrower.feedback.saved_title')),
                message: @js(session('status')),
                tone: 'success',
            }));
        "
        class="sr-only"
        aria-hidden="true"
    ></div>
@endif
@if ($errors->any())
    <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
@endif

<div class="max-w-2xl space-y-6">
    @if ($pinUpdateRoute)
        <x-site.profile-section-card
            :title="$hasPin ? __('borrower.security_tab.change_pin') : __('borrower.security_tab.set_pin')"
            :collapsible="true">
            <x-slot:view>
                <p class="text-sm text-gray-600">{{ __('site.partner_account.pin_hint') }}</p>
            </x-slot:view>
            <x-slot:form>
                <form method="POST" action="{{ $pinUpdateRoute }}" class="grid sm:grid-cols-2 gap-4">
                    @csrf @method('PUT')
                    @if ($hasPin)
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.security_tab.current_pin') }}</label>
                            <input type="password" name="current_pin" inputmode="numeric" maxlength="4" pattern="\d{4}" required
                                   class="w-full max-w-xs rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm font-mono">
                        </div>
                    @endif
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.security_tab.new_pin') }}</label>
                        <input type="password" name="pin" inputmode="numeric" maxlength="4" pattern="\d{4}" required
                               class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.security_tab.confirm_pin') }}</label>
                        <input type="password" name="pin_confirmation" inputmode="numeric" maxlength="4" pattern="\d{4}" required
                               class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm font-mono">
                    </div>
                    <div class="sm:col-span-2">
                        <button class="bg-brand hover:bg-brand-light text-white font-semibold px-6 py-2.5 rounded-xl text-sm">{{ __('borrower.security_tab.update_pin') }}</button>
                    </div>
                </form>
            </x-slot:form>
        </x-site.profile-section-card>
    @endif

    <x-site.profile-section-card :title="__('site.partner_account.settings_locale')" :collapsible="true">
        <x-slot:view>
            <p class="text-sm text-gray-600">{{ __('site.partner_account.settings_locale_hint') }}</p>
            <p class="text-xs text-gray-500 mt-2">{{ __('site.partner_account.settings_locale_control') }}</p>
        </x-slot:view>
        <x-slot:form>
            <p class="text-sm text-gray-600">{{ __('site.partner_account.settings_locale_hint') }}</p>
            <p class="text-xs text-gray-500 mt-2">{{ __('site.partner_account.settings_locale_control') }}</p>
        </x-slot:form>
    </x-site.profile-section-card>

    <x-site.profile-section-card :title="__('site.partner_account.settings_account')" :collapsible="true">
        <x-slot:view>
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
        </x-slot:view>
        <x-slot:form>
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
                @endif
            </dl>
            @if ($supportRoute)
                <a href="{{ $supportRoute }}" class="inline-flex mt-4 text-sm font-semibold text-brand hover:underline">
                    {{ __('site.partner_account.contact_support') }} →
                </a>
            @endif
        </x-slot:form>
    </x-site.profile-section-card>
</div>
