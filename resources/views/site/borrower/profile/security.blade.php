<x-site.borrower-layout :title="brand_title(__('borrower.profile.title'))" active="profile">

    <div class="max-w-3xl">
        @include('site.borrower.profile._heading', [
            'title' => __('borrower.profile.title'),
            'subtitle' => __('borrower.security_tab.subtitle'),
        ])

        @include('site.borrower.profile._tabs', ['active' => 'security'])

        <div class="grid gap-6">
            <section class="bg-white rounded-2xl border border-gray-200 p-6">
                <h2 class="font-semibold mb-1">{{ auth()->user()->pin_set_at ? __('borrower.security_tab.change_pin') : __('borrower.security_tab.set_pin') }}</h2>
                <p class="text-sm text-gray-500 mb-4">{{ __('borrower.security_tab.pin_hint') }}</p>

                <form method="POST" action="{{ route('site.borrower.profile.pin.update') }}" class="grid sm:grid-cols-2 gap-4 max-w-lg">
                    @csrf @method('PUT')
                    @if (auth()->user()->pin_set_at)
                        <div class="sm:col-span-2">
                            <label class="block text-xs text-gray-600 mb-1">{{ __('borrower.security_tab.current_pin') }}</label>
                            <input type="password" name="current_pin" inputmode="numeric" maxlength="4" pattern="\d{4}" required
                                   class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm font-mono">
                        </div>
                    @endif
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">{{ __('borrower.security_tab.new_pin') }}</label>
                        <input type="password" name="pin" inputmode="numeric" maxlength="4" pattern="\d{4}" required
                               class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm font-mono">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">{{ __('borrower.security_tab.confirm_pin') }}</label>
                        <input type="password" name="pin_confirmation" inputmode="numeric" maxlength="4" pattern="\d{4}" required
                               class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm font-mono">
                    </div>
                    <div class="sm:col-span-2">
                        <button class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">{{ __('borrower.security_tab.update_pin') }}</button>
                    </div>
                </form>
            </section>

            <section class="bg-white rounded-2xl border border-gray-200 p-6">
                <h2 class="font-semibold mb-1">{{ __('borrower.security_tab.trusted_devices') }}</h2>
                <p class="text-sm text-gray-500 mb-4">{{ __('borrower.security_tab.trusted_hint') }}</p>

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
                                        · {{ __('borrower.security_tab.expires', ['date' => optional($device->expires_at)->format('d M Y') ?? '—']) }}
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
            </section>

            @unless (config('auth_portal.biometric_enabled'))
                <section class="rounded-xl bg-gray-50 ring-1 ring-gray-200 px-4 py-3 text-sm text-gray-600">
                    {{ __('borrower.security_tab.biometric_future') }}
                </section>
            @endunless
        </div>
    </div>

</x-site.borrower-layout>
