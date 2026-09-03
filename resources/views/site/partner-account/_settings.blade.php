@props([
    'partner' => null,
    'supportRoute' => null,
    'pinUpdateRoute' => null,
    'preferencesUpdateRoute' => null,
])

@php
    $user = auth()->user();
    $hasPin = (bool) ($user?->pin_set_at);
    $preferredLocale = data_get($user?->preferences, 'preferred_locale', $user?->locale ?? app()->getLocale());
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
        <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-5 sm:p-6 space-y-4">
            <div>
                <h2 class="text-lg font-bold text-gray-900">{{ $hasPin ? __('borrower.security_tab.change_pin') : __('borrower.security_tab.set_pin') }}</h2>
                <p class="text-sm text-gray-600 mt-1">{{ __('site.partner_account.pin_hint') }}</p>
            </div>
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
                    <button type="submit" class="bg-brand hover:bg-brand-light text-white font-semibold px-6 py-2.5 rounded-xl text-sm">{{ __('borrower.security_tab.update_pin') }}</button>
                </div>
            </form>
        </div>
    @endif

    @if ($preferencesUpdateRoute)
        <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-5 sm:p-6 space-y-4">
            <div>
                <h2 class="text-lg font-bold text-gray-900">{{ __('site.partner_account.settings_locale') }}</h2>
                <p class="text-sm text-gray-600 mt-1">{{ __('site.partner_account.settings_locale_hint') }}</p>
            </div>
            <form method="POST" action="{{ $preferencesUpdateRoute }}" class="space-y-4">
                @csrf @method('PUT')
                <x-site.profile-select
                    name="preferred_locale"
                    :label="__('site.partner_account.settings_locale')"
                    :options="['en' => 'English', 'sw' => 'Kiswahili']"
                    :value="old('preferred_locale', $preferredLocale)"
                    :required="true"
                />
                <button type="submit" class="bg-brand hover:bg-brand-light text-white font-semibold px-6 py-2.5 rounded-xl text-sm">{{ __('site.partner_account.settings_locale_save') }}</button>
            </form>
        </div>
    @else
        <div class="rounded-2xl bg-gray-50 ring-1 ring-gray-100 px-4 py-4 text-sm text-gray-600 space-y-2">
            <p class="font-semibold text-gray-900">{{ __('site.partner_account.settings_locale') }}</p>
            <p>{{ __('site.partner_account.settings_locale_hint') }}</p>
            <p class="text-xs text-gray-500">{{ __('site.partner_account.settings_locale_control') }}</p>
        </div>
    @endif

    @if ($supportRoute)
        <a href="{{ $supportRoute }}" class="inline-flex text-sm font-semibold text-brand hover:underline">
            {{ __('site.partner_account.contact_support') }} →
        </a>
    @endif
</div>
