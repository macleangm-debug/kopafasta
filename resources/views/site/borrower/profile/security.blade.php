<x-site.borrower-layout :title="brand_title(__('borrower.profile.security'))" active="profile" content-width="wide">

    @php
        $prefs = auth()->user()->preferences ?? [];
        $notifPrefs = $prefs['notifications'] ?? [];
    @endphp

    <div>
        @include('site.borrower.profile._profile_shell', [
            'title' => __('borrower.profile.security'),
            'subtitle' => __('borrower.security_tab.subtitle'),
            'customer' => $customer,
            'active' => 'security',
        ])

        <div class="space-y-6">
            <x-site.profile-section-card :title="auth()->user()->pin_set_at ? __('borrower.security_tab.change_pin') : __('borrower.security_tab.set_pin')" :complete="(bool) auth()->user()->pin_set_at">
                <p class="text-sm text-gray-600 mb-4">{{ __('borrower.security_tab.pin_hint') }}</p>
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
            </x-site.profile-section-card>

            <x-site.profile-section-card :title="__('borrower.security_tab.trusted_devices')" :complete="$trustedDevices->isNotEmpty()">
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
            </x-site.profile-section-card>

            <x-site.profile-section-card :title="__('borrower.security_tab.notifications_title')" :complete="true">
                <p class="text-sm text-gray-600 mb-4">{{ __('borrower.security_tab.notifications_hint') }}</p>
                <form method="POST" action="{{ route('site.borrower.profile.notifications.update') }}" class="space-y-4">
                    @csrf @method('PUT')
                    @foreach ([
                        'loan_updates' => __('borrower.security_tab.notif_loan_updates'),
                        'payments'     => __('borrower.security_tab.notif_payments'),
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
            </x-site.profile-section-card>
        </div>
    </div>

</x-site.borrower-layout>
