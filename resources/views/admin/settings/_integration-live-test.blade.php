@php
    $partnerKey = $partnerKey ?? '';
    $partnerLabel = $partner['label'] ?? 'Partner';
    $supportsLiveTest = in_array($partnerKey, ['payin', 'unitxt', 'email_smtp', 'crb'], true);
    $isProductionLivePayIn = $partnerKey === 'payin'
        && app()->isProduction()
        && ! payment_gateway_is_dummy()
        && (\App\Models\Setting::get('payin.environment') === 'production');
    $payinEnv = \App\Models\Setting::get('payin.environment') === 'production' ? 'Production' : 'Sandbox';
    $payinMode = payment_gateway_is_dummy() ? 'Dummy' : 'Live';
    $callbackConfigured = filled(\App\Models\Setting::get('payin.webhook_secret'));
    $autoOpen = request()->boolean('live_test') && $supportsLiveTest;
@endphp

@if ($supportsLiveTest)
<div
    x-data="integrationLiveTest({
        autoOpen: {{ $autoOpen ? 'true' : 'false' }},
        requireRiskAck: {{ ($partnerKey === 'payin' && $isProductionLivePayIn) ? 'true' : 'false' }},
        emailSubject: @js('Kopafasta email live test'),
    })"
    @keydown.escape.window="if (liveTestOpen) closeLiveTest()"
    data-integration-live-test="{{ $partnerKey }}"
>
    <button type="button"
            @click="openLiveTest($event)"
            data-live-test-trigger
            data-loading-label="Opening…"
            class="rounded-xl ring-1 ring-brand/20 text-brand text-xs font-semibold px-3 py-2 hover:bg-brand-muted/40">
        Live test
    </button>

    <x-site.action-panel
        :title="$partnerKey === 'payin' ? 'PayIn operational rehearsal' : ($partnerLabel.' live test')"
        open="liveTestOpen"
        size="lg"
    >
        <form method="POST"
              action="{{ route('admin.settings.integrations.live-test') }}"
              class="space-y-4"
              x-ref="liveTestForm"
              data-no-draft
              autocomplete="off"
              @submit="if (step !== 'review') { $event.preventDefault(); goReview(); }">
            @csrf
            @if ($partnerKey === 'payin')
                <input type="hidden" name="suite" value="payment">
                <input type="hidden" name="partner" value="payin">

                <div x-show="step === 'form'" class="space-y-4">
                    <p class="text-sm text-gray-600">
                        This exercises the real PayIn payment rail using the same payment.show journey used by Kopafasta.
                    </p>
                    <x-admin.phone-input name="phone" label="Mobile number" :required="true" lockedCountry="TZ" />
                    <x-admin.money-input name="amount" label="Amount (TZS)" :value="1000" :decimals="0" help="Default rehearsal amount is 1,000 TZS." />
                    @if ($isProductionLivePayIn)
                        <label class="flex items-start gap-2 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-3 py-3 text-sm text-amber-950">
                            <input type="checkbox" x-model="riskAck" x-ref="riskAckInput" name="confirm_production_payment" value="1"
                                   class="mt-1 rounded border-amber-300 text-brand focus:ring-brand" required>
                            <span>I understand this is a real production payment and may move real money.</span>
                        </label>
                    @endif
                    <button type="button" @click="goReview()"
                            class="w-full inline-flex justify-center rounded-xl bg-brand text-white text-sm font-bold px-5 py-3 hover:bg-brand-light">
                        Review test payment
                    </button>
                </div>

                <div x-show="step === 'review'" x-cloak class="space-y-4">
                    <p class="text-sm text-gray-600">Confirm the rehearsal details before opening payment.show.</p>
                    <dl class="rounded-xl bg-gray-50 ring-1 ring-gray-200 divide-y divide-gray-200 text-sm">
                        <div class="flex justify-between gap-3 px-4 py-3"><dt class="text-gray-500">Provider</dt><dd class="font-semibold text-gray-900">PayIn</dd></div>
                        <div class="flex justify-between gap-3 px-4 py-3"><dt class="text-gray-500">Environment</dt><dd class="font-semibold text-gray-900">{{ $payinEnv }}</dd></div>
                        <div class="flex justify-between gap-3 px-4 py-3"><dt class="text-gray-500">Mobile number</dt><dd class="font-semibold text-gray-900" x-text="phoneDisplay"></dd></div>
                        <div class="flex justify-between gap-3 px-4 py-3"><dt class="text-gray-500">Amount</dt><dd class="font-semibold text-gray-900"><span x-text="amountDisplay"></span> TZS</dd></div>
                        <div class="flex justify-between gap-3 px-4 py-3"><dt class="text-gray-500">Gateway mode</dt><dd class="font-semibold text-gray-900">{{ $payinMode }}</dd></div>
                        <div class="flex justify-between gap-3 px-4 py-3"><dt class="text-gray-500">Callback</dt><dd class="font-semibold text-gray-900">{{ $callbackConfigured ? 'Configured' : 'Not configured' }}</dd></div>
                    </dl>
                    @if ($isProductionLivePayIn)
                        <p class="text-xs text-amber-800 rounded-lg bg-amber-50 ring-1 ring-amber-100 px-3 py-2">
                            This opens payment.show. Real money moves only after you initiate payment on that page.
                        </p>
                    @endif
                    <div class="flex flex-col-reverse sm:flex-row gap-2">
                        <button type="button" @click="backToForm()" :disabled="submitting"
                                class="flex-1 rounded-xl ring-1 ring-gray-200 bg-white text-gray-800 text-sm font-semibold px-4 py-3 hover:bg-gray-50 disabled:opacity-60">
                            Back
                        </button>
                        <button type="button"
                                @click="submitLiveTest($event)"
                                data-live-test-continue
                                data-loading-label="Opening payment…"
                                :disabled="submitting"
                                class="flex-1 rounded-xl bg-brand-gold text-brand text-sm font-bold px-4 py-3 hover:brightness-95 disabled:opacity-60">
                            Continue to payment.show
                        </button>
                    </div>
                </div>
            @elseif ($partnerKey === 'unitxt')
                <input type="hidden" name="suite" value="messaging">
                <input type="hidden" name="partner" value="unitxt">
                <div x-show="step === 'form'" class="space-y-4">
                    <p class="text-sm text-gray-600">Sends a real SMS through the configured Unitxt gateway.</p>
                    <x-admin.phone-input name="phone" label="Recipient" :required="true" lockedCountry="TZ" />
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Message</label>
                        <textarea name="message" x-model="messagePreview" rows="4" required maxlength="320"
                                  class="w-full rounded-xl border-gray-200 text-sm focus:border-brand focus:ring-brand/20"
                                  placeholder="Kopafasta Unitxt live test"></textarea>
                        <p class="mt-1 text-xs text-gray-500">
                            <span x-text="(messagePreview || '').length"></span> characters ·
                            <span x-text="Math.max(1, Math.ceil((messagePreview || '').length / 160) || 1)"></span> SMS segment(s)
                        </p>
                    </div>
                    <button type="button" @click="goReview()"
                            class="w-full inline-flex justify-center rounded-xl bg-brand text-white text-sm font-bold px-5 py-3 hover:bg-brand-light">
                        Review test SMS
                    </button>
                </div>
                <div x-show="step === 'review'" x-cloak class="space-y-4">
                    <dl class="rounded-xl bg-gray-50 ring-1 ring-gray-200 divide-y divide-gray-200 text-sm">
                        <div class="flex justify-between gap-3 px-4 py-3"><dt class="text-gray-500">Provider</dt><dd class="font-semibold text-gray-900">Unitxt SMS</dd></div>
                        <div class="flex justify-between gap-3 px-4 py-3"><dt class="text-gray-500">Recipient</dt><dd class="font-semibold text-gray-900" x-text="phoneDisplay"></dd></div>
                        <div class="px-4 py-3"><dt class="text-gray-500 mb-1">Message</dt><dd class="font-medium text-gray-900 whitespace-pre-wrap" x-text="messagePreview"></dd></div>
                    </dl>
                    <div class="flex flex-col-reverse sm:flex-row gap-2">
                        <button type="button" @click="backToForm()" :disabled="submitting" class="flex-1 rounded-xl ring-1 ring-gray-200 bg-white text-sm font-semibold px-4 py-3 disabled:opacity-60">Back</button>
                        <button type="button" @click="submitLiveTest($event)" data-live-test-continue data-loading-label="Sending…" :disabled="submitting" class="flex-1 rounded-xl bg-brand-gold text-brand text-sm font-bold px-4 py-3 disabled:opacity-60">Send test SMS</button>
                    </div>
                </div>
            @elseif ($partnerKey === 'email_smtp')
                <input type="hidden" name="suite" value="email">
                <input type="hidden" name="partner" value="email_smtp">
                <div x-show="step === 'form'" class="space-y-4">
                    <p class="text-sm text-gray-600">Sends a real email through the configured mail provider/SMTP.</p>
                    <x-admin.input name="email" label="To email" type="email" required />
                    <x-admin.input name="subject" label="Subject" :value="'Kopafasta email live test'" />
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Message</label>
                        <textarea name="message" rows="4" class="w-full rounded-xl border-gray-200 text-sm" placeholder="Controlled Email (SMTP) live test."></textarea>
                    </div>
                    <button type="button" @click="goReview()"
                            class="w-full inline-flex justify-center rounded-xl bg-brand text-white text-sm font-bold px-5 py-3 hover:bg-brand-light">
                        Review test email
                    </button>
                </div>
                <div x-show="step === 'review'" x-cloak class="space-y-4">
                    <dl class="rounded-xl bg-gray-50 ring-1 ring-gray-200 divide-y divide-gray-200 text-sm">
                        <div class="flex justify-between gap-3 px-4 py-3"><dt class="text-gray-500">Provider</dt><dd class="font-semibold text-gray-900">Email (SMTP)</dd></div>
                        <div class="flex justify-between gap-3 px-4 py-3"><dt class="text-gray-500">To</dt><dd class="font-semibold text-gray-900" x-text="emailTo"></dd></div>
                        <div class="flex justify-between gap-3 px-4 py-3"><dt class="text-gray-500">Subject</dt><dd class="font-semibold text-gray-900" x-text="emailSubject"></dd></div>
                    </dl>
                    <div class="flex flex-col-reverse sm:flex-row gap-2">
                        <button type="button" @click="backToForm()" :disabled="submitting" class="flex-1 rounded-xl ring-1 ring-gray-200 bg-white text-sm font-semibold px-4 py-3 disabled:opacity-60">Back</button>
                        <button type="button" @click="submitLiveTest($event)" data-live-test-continue data-loading-label="Sending…" :disabled="submitting" class="flex-1 rounded-xl bg-brand-gold text-brand text-sm font-bold px-4 py-3 disabled:opacity-60">Send test email</button>
                    </div>
                </div>
            @elseif ($partnerKey === 'crb')
                <input type="hidden" name="suite" value="crb">
                <input type="hidden" name="partner" value="crb">
                <div x-show="step === 'form'" class="space-y-4">
                    <p class="text-sm text-gray-600">Performs an actual CRB enquiry using the configured driver. May incur a provider charge on live credentials.</p>
                    <x-admin.input name="nida" label="NIDA" placeholder="XXXXXXXX-XXXXX-XXXXX-XX" help="Uses the same NIDA format validation as borrower identity." />
                    <x-admin.input name="full_name" label="Full name (optional)" />
                    <x-admin.input name="date_of_birth" label="Date of birth (optional)" placeholder="YYYY-MM-DD" />
                    <button type="button" @click="goReview()"
                            class="w-full inline-flex justify-center rounded-xl bg-brand text-white text-sm font-bold px-5 py-3 hover:bg-brand-light">
                        Review CRB enquiry
                    </button>
                </div>
                <div x-show="step === 'review'" x-cloak class="space-y-4">
                    <dl class="rounded-xl bg-gray-50 ring-1 ring-gray-200 divide-y divide-gray-200 text-sm">
                        <div class="flex justify-between gap-3 px-4 py-3"><dt class="text-gray-500">Provider</dt><dd class="font-semibold text-gray-900">CRB</dd></div>
                        <div class="flex justify-between gap-3 px-4 py-3"><dt class="text-gray-500">NIDA</dt><dd class="font-semibold text-gray-900" x-text="nidaDisplay || 'Sample / stub identity'"></dd></div>
                    </dl>
                    <p class="text-xs text-amber-800 rounded-lg bg-amber-50 ring-1 ring-amber-100 px-3 py-2">
                        Confirm only if you intend to run a real permitted CRB enquiry.
                    </p>
                    <div class="flex flex-col-reverse sm:flex-row gap-2">
                        <button type="button" @click="backToForm()" :disabled="submitting" class="flex-1 rounded-xl ring-1 ring-gray-200 bg-white text-sm font-semibold px-4 py-3 disabled:opacity-60">Back</button>
                        <button type="button" @click="submitLiveTest($event)" data-live-test-continue data-loading-label="Running…" :disabled="submitting" class="flex-1 rounded-xl bg-brand-gold text-brand text-sm font-bold px-4 py-3 disabled:opacity-60">Run CRB live test</button>
                    </div>
                </div>
            @endif
        </form>
    </x-site.action-panel>
</div>
@endif
