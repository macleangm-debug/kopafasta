<x-site.borrower-layout :title="brand_title(__('borrower.settings.title'))" active="settings" content-width="wide">

    @php
        $prefs = auth()->user()->preferences ?? [];
        $notifPrefs = $prefs['notifications'] ?? [];
        $displayName = $prefs['display_name'] ?? auth()->user()->name;
        $preferredChannel = $prefs['preferred_channel'] ?? 'in_app';
        $quietStart = $prefs['quiet_hours_start'] ?? '';
        $quietEnd = $prefs['quiet_hours_end'] ?? '';
        $preferredLocale = $prefs['preferred_locale'] ?? app()->getLocale();
    @endphp

    <div>
        <div class="mb-6">
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900">{{ __('borrower.settings.title') }}</h1>
            <p class="mt-1 text-sm text-gray-600">{{ __('borrower.settings.subtitle') }}</p>
        </div>

        @if (session('status'))
            <div
                x-data
                x-init="
                    $nextTick(() => window.showBorrowerFeedback({
                        title: @js(__('borrower.feedback.saved_title')),
                        message: @js(session('status')),
                        tone: 'success',
                    }));
                "
                class="sr-only"
                aria-hidden="true"
            ></div>
        @endif

        <div class="space-y-6">
            <x-site.profile-section-card
                :title="auth()->user()->pin_set_at ? __('borrower.security_tab.change_pin') : __('borrower.security_tab.set_pin')"
                :complete="(bool) auth()->user()->pin_set_at"
                :collapsible="true">
                <x-slot:view>
                    <p class="text-sm text-gray-600">{{ __('borrower.security_tab.pin_hint') }}</p>
                </x-slot:view>
                <x-slot:form>
                    <form method="POST" action="{{ route('site.borrower.profile.pin.update') }}" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @csrf @method('PUT')
                        @if (auth()->user()->pin_set_at)
                            <div class="sm:col-span-2 lg:col-span-3">
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
                        <div class="sm:col-span-2 lg:col-span-3">
                            <button class="bg-brand hover:bg-brand-light text-white font-semibold px-6 py-2.5 rounded-xl text-sm">{{ __('borrower.security_tab.update_pin') }}</button>
                        </div>
                    </form>
                </x-slot:form>
            </x-site.profile-section-card>

            <x-site.profile-section-card
                :title="__('borrower.security_tab.trusted_devices')"
                :complete="$trustedDevices->isNotEmpty()">
                <x-slot:view>
                    <p class="text-sm text-gray-600">{{ __('borrower.settings.trusted_devices_advice') }}</p>
                    <p class="mt-2 text-sm font-medium text-gray-800">
                        {{ __('borrower.settings.trusted_devices_count', ['count' => $trustedDevices->count()]) }}
                    </p>
                </x-slot:view>
                <x-slot:form>
                    <p class="text-sm text-gray-600 mb-4">{{ __('borrower.security_tab.trusted_hint') }}</p>
                    @if ($trustedDevices->isEmpty())
                        <p class="text-sm text-gray-500">{{ __('borrower.security_tab.no_devices') }}</p>
                    @else
                        <ul class="divide-y divide-gray-100">
                            @foreach ($trustedDevices as $device)
                                <li class="py-3 flex items-center justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ $device->name ?? __('borrower.security_tab.web_browser') }}</p>
                                        <p class="text-xs text-gray-500">
                                            {{ __('borrower.security_tab.last_used', ['time' => optional($device->last_used_at)->diffForHumans() ?? '—']) }}
                                        </p>
                                    </div>
                                    <form method="POST" action="{{ route('site.borrower.profile.devices.revoke', $device) }}"
                                          @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.security_tab.remove_title')), message: @js(__('borrower.security_tab.remove_message')), confirmLabel: @js(__('borrower.security_tab.remove')), confirmClass: 'bg-red-600 hover:bg-red-700 text-white' })">
                                        @csrf @method('DELETE')
                                        <button class="text-xs font-semibold text-red-600 hover:underline">{{ __('borrower.security_tab.remove') }}</button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-slot:form>
            </x-site.profile-section-card>

            <x-site.profile-section-card
                section-id="notifications"
                :title="__('borrower.security_tab.notifications_title')"
                :complete="true">
                <x-slot:view>
                    <p class="text-sm text-gray-600">{{ __('borrower.security_tab.notifications_hint') }}</p>
                    <p class="text-xs text-gray-500 mt-2">{{ __('borrower.security_tab.notif_credit_limit_hint') }}</p>
                </x-slot:view>
                <x-slot:form>
                    <form method="POST" action="{{ route('site.borrower.profile.notifications.update') }}" class="space-y-4">
                        @csrf @method('PUT')
                        @foreach ([
                            'loan_updates' => __('borrower.security_tab.notif_loan_updates'),
                            'guarantor_updates' => __('borrower.security_tab.notif_guarantor_updates'),
                            'payments'     => __('borrower.security_tab.notif_payments'),
                            'plus_goals'   => __('borrower.security_tab.notif_plus_goals'),
                            'plus_business'=> __('borrower.security_tab.notif_plus_business'),
                            'plus_learn'   => __('borrower.security_tab.notif_plus_learn'),
                            'plus_offers'  => __('borrower.security_tab.notif_plus_offers'),
                            'credit_limit_updates' => __('borrower.security_tab.notif_credit_limit'),
                            'promotions'   => __('borrower.security_tab.notif_promotions'),
                            'push'         => __('borrower.security_tab.notif_push'),
                        ] as $key => $label)
                            <label class="flex items-center justify-between gap-3 rounded-xl ring-1 ring-gray-200 px-4 py-3 cursor-pointer hover:bg-gray-50/80">
                                <span class="text-sm font-medium text-gray-800">{{ $label }}</span>
                                <input type="checkbox" name="notifications[{{ $key }}]" value="1"
                                       @checked($notifPrefs[$key] ?? true)
                                       class="rounded border-gray-300 text-brand focus:ring-brand size-5">
                            </label>
                        @endforeach
                        <button class="bg-brand hover:bg-brand-light text-white font-semibold px-6 py-2.5 rounded-xl text-sm">{{ __('borrower.profile.save') }}</button>
                    </form>
                </x-slot:form>
            </x-site.profile-section-card>

            <x-site.profile-section-card
                :title="__('borrower.settings.personalisation_title')"
                :complete="filled($displayName)">
                <x-slot:view>
                    <dl class="grid sm:grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-gray-500">{{ __('borrower.settings.fields.display_name') }}</dt>
                            <dd class="font-medium mt-0.5">{{ $displayName }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">{{ __('borrower.settings.fields.preferred_channel') }}</dt>
                            <dd class="font-medium mt-0.5">{{ __('borrower.settings.channels.'.$preferredChannel) }}</dd>
                        </div>
                    </dl>
                </x-slot:view>
                <x-slot:form>
                    <form method="POST" action="{{ route('site.borrower.settings.preferences') }}" class="space-y-4">
                        @csrf @method('PUT')
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.settings.fields.display_name') }}</label>
                            <input type="text" name="display_name" value="{{ old('display_name', $displayName) }}" maxlength="80"
                                   class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                        </div>
                        <div>
                            <x-site.profile-select
                                name="preferred_locale"
                                :label="__('borrower.settings.fields.language')"
                                :options="['en' => 'English', 'sw' => 'Kiswahili']"
                                :value="old('preferred_locale', $preferredLocale)"
                                :required="true"
                            />
                        </div>
                        <div>
                            <x-site.profile-select
                                name="preferred_channel"
                                :label="__('borrower.settings.fields.preferred_channel')"
                                :options="[
                                    'in_app' => __('borrower.settings.channels.in_app'),
                                    'sms' => __('borrower.settings.channels.sms'),
                                    'whatsapp' => __('borrower.settings.channels.whatsapp'),
                                ]"
                                :value="old('preferred_channel', $preferredChannel)"
                                :required="true"
                            />
                        </div>
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.settings.fields.quiet_start') }}</label>
                                <input type="time" name="quiet_hours_start" value="{{ old('quiet_hours_start', $quietStart) }}"
                                       class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.settings.fields.quiet_end') }}</label>
                                <input type="time" name="quiet_hours_end" value="{{ old('quiet_hours_end', $quietEnd) }}"
                                       class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                            </div>
                        </div>
                        <p class="text-xs text-gray-500">{{ __('borrower.settings.quiet_hours_hint') }}</p>
                        <button class="bg-brand hover:bg-brand-light text-white font-semibold px-6 py-2.5 rounded-xl text-sm">{{ __('borrower.profile.save') }}</button>
                    </form>
                </x-slot:form>
            </x-site.profile-section-card>
        </div>
    </div>

</x-site.borrower-layout>
