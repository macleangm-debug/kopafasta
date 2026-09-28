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

    <div class="grid lg:grid-cols-[3fr_2fr] gap-4 lg:gap-5 items-start lg:items-stretch">
        <section class="rounded-2xl overflow-hidden ring-1 ring-brand/15 bg-white lg:h-full flex flex-col" x-data="{
            referralSheet: false,
            referralMenu: false,
            pop: { top: 0, left: 0 },
            place() {
                const r = this.$refs.referralBtn?.getBoundingClientRect();
                if (! r) return;
                this.pop = { top: r.bottom + 8, left: Math.max(12, Math.min(r.left, window.innerWidth - 300)) };
            }
        }">
            <div class="kf-premium-panel rounded-none relative px-4 sm:px-5 py-3">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-bold text-white">{{ __('site.affiliate_portal.share_message') }}</h2>
                    <button type="button" x-ref="referralBtn"
                            @click="if (window.matchMedia('(min-width: 1024px)').matches) { referralMenu = !referralMenu; place(); } else { referralSheet = true; }"
                            class="size-7 shrink-0 rounded-full bg-brand-gold text-brand ring-1 ring-white/40 grid place-items-center text-xs font-extrabold"
                            aria-label="{{ __('site.affiliate_portal.how_referrals_work') }}">i</button>
                </div>
            </div>
            <div class="px-4 sm:px-5 py-2.5 flex-1">
                <p class="text-sm text-gray-800 whitespace-pre-line leading-tight" data-kf-promo-message>{{ $shareMessage }}</p>
            </div>
            <x-site.bottom-sheet :title="__('site.affiliate_portal.how_referrals_work')" open="referralSheet">
                <p class="text-sm text-gray-700">{{ __('site.affiliate_portal.attribution_window_note', ['days' => $attributionWindow]) }}</p>
            </x-site.bottom-sheet>
            <template x-teleport="body">
                <div x-show="referralMenu" x-cloak x-transition
                     @click.outside="if (! $refs.referralBtn?.contains($event.target)) referralMenu = false"
                     class="fixed z-[70] w-72 rounded-2xl bg-white shadow-xl ring-1 ring-brand/10 p-4 text-left"
                     :style="`top:${pop.top}px;left:${pop.left}px`">
                    <p class="text-sm font-bold text-gray-900">{{ __('site.affiliate_portal.how_referrals_work') }}</p>
                    <p class="mt-2 text-sm text-gray-700">{{ __('site.affiliate_portal.attribution_window_note', ['days' => $attributionWindow]) }}</p>
                </div>
            </template>
        </section>

        <section id="promo-code" class="kf-premium-panel rounded-2xl relative overflow-hidden lg:h-full lg:flex lg:flex-col"
                 x-data="affiliatePromoEditor({
                    initial: @js(old('affiliate_code', $vendor->affiliate_code)),
                    startEditing: @js((bool) old('affiliate_code') && ($canChangeCode ?? false)),
                    canChange: @js((bool) ($canChangeCode ?? false)),
                    nextChangeLabel: @js(($nextCodeChangeAt && ! ($canChangeCode ?? false))
                        ? __('site.affiliate_portal.code_cooldown_on', [
                            'date' => $nextCodeChangeAt->timezone(app_display_timezone())->translatedFormat('d M Y'),
                        ])
                        : null),
                    checkUrl: @js(route('site.affiliate.share.promo-check')),
                    saveUrl: @js(route('site.affiliate.profile.update', ['section' => 'personal'])),
                    csrf: @js(csrf_token()),
                    consequence: @js(__('site.affiliate_portal.code_change_consequence')),
                    labels: {
                        updating: @js(__('site.affiliate_portal.code_updating')),
                        updated: @js(__('site.affiliate_portal.code_updated')),
                        save: @js(__('site.affiliate_portal.save_code_short')),
                        cancel: @js(__('site.affiliate_portal.cancel_edit')),
                        change: @js(__('site.affiliate_portal.change_action')),
                        copy: @js(__('site.affiliate_portal.copy_code')),
                        copied: @js(__('site.affiliate_portal.code_copied')),
                    },
                 })"
                 x-init="if (window.location.hash === '#promo-code' && canChange) { editing = true; }">
            <div class="relative px-4 sm:px-5 py-4 lg:flex-1 lg:flex lg:flex-col">
                <p class="text-[10px] uppercase tracking-[0.18em] text-brand-gold font-bold">{{ __('site.affiliate_portal.your_promo_code') }}</p>

                <div class="lg:flex-1 lg:flex lg:flex-col lg:items-center lg:justify-center lg:text-center py-4 lg:py-0 space-y-3">
                    {{-- Display mode: large code is the hero --}}
                    <p x-show="!editing"
                       class="text-3xl sm:text-5xl font-extrabold font-mono tracking-wide text-white leading-none"
                       data-kf-promo-code
                       x-text="code">{{ $vendor->affiliate_code }}</p>

                    {{-- Edit mode: same position, same large typography --}}
                    <input x-show="editing"
                           x-cloak
                           type="text"
                           name="affiliate_code_display"
                           x-model="code"
                           @input="onInput()"
                           @blur="check()"
                           maxlength="24"
                           autocomplete="off"
                           spellcheck="false"
                           class="w-full max-w-md mx-auto bg-transparent border-0 border-b-2 border-brand-gold/70 focus:border-brand-gold focus:ring-0 text-center text-3xl sm:text-5xl font-extrabold font-mono tracking-wide text-white leading-none px-1 py-1 placeholder:text-white/40"
                           :class="error ? 'border-rose-300' : ''"
                           placeholder="{{ __('site.affiliate_portal.promo_code') }}">

                    <p x-show="editing && error" x-cloak x-text="error" class="text-xs text-rose-100"></p>
                    @error('affiliate_code')
                        <p class="text-xs text-rose-100" x-show="editing && !error">{{ $message }}</p>
                    @enderror

                    <p x-show="editing" x-cloak class="text-xs text-white/80 leading-relaxed max-w-sm mx-auto">
                        {{ __('site.affiliate_portal.code_change_consequence') }}
                    </p>

                    {{-- Actions: Copy + Change, or Save · Cancel while editing --}}
                    <div class="flex flex-wrap items-center justify-center gap-2 pt-1" x-show="!editing">
                        <button type="button"
                                class="inline-flex rounded-xl bg-brand-gold text-brand px-3.5 py-2 text-sm font-bold"
                                @click="copyCode()">
                            <span x-text="copied ? labels.copied : labels.copy">{{ __('site.affiliate_portal.copy_code') }}</span>
                        </button>
                        <button type="button"
                                x-show="canChange"
                                x-cloak
                                class="inline-flex rounded-xl ring-1 ring-white/35 text-white px-3.5 py-2 text-sm font-bold hover:bg-white/10"
                                @click="editing = true">
                            {{ __('site.affiliate_portal.change_action') }}
                        </button>
                    </div>

                    <div class="flex flex-wrap items-center justify-center gap-3 pt-1" x-show="editing" x-cloak>
                        <button type="button"
                                @click="save()"
                                :disabled="saving || !!error || ! code || code === String(configInitial || '').toUpperCase()"
                                class="inline-flex items-center gap-2 rounded-xl bg-brand-gold text-brand px-4 py-2 text-sm font-bold disabled:opacity-60 disabled:cursor-not-allowed">
                            <svg x-show="saving" x-cloak class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                                <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"></path>
                            </svg>
                            <span x-text="saving ? labels.updating : labels.save">{{ __('site.affiliate_portal.save_code_short') }}</span>
                        </button>
                        <button type="button"
                                @click="cancelEdit()"
                                :disabled="saving"
                                class="text-sm font-semibold text-white/90 hover:text-white underline-offset-2 hover:underline disabled:opacity-50">
                            {{ __('site.affiliate_portal.cancel_edit') }}
                        </button>
                    </div>
                </div>

                <p x-show="!canChange && nextChangeLabel"
                   class="text-xs text-white/85 leading-relaxed mt-3"
                   x-text="nextChangeLabel">
                    @if ($nextCodeChangeAt && ! ($canChangeCode ?? false))
                        {{ __('site.affiliate_portal.code_cooldown_on', [
                            'date' => $nextCodeChangeAt->timezone(app_display_timezone())->translatedFormat('d M Y'),
                        ]) }}
                    @endif
                </p>

                <p class="text-xs text-white/60 leading-relaxed mt-2">{{ __('site.affiliate_portal.code_rules') }}</p>
            </div>
        </section>
    </div>

    @once
    @push('scripts')
    <script>
        function affiliatePromoEditor(config) {
            return {
                editing: !!config.startEditing,
                code: String(config.initial || '').toUpperCase(),
                configInitial: String(config.initial || '').toUpperCase(),
                error: '',
                saving: false,
                copied: false,
                canChange: !!config.canChange,
                nextChangeLabel: config.nextChangeLabel || '',
                labels: config.labels || {},
                timer: null,
                onInput() {
                    this.code = String(this.code || '').replace(/\s+/g, '').toUpperCase();
                    this.check();
                },
                cancelEdit() {
                    if (this.saving) return;
                    this.editing = false;
                    this.error = '';
                    this.code = this.configInitial;
                },
                copyCode() {
                    const value = this.code || this.configInitial;
                    navigator.clipboard.writeText(value).then(() => {
                        this.copied = true;
                        setTimeout(() => { this.copied = false; }, 1600);
                    }).catch(() => {});
                },
                check() {
                    const value = String(this.code || '').replace(/\s+/g, '').toUpperCase();
                    this.code = value;
                    if (! value || value === this.configInitial) {
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
                    const value = String(this.code || '').replace(/\s+/g, '').toUpperCase();
                    this.code = value;
                    if (! value || value === this.configInitial) return;

                    this.saving = true;
                    const body = new FormData();
                    body.append('_token', config.csrf);
                    body.append('_method', 'PUT');
                    body.append('focus', 'promo');
                    body.append('affiliate_code', value);
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
                            return;
                        }
                        const promo = data.promo || {};
                        const code = String(promo.code || value).toUpperCase();
                        this.code = code;
                        this.configInitial = code;
                        this.editing = false;
                        this.canChange = promo.can_change === true;
                        this.nextChangeLabel = promo.next_change_label || '';
                        document.querySelectorAll('[data-kf-promo-code]').forEach((el) => {
                            if (el.tagName !== 'INPUT') el.textContent = code;
                        });
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
                        this.error = config.labels?.updated ? '' : (this.error || '');
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
