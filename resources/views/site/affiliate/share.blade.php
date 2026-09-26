<x-site.affiliate-layout :title="brand_title(__('site.affiliate_portal.share_title'))" active="share" :hero="false">

    <section class="kf-premium-panel rounded-2xl p-6 sm:p-8 mb-6 relative overflow-hidden" data-kf-share-hero>
        <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_50%)]"></div>
        <div class="relative grid lg:grid-cols-[1.2fr_0.8fr] gap-6 items-center">
            <div class="space-y-4">
                <div>
                    <p class="text-xs uppercase tracking-widest text-brand-gold font-semibold">{{ __('site.affiliate_portal.promo_code') }}</p>
                    <p class="text-3xl font-bold font-mono tracking-wide mt-1" data-kf-promo-code>{{ $links['affiliate_code'] }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-widest text-brand-gold font-semibold">{{ __('site.affiliate_portal.referral_link') }}</p>
                    <p class="text-sm text-white/85 break-all mt-1" data-kf-promo-link>{{ $links['affiliate_link'] }}</p>
                </div>
                @unless ($eligibility['can_share'] ?? false)
                    <div class="rounded-2xl bg-white/10 ring-1 ring-white/20 px-4 py-3">
                        <p class="text-sm font-bold text-white">{{ ($shareLock['title'] ?? null) ?: __('site.affiliate_portal.eligibility_blocked') }}</p>
                        <p class="text-sm text-white/80 mt-1">{{ $shareLock['body'] ?? __('site.affiliate_portal.eligibility_blocked') }}</p>
                        @if (! empty($shareLock['cta_url']))
                            <a href="{{ $shareLock['cta_url'] }}" class="inline-flex mt-3 justify-center bg-brand-gold text-brand font-semibold px-4 py-2.5 rounded-xl text-sm">
                                {{ $shareLock['cta_label'] }} →
                            </a>
                        @endif
                    </div>
                @else
                    <x-site.referral-share
                        :link="$links['affiliate_link']"
                        :code="$links['affiliate_code']"
                        :message="$shareMessage"
                        :channels="['copy', 'whatsapp', 'sms', 'native']"
                    />
                @endunless
            </div>
            <div class="flex flex-col items-center justify-center">
                <img src="{{ $qrUrl }}" alt="{{ __('site.affiliate_portal.qr_alt') }}" class="size-44 rounded-2xl bg-white p-3 ring-1 ring-white/30" data-kf-promo-qr>
                <p class="text-xs text-white/70 mt-3 text-center">{{ __('site.affiliate_portal.qr_hint') }}</p>
            </div>
        </div>
    </section>

    <div class="grid lg:grid-cols-2 gap-6">
        <section class="rounded-2xl overflow-hidden ring-1 ring-brand/15 bg-white">
            <div class="kf-premium-panel rounded-none relative px-4 sm:px-5 py-3.5">
                <h2 class="font-bold text-white">{{ __('site.affiliate_portal.share_message') }}</h2>
            </div>
            <div class="p-5 space-y-3">
                <p class="text-sm text-gray-800 whitespace-pre-line" data-kf-promo-message>{{ $shareMessage }}</p>
                <p class="text-xs text-gray-500">{{ __('site.affiliate_portal.attribution_window_note', ['days' => $attributionWindow]) }}</p>
            </div>
        </section>

        <section class="rounded-2xl overflow-hidden ring-1 ring-brand/15 bg-white"
                 x-data="affiliatePromoEditor({
                    initial: @js(old('affiliate_code', $vendor->affiliate_code)),
                    startEditing: @js((bool) old('affiliate_code')),
                    checkUrl: @js(route('site.affiliate.share.promo-check')),
                    saveUrl: @js(route('site.affiliate.profile.update', ['section' => 'personal'])),
                    csrf: @js(csrf_token()),
                    labels: {
                        updating: @js(__('site.affiliate_portal.code_updating')),
                        updated: @js(__('site.affiliate_portal.code_updated')),
                        update: @js(__('site.affiliate_portal.save_code')),
                    },
                 })">
            <div class="kf-premium-panel rounded-none relative px-4 sm:px-5 py-3.5">
                <h2 class="font-bold text-white">{{ __('site.affiliate_portal.personalize_code') }}</h2>
            </div>
            <div class="p-5 space-y-4">
            @if ($canChangeCode)
                <div class="flex flex-wrap items-center gap-3" x-show="!editing">
                    <p class="text-2xl font-bold font-mono tracking-wide text-gray-900" data-kf-promo-code>{{ $vendor->affiliate_code }}</p>
                    <button type="button"
                            class="rounded-xl ring-1 ring-gray-200 px-3 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50"
                            @click="navigator.clipboard.writeText(@js($vendor->affiliate_code))">{{ __('site.affiliate_portal.copy_code') }}</button>
                    <button type="button" @click="editing = true"
                            class="rounded-xl bg-brand text-white px-3 py-2 text-sm font-semibold">{{ __('site.affiliate_portal.edit_code') }}</button>
                </div>
                <form method="POST" action="{{ route('site.affiliate.profile.update', ['section' => 'personal']) }}"
                      class="space-y-3" x-show="editing" x-cloak
                      @submit.prevent="save()">
                    @csrf @method('PUT')
                    <input type="hidden" name="focus" value="promo">
                    <label class="block text-xs font-medium text-gray-600">{{ __('site.affiliate_portal.promo_code') }}</label>
                    <input name="affiliate_code" x-model="code"
                           @blur="check()"
                           pattern="[A-Za-z0-9_-]{3,24}" maxlength="24"
                           class="w-full rounded-xl border-gray-200 ring-1 px-3 py-2.5 text-sm font-mono uppercase"
                           :class="error ? 'ring-red-300 border-red-300' : 'ring-gray-200'">
                    <p x-show="error" x-cloak x-text="error" class="text-xs text-red-600"></p>
                    @error('affiliate_code')<p class="text-xs text-red-600" x-show="!error">{{ $message }}</p>@enderror
                    <div class="flex flex-wrap gap-3">
                        <button type="submit" :disabled="saving || !!error"
                                class="bg-brand hover:bg-brand-light text-white font-semibold px-5 py-2.5 rounded-xl text-sm disabled:opacity-60"
                                x-text="saving ? labels.updating : labels.update"></button>
                        <button type="button" @click="editing = false; error = ''" class="rounded-xl ring-1 ring-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-800">{{ __('site.affiliate_portal.cancel_edit') }}</button>
                    </div>
                </form>
            @else
                <p class="text-2xl font-bold font-mono tracking-wide text-gray-900">{{ $vendor->affiliate_code }}</p>
                <p class="text-sm text-gray-600">
                    {{ $nextCodeChangeAt
                        ? __('site.affiliate_portal.code_cooldown_on', ['date' => $nextCodeChangeAt->timezone(config('app.timezone'))->translatedFormat('d M Y')])
                        : __('site.affiliate_portal.code_change_now') }}
                </p>
            @endif
            </div>
        </section>
    </div>

    @once
    @push('scripts')
    <script>
        function affiliatePromoEditor(config) {
            return {
                editing: !!config.startEditing,
                code: config.initial || '',
                error: '',
                saving: false,
                labels: config.labels || {},
                timer: null,
                check() {
                    const value = String(this.code || '').trim().toUpperCase();
                    this.code = value;
                    if (! value || value === String(config.initial || '').toUpperCase()) {
                        this.error = '';
                        return;
                    }
                    clearTimeout(this.timer);
                    this.timer = setTimeout(async () => {
                        try {
                            const res = await fetch(config.checkUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    Accept: 'application/json',
                                    'X-CSRF-TOKEN': config.csrf,
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                credentials: 'same-origin',
                                body: JSON.stringify({ affiliate_code: value }),
                            });
                            const data = await res.json().catch(() => ({}));
                            this.error = data.available === false || data.ok === false
                                ? (data.message || '')
                                : '';
                        } catch (e) {
                            this.error = '';
                        }
                    }, 280);
                },
                async save() {
                    if (this.saving || this.error) return;
                    this.saving = true;
                    if (typeof window.kfShowInlineSaving === 'function') {
                        window.kfShowInlineSaving(this.labels.updating || 'Updating…');
                    }
                    const body = new FormData();
                    body.append('_token', config.csrf);
                    body.append('_method', 'PUT');
                    body.append('focus', 'promo');
                    body.append('affiliate_code', this.code);
                    try {
                        const res = await fetch(config.saveUrl, {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-KF-Autosave': '1',
                            },
                            credentials: 'same-origin',
                            body,
                        });
                        const data = await res.json().catch(() => ({}));
                        if (! res.ok || data.ok === false) {
                            this.error = data.message || Object.values(data.errors || {})[0]?.[0] || '';
                            if (typeof window.kfShowSaveError === 'function') {
                                window.kfShowSaveError(this.error);
                            } else if (typeof window.kfHideSaving === 'function') {
                                window.kfHideSaving();
                            }
                            return;
                        }
                        if (typeof window.kfFlashInlineSaved === 'function') {
                            window.kfFlashInlineSaved(this.labels.updated || 'Updated');
                        }
                        const promo = data.promo || {};
                        const code = promo.code || this.code;
                        this.code = code;
                        config.initial = code;
                        this.editing = false;
                        document.querySelectorAll('[data-kf-promo-code]').forEach((el) => { el.textContent = code; });
                        if (promo.link) {
                            document.querySelectorAll('[data-kf-promo-link]').forEach((el) => { el.textContent = promo.link; });
                        }
                        if (promo.message) {
                            document.querySelectorAll('[data-kf-promo-message]').forEach((el) => { el.textContent = promo.message; });
                        }
                        if (promo.qr_url) {
                            document.querySelectorAll('[data-kf-promo-qr]').forEach((el) => { el.setAttribute('src', promo.qr_url); });
                        }
                    } catch (e) {
                        if (typeof window.kfHideSaving === 'function') window.kfHideSaving();
                    } finally {
                        this.saving = false;
                    }
                },
            };
        }
    </script>
    @endpush
    @endonce

</x-site.affiliate-layout>
