@php
    use App\Support\RatePercent;
    $r = $record ?? null;
    $displayed = app(\App\Services\DisplayedRateService::class)->breakdown($r ?? new \App\Models\LoanProduct([
        'interest_rate' => $r?->interest_rate ?? 0,
        'bot_regulated_rate' => $r?->bot_regulated_rate,
        'processing_fee_rate' => $r?->processing_fee_rate ?? 0,
        'service_fee_rate' => $r?->service_fee_rate ?? 0,
        'administration_fee_rate' => $r?->administration_fee_rate ?? 0,
        'min_amount' => $r?->min_amount ?? 0,
    ]));
@endphp

<x-admin.step title="BOT compliance & monthly rate">
    <p class="md:col-span-2 text-xs text-gray-500 mb-2">
        Enter rates as percentages (e.g. 3.5 for 3.5%). BOT-regulated interest is capped at 3.5% / month.
        When tiered rates are configured below, those totals are shown to borrowers instead of this fee stack.
    </p>
    <x-admin.input name="interest_rate" label="Base rate % (legacy / fallback)" type="number" step="0.01" :value="RatePercent::forInput(old('interest_rate', $r?->interest_rate))" required />
    <x-admin.input name="bot_regulated_rate" label="BOT regulated rate %" type="number" step="0.01" max="3.5" :value="RatePercent::forInput(old('bot_regulated_rate', $r?->bot_regulated_rate ?? $r?->interest_rate))" placeholder="e.g. 3.5" />
    <x-admin.input name="processing_fee_rate" label="Processing fee % / month" type="number" step="0.01" :value="RatePercent::forInput(old('processing_fee_rate', $r?->processing_fee_rate ?? 0))" />
    <x-admin.input name="service_fee_rate" label="Risk fee % / month" type="number" step="0.01" :value="RatePercent::forInput(old('service_fee_rate', $r?->service_fee_rate ?? 0))" />
    <x-admin.input name="administration_fee_rate" label="Administration fee % / month" type="number" step="0.01" :value="RatePercent::forInput(old('administration_fee_rate', $r?->administration_fee_rate ?? 0))" />
    <div class="md:col-span-2 rounded-xl bg-amber-50 ring-1 ring-amber-100 p-4 text-sm" id="monthly-rate-preview"
         data-bot="{{ $displayed['bot_regulated_rate'] }}"
         data-fees="{{ $displayed['internal_fee_rate'] }}"
         data-total="{{ $displayed['displayed_monthly_rate'] }}"
         data-uses-tiers="{{ $displayed['uses_tiers'] ? '1' : '0' }}"
         data-tier-range="{{ $displayed['tier_borrower_range'] ?? '' }}">
        <p class="font-semibold text-amber-900">Effective monthly rate (borrower)</p>
        <ul class="mt-2 space-y-1 text-amber-900/90 text-xs" id="monthly-rate-preview-lines">
            @if ($displayed['uses_tiers'])
                <li>Tiered range: <strong>{{ $displayed['tier_borrower_range'] }}</strong> / month</li>
                <li>At minimum amount: <strong>{{ number_format(($displayed['tier_borrower_rate_at_min'] ?? 0) * 100, 1) }}%</strong> / month</li>
            @else
                <li>BOT regulated: <span id="preview-bot">{{ number_format($displayed['bot_regulated_rate'] * 100, 2) }}%</span> / month</li>
                <li>Internal fees: <span id="preview-fees">{{ number_format($displayed['internal_fee_rate'] * 100, 2) }}%</span> / month</li>
                <li><strong>Monthly rate: <span id="preview-total">{{ number_format($displayed['displayed_monthly_rate'] * 100, 2) }}%</span> / month</strong></li>
            @endif
        </ul>
        <p class="mt-2 text-[11px] text-amber-800/80">Updates live when you change fee fields above (fee-stack products only).</p>
    </div>
</x-admin.step>

@once
    @push('scripts')
    <script>
        (function () {
            const pct = (v) => {
                const n = parseFloat(v);
                if (Number.isNaN(n)) return 0;
                return n > 1 ? n / 100 : n;
            };
            const fmt = (d) => (d * 100).toFixed(2) + '%';
            const fields = ['bot_regulated_rate', 'processing_fee_rate', 'service_fee_rate', 'administration_fee_rate'];
            const preview = document.getElementById('monthly-rate-preview');
            if (!preview || preview.dataset.usesTiers === '1') return;

            const botEl = document.getElementById('preview-bot');
            const feesEl = document.getElementById('preview-fees');
            const totalEl = document.getElementById('preview-total');

            const recalc = () => {
                let bot = pct(document.querySelector('[name="bot_regulated_rate"]')?.value || document.querySelector('[name="interest_rate"]')?.value || 0);
                bot = Math.min(bot, 0.035);
                const fees = ['processing_fee_rate', 'service_fee_rate', 'administration_fee_rate']
                    .reduce((sum, name) => sum + pct(document.querySelector(`[name="${name}"]`)?.value || 0), 0);
                const total = bot + fees;
                if (botEl) botEl.textContent = fmt(bot);
                if (feesEl) feesEl.textContent = fmt(fees);
                if (totalEl) totalEl.textContent = fmt(total);
            };

            fields.forEach((name) => {
                const el = document.querySelector(`[name="${name}"]`);
                if (el) el.addEventListener('input', recalc);
            });
            const base = document.querySelector('[name="interest_rate"]');
            if (base) base.addEventListener('input', recalc);
        })();
    </script>
    @endpush
@endonce

<x-admin.step title="Pricing & tenure">
    <x-admin.input  name="tenure_min_months"   label="Min tenure (months)" type="number" :value="$r?->tenure_min_months" required />
    <x-admin.input  name="tenure_max_months"   label="Max tenure (months)" type="number" :value="$r?->tenure_max_months" required />
    <x-admin.select name="repayment_cadence"   label="Repayment cadence"
                    :options="['weekly' => 'Weekly (tenure × 4 instalments)', 'monthly' => 'Monthly']"
                    :value="$r?->repayment_cadence ?? 'weekly'" required />
    <x-admin.input  name="min_amount"          label="Min amount (TZS)"    type="number" step="0.01" :value="$r?->min_amount" required />
    <x-admin.input  name="max_amount"          label="Max amount (TZS)"    type="number" step="0.01" :value="$r?->max_amount" required />
</x-admin.step>
