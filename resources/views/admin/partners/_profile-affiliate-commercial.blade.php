@php
    $commercial = $affiliateCommercial ?? null;
    $canNegotiate = app(\App\Services\PartnerStaffService::class)->canNegotiateRates(auth()->user());
    $settingsRates = app(\App\Services\AffiliateCommercialTermsService::class)->settingsRates();
    $fmtPct = fn (float $v) => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.').'%';
@endphp
@if ($commercial)
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6" x-data="{ termsOpen: false, classOpen: false, termsStep: 'form', classStep: 'form', rateSource: @js($commercial['rate_source']) }">
        <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
            <div>
                <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.partners.commercial_title') }}</h3>
                <p class="text-xs text-gray-500 mt-0.5">{{ __('admin.partners.commercial_hint') }}</p>
            </div>
            @if ($record->isPremiumAffiliate())
                <x-site.grade-badge grade="premium" :label="app(\App\Services\AffiliateSettingsService::class)->premiumBadgeLabel()" size="sm" />
            @endif
        </div>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
            <div>
                <dt class="text-[10px] uppercase tracking-widest text-brand/60 font-semibold">{{ __('admin.partners.commercial_type') }}</dt>
                <dd class="mt-1 font-semibold text-gray-900">{{ $commercial['type_label'] }}</dd>
            </div>
            <div>
                <dt class="text-[10px] uppercase tracking-widest text-brand/60 font-semibold">{{ __('admin.partners.commercial_rate_source') }}</dt>
                <dd class="mt-1 font-semibold text-gray-900">{{ $commercial['rate_source_label'] }}</dd>
            </div>
            <div>
                <dt class="text-[10px] uppercase tracking-widest text-brand/60 font-semibold">{{ __('admin.partners.commercial_effective_from') }}</dt>
                <dd class="mt-1 font-semibold text-gray-900">{{ $commercial['effective_from']?->format('d M Y') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-[10px] uppercase tracking-widest text-brand/60 font-semibold">{{ __('admin.partners.commercial_contract') }}</dt>
                <dd class="mt-1">
                    <a href="{{ $commercial['agreement_url'] }}" class="text-sm font-semibold text-brand hover:underline">{{ __('admin.partners.commercial_view_agreement') }}</a>
                </dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-[10px] uppercase tracking-widest text-brand/60 font-semibold">{{ __('admin.partners.commercial_effective_rates') }}</dt>
                <dd class="mt-1 font-semibold text-gray-900">
                    {{ __('admin.partners.commercial_rate_line', [
                        'registration' => $fmtPct($commercial['rates']['registration_discount_percent']),
                        'application' => $fmtPct($commercial['rates']['application_discount_percent']),
                        'commission' => $fmtPct($commercial['rates']['affiliate_commission_percent']),
                        'plus' => $fmtPct($commercial['rates']['plus_discount_percent']),
                    ]) }}
                </dd>
            </div>
            @if ($commercial['note'])
                <div class="sm:col-span-2">
                    <dt class="text-[10px] uppercase tracking-widest text-brand/60 font-semibold">{{ __('admin.partners.commercial_note') }}</dt>
                    <dd class="mt-1 text-sm text-gray-700">{{ $commercial['note'] }}</dd>
                </div>
            @endif
        </dl>

        <div class="mt-5 flex flex-wrap gap-2">
            @if ($canNegotiate && $record->isPremiumAffiliate())
                <button type="button" @click="termsOpen = true; termsStep = 'form'"
                        class="inline-flex items-center rounded-lg bg-brand text-white text-sm font-semibold px-3 py-2">
                    {{ __('admin.partners.commercial_change_terms') }}
                </button>
            @endif
            <button type="button" @click="classOpen = true; classStep = 'form'"
                    class="inline-flex items-center rounded-lg bg-white ring-1 ring-gray-200 text-sm font-semibold text-gray-800 px-3 py-2">
                {{ __('admin.partners.commercial_change_class') }}
            </button>
        </div>

        @if ($canNegotiate && $record->isPremiumAffiliate())
            <x-site.action-panel :title="__('admin.partners.commercial_change_terms')" open="termsOpen">
                <form method="POST" action="{{ route('admin.partners.affiliate-commercial-terms', $record) }}" class="space-y-4" data-no-draft>
                    @csrf
                    <input type="hidden" name="confirmed" value="1">
                    <div x-show="termsStep === 'form'" class="space-y-3">
                        <p class="text-sm text-gray-700">{{ __('admin.partners.commercial_change_terms_body', ['name' => $record->name]) }}</p>
                        <label class="flex items-start gap-3 rounded-xl ring-1 ring-gray-200 px-3 py-2.5">
                            <input type="radio" name="rate_source" value="standard" x-model="rateSource" class="mt-0.5 text-brand">
                            <span class="text-sm">{{ __('admin.partners.commercial_source_standard') }} — {{ $fmtPct($settingsRates['affiliate_commission_percent']) }}</span>
                        </label>
                        <label class="flex items-start gap-3 rounded-xl ring-1 ring-gray-200 px-3 py-2.5">
                            <input type="radio" name="rate_source" value="negotiated" x-model="rateSource" class="mt-0.5 text-brand">
                            <span class="text-sm">{{ __('admin.partners.commercial_source_negotiated') }}</span>
                        </label>
                        <div x-show="rateSource === 'negotiated'" class="grid sm:grid-cols-2 gap-3">
                            <x-admin.input name="registration_discount_percent" label="Registration %" type="number" step="0.01" :value="$commercial['rates']['registration_discount_percent']" />
                            <x-admin.input name="application_discount_percent" label="Application %" type="number" step="0.01" :value="$commercial['rates']['application_discount_percent']" />
                            <x-admin.input name="affiliate_commission_percent" label="Commission %" type="number" step="0.01" :value="$commercial['rates']['affiliate_commission_percent']" />
                            <x-admin.input name="plus_discount_percent" label="Plus %" type="number" step="0.1" :value="$commercial['rates']['plus_discount_percent']" />
                        </div>
                        <x-admin.input name="effective_from" :label="__('admin.partners.commercial_effective_from')" type="date" :value="now()->toDateString()" required />
                        <label class="block text-sm font-semibold text-gray-800">
                            {{ __('admin.partners.commercial_reason') }}
                            <textarea name="reason" required minlength="8" maxlength="500" class="mt-1 w-full rounded-xl border-gray-300 text-sm"></textarea>
                        </label>
                        <label class="block text-sm font-semibold text-gray-800">
                            {{ __('admin.partners.commercial_note') }}
                            <textarea name="note" maxlength="500" class="mt-1 w-full rounded-xl border-gray-300 text-sm">{{ $commercial['note'] }}</textarea>
                        </label>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" @click="termsStep = 'review'" class="inline-flex items-center px-4 py-2.5 rounded-xl bg-brand text-white text-sm font-bold">
                                {{ __('admin.partners.commercial_review') }}
                            </button>
                            <button type="button" @click="termsOpen = false" class="inline-flex items-center px-4 py-2.5 rounded-xl bg-white ring-1 ring-gray-200 text-sm font-semibold">
                                {{ __('admin.partners.commercial_cancel') }}
                            </button>
                        </div>
                    </div>
                    <div x-show="termsStep === 'review'" x-cloak class="space-y-3">
                        <p class="text-sm text-gray-700">{{ __('admin.partners.commercial_confirm_terms', ['name' => $record->name]) }}</p>
                        <p class="text-xs text-gray-500">{{ __('admin.partners.commercial_confirm_terms_note') }}</p>
                        <div class="flex flex-wrap gap-2">
                            <button type="submit" class="inline-flex items-center px-4 py-2.5 rounded-xl bg-brand text-white text-sm font-bold">
                                {{ __('admin.partners.commercial_confirm') }}
                            </button>
                            <button type="button" @click="termsStep = 'form'" class="inline-flex items-center px-4 py-2.5 rounded-xl bg-white ring-1 ring-gray-200 text-sm font-semibold">
                                {{ __('admin.partners.commercial_back') }}
                            </button>
                        </div>
                    </div>
                </form>
            </x-site.action-panel>
        @endif

        <x-site.action-panel :title="__('admin.partners.commercial_change_class')" open="classOpen">
            <form method="POST" action="{{ route('admin.partners.affiliate-classification', $record) }}" class="space-y-4" data-no-draft>
                @csrf
                <input type="hidden" name="confirmed" value="1">
                <div x-show="classStep === 'form'" class="space-y-3">
                    <p class="text-sm text-gray-700">{{ __('admin.partners.commercial_change_class_body', [
                        'name' => $record->name,
                        'from' => $commercial['type_label'],
                        'to' => $record->isPremiumAffiliate() ? __('site.affiliate_portal.standard_partner') : __('site.affiliate_portal.premium_partner'),
                    ]) }}</p>
                    <input type="hidden" name="affiliate_premium" value="{{ $record->isPremiumAffiliate() ? '0' : '1' }}">
                    <label class="block text-sm font-semibold text-gray-800">
                        {{ __('admin.partners.commercial_reason') }}
                        <textarea name="reason" required minlength="8" maxlength="500" class="mt-1 w-full rounded-xl border-gray-300 text-sm"></textarea>
                    </label>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" @click="classStep = 'review'" class="inline-flex items-center px-4 py-2.5 rounded-xl bg-brand text-white text-sm font-bold">
                            {{ __('admin.partners.commercial_review') }}
                        </button>
                        <button type="button" @click="classOpen = false" class="inline-flex items-center px-4 py-2.5 rounded-xl bg-white ring-1 ring-gray-200 text-sm font-semibold">
                            {{ __('admin.partners.commercial_cancel') }}
                        </button>
                    </div>
                </div>
                <div x-show="classStep === 'review'" x-cloak class="space-y-3">
                    <p class="text-sm text-gray-700">{{ __('admin.partners.commercial_confirm_class', [
                        'name' => $record->name,
                        'to' => $record->isPremiumAffiliate() ? __('site.affiliate_portal.standard_partner') : __('site.affiliate_portal.premium_partner'),
                    ]) }}</p>
                    <p class="text-xs text-gray-500">{{ __('admin.partners.commercial_confirm_class_note') }}</p>
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="inline-flex items-center px-4 py-2.5 rounded-xl bg-brand text-white text-sm font-bold">
                            {{ __('admin.partners.commercial_confirm') }}
                        </button>
                        <button type="button" @click="classStep = 'form'" class="inline-flex items-center px-4 py-2.5 rounded-xl bg-white ring-1 ring-gray-200 text-sm font-semibold">
                            {{ __('admin.partners.commercial_back') }}
                        </button>
                    </div>
                </div>
            </form>
        </x-site.action-panel>
    </div>
@endif
