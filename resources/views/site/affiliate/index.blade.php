<x-site.layout :title="brand_title(__('site.affiliate.title'))">
    <section class="relative overflow-hidden premium-gradient border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-20 grid lg:grid-cols-2 gap-12 items-center">
            <div>
                <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-2">{{ __('site.affiliate.title') }}</p>
                <h1 class="text-3xl sm:text-4xl font-bold tracking-tight text-brand">{{ __('site.affiliate.hero_title') }}</h1>
                <p class="mt-4 text-gray-600 leading-relaxed">{{ __('site.affiliate.hero_body') }}</p>
                <ul class="mt-8 space-y-3">
                    @foreach (['benefit_1', 'benefit_2', 'benefit_3', 'benefit_4'] as $key)
                        <li class="flex items-center gap-3 text-sm text-gray-700 glass-card px-4 py-3">
                            <span class="size-8 rounded-full bg-brand text-white grid place-items-center shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                            </span>
                            {{ __('site.affiliate.'.$key) }}
                        </li>
                    @endforeach
                </ul>
                <a href="#apply" class="mt-8 inline-flex items-center gap-2 bg-brand hover:bg-brand-light text-white font-semibold px-6 py-3 rounded-xl transition shadow-md">
                    {{ __('site.affiliate.cta_apply') }}
                </a>
            </div>
            <div class="relative">
                <div class="rounded-3xl overflow-hidden shadow-2xl aspect-[4/3] bg-brand-muted ring-1 ring-white/50">
                    <img src="https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?auto=format&fit=crop&w=800&q=80" alt="" class="w-full h-full object-cover">
                </div>
                <div class="absolute -bottom-4 -right-2 sm:right-4 glass-card p-5 w-52">
                    <p class="text-[10px] uppercase tracking-wider text-gray-500">{{ __('site.affiliate.step_4') }}</p>
                    <p class="text-xl font-bold text-brand mt-1 tabular-nums">TZS 2,450,000</p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold text-center mb-10">{{ __('site.affiliate.how_it_works') }}</h2>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
                @foreach ([['step_1', 'step_1_body', '1'], ['step_2', 'step_2_body', '2'], ['step_3', 'step_3_body', '3'], ['step_4', 'step_4_body', '4']] as [$title, $body, $num])
                    <div class="glass-card p-6 text-center">
                        <span class="size-10 rounded-full bg-brand text-white font-bold grid place-items-center mx-auto">{{ $num }}</span>
                        <h3 class="mt-4 font-bold text-gray-900">{{ __('site.affiliate.'.$title) }}</h3>
                        <p class="mt-2 text-sm text-gray-600">{{ __('site.affiliate.'.$body) }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="premium-gradient py-16 lg:py-24 border-y border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-2 gap-8 mb-16">
            <div class="glass-card p-8">
                <h2 class="text-xl font-bold text-brand">{{ __('site.affiliate.commission_title') }}</h2>
                <p class="mt-3 text-gray-600 leading-relaxed">{{ __('site.affiliate.commission_body') }}</p>
            </div>
            <div class="glass-card p-8">
                <h2 class="text-xl font-bold text-brand">{{ __('site.affiliate.portal_title') }}</h2>
                <p class="mt-3 text-gray-600 leading-relaxed">{{ __('site.affiliate.portal_body') }}</p>
                <a href="{{ route('site.login', ['portal' => 'partner']) }}" class="mt-4 inline-flex text-sm font-semibold text-brand hover:underline">{{ __('site.nav.partner_log_in') }} →</a>
            </div>
        </div>

        <div id="apply" class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-8">
                <p class="text-xs uppercase tracking-widest text-brand font-semibold">{{ __('site.affiliate.cta_apply') }}</p>
                <h2 class="mt-2 text-2xl sm:text-3xl font-bold text-gray-900">{{ __('site.affiliate_apply.title') }}</h2>
                <p class="mt-3 text-gray-600 max-w-2xl mx-auto">{{ __('site.affiliate_apply.subtitle') }}</p>
            </div>

            <div class="glass-card p-8 lg:p-10 shadow-xl ring-1 ring-brand/10">
                <p class="text-sm text-gray-600 rounded-xl bg-white/70 border border-brand/10 p-4">{{ __('site.affiliate.after_approval') }}</p>

                @if ($errors->any())
                    <div class="mt-6 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">
                        <ul class="list-disc ml-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('site.affiliate.apply.post') }}" class="mt-8 space-y-5"
                      x-data="{ category: @js(old('applicant_category', 'individual')) }">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('site.affiliate_apply.category') }}</label>
                        <p class="text-xs text-gray-500 mb-3">{{ __('site.affiliate.type_hint') }}</p>
                        <div class="grid sm:grid-cols-3 gap-3">
                            @foreach (['individual' => 'type_individual', 'company' => 'type_company', 'institution' => 'type_institution'] as $value => $labelKey)
                                <label class="cursor-pointer rounded-xl border p-4 text-center transition"
                                       :class="category === '{{ $value }}' ? 'border-brand bg-brand-muted ring-2 ring-brand/20' : 'border-gray-200 hover:border-brand/40'">
                                    <input type="radio" name="applicant_category" value="{{ $value }}" class="sr-only" x-model="category">
                                    <span class="text-sm font-semibold text-gray-900">{{ __('site.affiliate.'.$labelKey) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('site.affiliate_apply.full_name') }}</label>
                        <input name="full_name" value="{{ old('full_name') }}" required class="w-full rounded-xl border-gray-200 px-3 py-2.5 text-sm focus:border-brand focus:ring-brand/10">
                    </div>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('site.affiliate_apply.email') }}</label>
                            <input type="email" name="email" value="{{ old('email') }}" required class="w-full rounded-xl border-gray-200 px-3 py-2.5 text-sm focus:border-brand focus:ring-brand/10">
                        </div>
                        <div>
                            <x-site.phone-input name="phone" :label="__('site.affiliate_apply.phone')" :value="old('phone')" :required="true" />
                        </div>
                    </div>
                    <div x-show="category !== 'individual'" x-cloak>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('site.affiliate_apply.business_name') }}</label>
                        <input name="business_name" value="{{ old('business_name') }}" :required="category !== 'individual'"
                               class="w-full rounded-xl border-gray-200 px-3 py-2.5 text-sm focus:border-brand focus:ring-brand/10">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('site.affiliate_apply.region') }}</label>
                        <select name="region" class="w-full rounded-xl border-gray-200 px-3 py-2.5 text-sm focus:border-brand focus:ring-brand/10">
                            <option value="">{{ __('site.affiliate_apply.select_region') }}</option>
                            @foreach ($regions as $region)
                                <option value="{{ $region }}" @selected(old('region') === $region)>{{ $region }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('site.affiliate_apply.message') }}</label>
                        <textarea name="message" rows="4" class="w-full rounded-xl border-gray-200 px-3 py-2.5 text-sm focus:border-brand focus:ring-brand/10" placeholder="{{ __('site.affiliate_apply.message_placeholder') }}">{{ old('message') }}</textarea>
                    </div>
                    <button class="w-full sm:w-auto bg-brand hover:bg-brand-light text-white font-bold px-8 py-3 rounded-xl transition shadow-md">{{ __('site.affiliate_apply.submit') }}</button>
                </form>
            </div>
        </div>
    </section>
</x-site.layout>
