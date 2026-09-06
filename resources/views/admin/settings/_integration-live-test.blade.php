@php
    $partnerKey = $partnerKey ?? '';
    $isProductionLivePayIn = $partnerKey === 'payin'
        && app()->isProduction()
        && ! payment_gateway_is_dummy()
        && (\App\Models\Setting::get('payin.environment') === 'production');
@endphp

<section id="live-test" class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
    <div class="bg-gradient-to-br from-slate-950 via-brand to-brand-light px-5 py-4 text-white">
        <p class="text-[10px] uppercase tracking-[0.18em] text-brand-gold font-semibold">Live test</p>
        <h3 class="mt-1 text-lg font-bold">{{ $partner['label'] ?? 'Partner' }} operational rehearsal</h3>
        <p class="mt-1 text-sm text-white/75">Exercises the real {{ $partner['label'] ?? 'provider' }} rail — not a credentials-only health check.</p>
    </div>

    <div class="p-5">
        @if ($partnerKey === 'payin')
            <form method="POST" action="{{ route('admin.settings.integrations.live-test') }}" class="space-y-4"
                  @submit.prevent="window.confirmForm($el, {
                      title: @js($isProductionLivePayIn ? 'Start real PayIn rehearsal' : 'Start PayIn live test'),
                      message: @js($isProductionLivePayIn
                          ? 'This is a real production payment. The entered phone may receive a real USSD/payment request and successful payment will move real money. Continue only for a controlled rehearsal.'
                          : 'Create a controlled test payment and continue through the canonical payment.show journey.'),
                      confirmLabel: 'Continue to payment.show',
                      tone: @js($isProductionLivePayIn ? 'warning' : 'confirm'),
                      confirmClass: @js($isProductionLivePayIn ? 'bg-red-600 hover:bg-red-700 text-white' : 'bg-brand hover:bg-brand-light text-white'),
                  })">
                @csrf
                <input type="hidden" name="suite" value="payment">
                <input type="hidden" name="partner" value="payin">
                <x-admin.phone-input name="phone" label="Mobile number" :required="true" lockedCountry="TZ" />
                <x-admin.money-input name="amount" label="Amount (TZS)" :value="1000" :decimals="0" help="Default rehearsal amount is 1,000 TZS." />
                @if ($isProductionLivePayIn)
                    <label class="flex items-start gap-2 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-3 py-3 text-sm text-amber-950">
                        <input type="checkbox" name="confirm_production_payment" value="1" class="mt-1 rounded border-amber-300 text-brand focus:ring-brand" required>
                        <span><strong>I understand this is a real production payment</strong> and may move real money.</span>
                    </label>
                @endif
                <button type="submit" class="inline-flex rounded-xl bg-brand text-white text-sm font-bold px-5 py-3 hover:bg-brand-light">
                    Review test payment → Continue to payment.show
                </button>
            </form>
        @elseif ($partnerKey === 'unitxt')
            <form method="POST" action="{{ route('admin.settings.integrations.live-test') }}" class="space-y-4"
                  x-data="{ message: '', segments() { const len = (this.message || '').length; return Math.max(1, Math.ceil(len / 160)); } }"
                  @submit.prevent="window.confirmForm($el, {
                      title: 'Send Unitxt test SMS',
                      message: 'This sends a real SMS through the configured Unitxt gateway.',
                      confirmLabel: 'Send test SMS',
                      tone: 'warning',
                  })">
                @csrf
                <input type="hidden" name="suite" value="messaging">
                <input type="hidden" name="partner" value="unitxt">
                <x-admin.phone-input name="phone" label="Recipient" :required="true" lockedCountry="TZ" />
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Message</label>
                    <textarea name="message" x-model="message" rows="4" required maxlength="320"
                              class="w-full rounded-xl border-gray-200 text-sm focus:border-brand focus:ring-brand/20"
                              placeholder="Kopafasta Unitxt live test"></textarea>
                    <p class="mt-1 text-xs text-gray-500">
                        <span x-text="(message || '').length"></span> characters ·
                        <span x-text="segments()"></span> SMS segment<span x-show="segments() !== 1">s</span>
                    </p>
                </div>
                <button type="submit" class="inline-flex rounded-xl bg-brand text-white text-sm font-bold px-5 py-3 hover:bg-brand-light">
                    Send test SMS
                </button>
            </form>
        @elseif ($partnerKey === 'email_smtp')
            <form method="POST" action="{{ route('admin.settings.integrations.live-test') }}" class="space-y-4"
                  @submit.prevent="window.confirmForm($el, {
                      title: 'Send Email live test',
                      message: 'This sends a real email through the configured mail provider/SMTP.',
                      confirmLabel: 'Send test email',
                      tone: 'warning',
                  })">
                @csrf
                <input type="hidden" name="suite" value="email">
                <input type="hidden" name="partner" value="email_smtp">
                <x-admin.input name="email" label="To email" type="email" required />
                <x-admin.input name="subject" label="Subject" :value="'Kopafasta email live test'" />
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Message</label>
                    <textarea name="message" rows="4" class="w-full rounded-xl border-gray-200 text-sm" placeholder="Controlled Email (SMTP) live test."></textarea>
                </div>
                <button type="submit" class="inline-flex rounded-xl bg-brand text-white text-sm font-bold px-5 py-3 hover:bg-brand-light">
                    Send test email
                </button>
            </form>
            <p class="mt-3 text-xs text-gray-500">
                Credentials: <a href="{{ route('admin.settings.gateways') }}" class="font-semibold text-brand hover:underline">SMS / Email gateways</a>.
                One email delivery engine serves borrowers, affiliates, capital, recovery and other approved recipients.
            </p>
        @elseif ($partnerKey === 'crb')
            <form method="POST" action="{{ route('admin.settings.integrations.live-test') }}" class="space-y-4"
                  @submit.prevent="window.confirmForm($el, {
                      title: 'Run CRB enquiry',
                      message: 'This performs an actual CRB enquiry using the configured driver and may incur a provider charge on live credentials.',
                      confirmLabel: 'Run CRB live test',
                      tone: 'warning',
                  })">
                @csrf
                <input type="hidden" name="suite" value="crb">
                <input type="hidden" name="partner" value="crb">
                <x-admin.input name="nida" label="NIDA" placeholder="XXXXXXXX-XXXXX-XXXXX-XX" help="Uses the same NIDA format validation as borrower identity." />
                <x-admin.input name="full_name" label="Full name (optional)" />
                <x-admin.input name="date_of_birth" label="Date of birth (optional)" placeholder="YYYY-MM-DD" />
                <button type="submit" class="inline-flex rounded-xl bg-brand text-white text-sm font-bold px-5 py-3 hover:bg-brand-light">
                    Run CRB live test
                </button>
            </form>
        @else
            <p class="text-sm text-gray-600">Live test for this partner will appear when its operational adapter is ready. Use Check health for credentials/reachability only.</p>
        @endif
    </div>
</section>
