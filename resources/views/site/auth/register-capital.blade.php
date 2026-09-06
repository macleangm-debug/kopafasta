@php
    $kind = old('partner_kind', 'organisation');
    $countries = $countries ?? collect([(object) ['code' => 'TZ', 'name' => 'Tanzania']]);
@endphp
<x-site.layout :title="brand_title(__('site.invest.cta_apply'))" :seo="['indexable' => false]">
    <section class="min-h-screen grid lg:grid-cols-5 premium-gradient">
        <aside class="hidden lg:flex lg:col-span-2 relative overflow-hidden bg-gradient-to-br from-brand via-[#0f6b54] to-[#082f27] text-white p-10 flex-col">
            <a href="{{ route('site.invest') }}" class="relative"><x-site.brand-mark variant="light" /></a>
            <div class="relative mt-12">
                <p class="text-xs uppercase tracking-widest text-brand-gold font-semibold">{{ __('site.invest.eyebrow') }}</p>
                <h2 class="mt-2 text-3xl font-bold tracking-tight leading-tight">{{ __('site.invest.hero_title') }}</h2>
                <p class="mt-3 text-white/75 text-sm">{{ __('site.invest.hero_body') }}</p>
            </div>
            <ul class="relative mt-10 space-y-3 text-sm">
                @foreach ([__('site.invest.point_1'), __('site.invest.point_2'), __('site.invest.point_3')] as $point)
                    <li class="flex items-start gap-3"><span class="text-brand-gold font-black tracking-[-0.14em]">›››</span><span>{{ $point }}</span></li>
                @endforeach
            </ul>
        </aside>

        <div class="lg:col-span-3 flex items-start lg:items-center justify-center px-4 py-10 sm:px-10"
             x-data="{ kind: @js($kind), reviewOpen: false }">
            <div class="w-full max-w-2xl glass-card p-6 sm:p-10">
                <a href="{{ route('site.invest') }}" class="lg:hidden mb-6 inline-block"><x-site.brand-mark size="md" /></a>
                <h1 class="text-2xl font-bold text-gray-900">{{ __('site.invest.cta_apply') }}</h1>
                <p class="mt-1 text-sm text-gray-600">{{ __('site.invest.journey_body') }}</p>

                @if ($errors->any())
                    <div class="mt-6 p-3.5 rounded-xl bg-red-50 border border-red-200 text-sm text-red-700">
                        <ul class="list-disc ml-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('site.register.capital.post') }}" class="mt-6 space-y-5" @submit.prevent="reviewOpen = true" x-ref="capitalForm">
                    @csrf
                    <input type="hidden" name="partner_kind" :value="kind">

                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-brand mb-2">{{ __('site.invest.partner_kind') }}</p>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" @click="kind = 'individual'" :class="kind === 'individual' ? 'ring-brand bg-brand-muted text-brand' : 'ring-gray-200'" class="rounded-xl ring-1 px-3 py-3 text-sm font-semibold">{{ __('site.invest.kind_individual') }}</button>
                            <button type="button" @click="kind = 'organisation'" :class="kind === 'organisation' ? 'ring-brand bg-brand-muted text-brand' : 'ring-gray-200'" class="rounded-xl ring-1 px-3 py-3 text-sm font-semibold">{{ __('site.invest.kind_organisation') }}</button>
                        </div>
                    </div>

                    <div x-show="kind === 'organisation'" x-cloak class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('site.invest.org_name') }}</label>
                            <input name="organization" value="{{ old('organization') }}" class="w-full px-3.5 py-3 rounded-xl border border-gray-200 focus:border-brand focus:ring-2 focus:ring-brand/10 text-sm" :required="kind === 'organisation'">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('site.invest.org_type') }}</label>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                @foreach (['bank' => 'Bank', 'mfi' => 'MFI', 'dfi' => 'DFI', 'family_office' => 'Family office', 'asset_manager' => 'Asset manager', 'other' => 'Other'] as $v => $label)
                                    <label class="cursor-pointer">
                                        <input type="radio" name="org_type" value="{{ $v }}" {{ old('org_type', 'bank') === $v ? 'checked' : '' }} class="sr-only peer">
                                        <div class="px-3 py-2.5 rounded-xl ring-1 ring-gray-200 peer-checked:ring-brand peer-checked:bg-brand-muted text-sm font-medium text-center">{{ $label }}</div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('site.invest.contact_name') }}</label>
                            <input name="contact_name" value="{{ old('contact_name') }}" required class="w-full px-3.5 py-3 rounded-xl border border-gray-200 focus:border-brand focus:ring-2 focus:ring-brand/10 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('site.invest.contact_role') }}</label>
                            <input name="contact_role" value="{{ old('contact_role') }}" class="w-full px-3.5 py-3 rounded-xl border border-gray-200 focus:border-brand focus:ring-2 focus:ring-brand/10 text-sm">
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('site.feedback.email') }}</label>
                            <input type="email" name="email" value="{{ old('email') }}" required class="w-full px-3.5 py-3 rounded-xl border border-gray-200 focus:border-brand focus:ring-2 focus:ring-brand/10 text-sm">
                        </div>
                        <div>
                            <x-site.phone-input name="phone" label="{{ __('site.feedback.phone') }}" :value="old('phone')" variant="rounded" required />
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('site.invest.country') }}</label>
                            <select name="country" required class="w-full px-3.5 py-3 rounded-xl border border-gray-200 focus:border-brand focus:ring-2 focus:ring-brand/10 text-sm">
                                @foreach ($countries as $country)
                                    <option value="{{ $country->name }}" @selected(old('country', 'Tanzania') === $country->name)>{{ $country->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('site.invest.commitment') }}</label>
                            <select name="commitment_band" required class="w-full px-3.5 py-3 rounded-xl border border-gray-200 focus:border-brand focus:ring-2 focus:ring-brand/10 text-sm">
                                @foreach (['50k_250k' => '$50K–250K', '250k_1m' => '$250K–1M', '1m_5m' => '$1M–5M', '5m_plus' => '$5M+'] as $v => $label)
                                    <option value="{{ $v }}" @selected(old('commitment_band') === $v)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('site.invest.address') }}</label>
                        <input name="address" value="{{ old('address') }}" class="w-full px-3.5 py-3 rounded-xl border border-gray-200 focus:border-brand focus:ring-2 focus:ring-brand/10 text-sm">
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('site.auth.password') ?? 'Password' }}</label>
                            <input type="password" name="password" required class="w-full px-3.5 py-3 rounded-xl border border-gray-200 focus:border-brand focus:ring-2 focus:ring-brand/10 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('site.auth.password_confirm') ?? 'Confirm password' }}</label>
                            <input type="password" name="password_confirmation" required class="w-full px-3.5 py-3 rounded-xl border border-gray-200 focus:border-brand focus:ring-2 focus:ring-brand/10 text-sm">
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-brand hover:bg-brand-light text-white font-bold py-3.5 rounded-xl">{{ __('site.invest.review_submit') }}</button>
                </form>

                <x-site.action-panel open="reviewOpen" :title="__('site.invest.review_title')" size="md">
                    <p class="text-sm text-gray-600">{{ __('site.invest.review_body') }}</p>
                    <div class="mt-4 flex gap-2">
                        <button type="button" class="flex-1 rounded-xl ring-1 ring-gray-200 py-3 text-sm font-semibold" @click="reviewOpen = false">{{ __('site.partner_apply.back') }}</button>
                        <button type="button" class="flex-1 rounded-xl bg-brand text-white py-3 text-sm font-bold" @click="reviewOpen = false; $refs.capitalForm.removeAttribute('x-on:submit.prevent'); $refs.capitalForm.submit()">{{ __('site.invest.confirm_submit') }}</button>
                    </div>
                </x-site.action-panel>
            </div>
        </div>
    </section>
</x-site.layout>
