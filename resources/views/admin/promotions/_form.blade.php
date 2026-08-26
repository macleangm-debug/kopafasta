@php
    $r = $record ?? null;
    $meta = $meta ?? ($r?->metadata ?? []);
@endphp

<x-admin.step title="Goal">
    <div class="md:col-span-2 space-y-3">
        <p class="text-sm font-semibold text-gray-800">What do you want to achieve?</p>
        <div class="grid sm:grid-cols-2 gap-2">
            @foreach ($intents ?? [] as $key => $label)
                <label class="flex items-center gap-2 rounded-xl ring-1 ring-gray-200 px-3 py-2.5 text-sm cursor-pointer has-[:checked]:ring-brand has-[:checked]:bg-brand-muted/40">
                    <input type="radio" name="intent" value="{{ $key }}" class="text-brand" x-model="intent">
                    {{ $label }}
                </label>
            @endforeach
        </div>
        <div x-show="intent === 'other'" x-cloak class="rounded-xl bg-gray-50 ring-1 ring-gray-200 p-3">
            <x-admin.input name="intent_other" label="Describe the goal" x-model="intentOther" />
        </div>
        <x-admin.input name="name" label="Campaign name" required x-model="name" />
        <x-admin.input name="code" label="Code (optional)" :value="$r?->code" placeholder="Auto if blank" />
        <input type="hidden" name="payload_type" :value="payloadType">
        <div x-show="intent === 'promote_offer'" x-cloak>
            <x-admin.select name="offer_id" label="Offer" :options="['' => '—'] + ($offers ?? collect())->mapWithKeys(fn ($offer) => [$offer->id => $offer->title])->all()" x-model="offerId" />
        </div>
        <div x-show="intent === 'learning_content'" x-cloak>
            <x-admin.input name="article_hint" label="Published article / subject to promote" :value="$meta['article_hint'] ?? ''" />
        </div>
        <x-admin.input name="cta_url" label="CTA destination (optional)" x-model="cta" placeholder="/borrower/plus" />
        <x-admin.textarea name="message_en" label="Message (English)" rows="4" x-model="messageEn" />
        <x-admin.textarea name="message_sw" label="Message (Swahili)" rows="4" x-model="messageSw" />
        <div class="grid sm:grid-cols-2 gap-4" x-show="intent === 'fee_promotion'" x-cloak>
            <x-admin.select name="type" label="Fee promotion type" :options="$types" :value="$r?->type ?? 'fee_discount'" />
            <x-admin.select name="applies_to" label="Applies to" :options="$appliesTo" :value="$r?->applies_to" placeholder="— Optional —" />
            <x-admin.input name="original_fee" label="Original fee (TZS)" money :decimals="2" :value="$r?->original_fee" />
            <x-admin.select name="discount_type" label="Discount type" :options="$discountTypes ?? []" :value="$r?->discount_type ?? 'percentage'" />
            <x-admin.input name="discount_percent" label="Discount (%)" type="number" step="0.01" :value="$r?->discount_percent" />
            <x-admin.input name="discount_amount" label="Fixed discount (TZS)" money :decimals="2" :value="$r?->discount_amount" />
            <p class="sm:col-span-2 text-xs text-gray-500">Promotions apply to fees only — not loan interest or penalties. The existing Promotion engine still applies the discount.</p>
        </div>
        <p class="text-xs text-gray-500">This wizard orchestrates audience, channels and timing around the existing Promotion engine. It does not create a second campaign engine.</p>
    </div>
</x-admin.step>

<x-admin.step title="Audience">
    <div class="md:col-span-2 space-y-4">
        <p class="text-sm font-semibold text-gray-800">Who should receive this?</p>
        <div class="flex flex-wrap gap-2">
            <label class="rounded-xl ring-1 ring-gray-200 px-3 py-2 text-sm has-[:checked]:ring-brand"><input type="radio" name="audience_mode" value="everyone" class="mr-1 text-brand" x-model="audienceMode"> Everyone eligible</label>
            <label class="rounded-xl ring-1 ring-gray-200 px-3 py-2 text-sm has-[:checked]:ring-brand"><input type="radio" name="audience_mode" value="saved" class="mr-1 text-brand" x-model="audienceMode"> Saved audience</label>
            <label class="rounded-xl ring-1 ring-gray-200 px-3 py-2 text-sm has-[:checked]:ring-brand"><input type="radio" name="audience_mode" value="custom" class="mr-1 text-brand" x-model="audienceMode"> Custom</label>
        </div>

        <div x-show="audienceMode === 'saved'" x-cloak>
            <x-admin.select name="audience_id" label="Saved audience" :options="['' => 'Choose…'] + ($savedAudiences ?? collect())->mapWithKeys(fn ($audience) => [$audience->id => $audience->name.' · '.\App\Support\MoneyFormat::compact($audience->estimated_count)])->all()" x-model="audienceId" />
        </div>

        <div x-show="audienceMode === 'custom'" x-cloak class="grid sm:grid-cols-2 gap-4">
            <x-admin.select name="country_code" label="Country" :options="['' => 'Any'] + ($dimensions['country_code']['options'] ?? [])" x-model="country" />
            <x-admin.select name="audience_status" label="Customer status" :options="['' => 'Any'] + ($dimensions['status']['options'] ?? [])" x-model="status" />
            <div>
                <p class="text-xs font-semibold text-gray-700 mb-1">Grade</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($dimensions['grades']['options'] ?? [] as $value => $label)
                        <label class="inline-flex items-center gap-1.5 text-sm">
                            <input type="checkbox" name="grades[]" value="{{ $value }}" class="rounded border-gray-300 text-brand" :checked="grades.includes('{{ $value }}')" @change="toggleGrade('{{ $value }}')">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>
            <x-admin.select name="plus" label="Kopafasta Plus" :options="$dimensions['plus']['options'] ?? []" x-model="plus" />
            <x-admin.select name="borrowing" label="Borrowing relationship" :options="$dimensions['borrowing']['options'] ?? []" x-model="borrowing" />
            <x-admin.select name="affiliate" label="Affiliate status" :options="$dimensions['affiliate']['options'] ?? []" x-model="affiliate" />
        </div>

        <p class="text-lg font-bold text-brand tabular-nums">
            Estimated audience:
            <span x-text="estimateLabel">—</span>
        </p>
        <p class="text-xs text-gray-500" x-show="estimateLoading">Counting…</p>
    </div>
</x-admin.step>

<x-admin.step title="Channels">
    <div class="md:col-span-2 space-y-2">
        <p class="text-sm font-semibold">Channels enabled in Settings Hub</p>
        @forelse ($enabledChannels ?? [] as $key => $label)
            <label class="flex items-center gap-2 rounded-xl ring-1 ring-gray-200 px-3 py-2 text-sm">
                <input type="checkbox" name="channels[]" value="{{ $key }}" class="rounded text-brand"
                       :checked="channels.includes('{{ $key }}')" @change="toggleChannel('{{ $key }}')">
                {{ $label }}
            </label>
        @empty
            <p class="text-sm text-amber-800">No channels enabled. Turn them on in Settings → Communications.</p>
        @endforelse
        <p class="text-xs text-gray-500">Gateway and provider configuration stays in Settings. Quiet hours are honoured for SMS/email/WhatsApp.</p>
    </div>
</x-admin.step>

<x-admin.step title="Timing">
    <div class="md:col-span-2 flex flex-wrap gap-2 mb-2">
        <label class="rounded-xl ring-1 ring-gray-200 px-3 py-2 text-sm has-[:checked]:ring-brand"><input type="radio" name="send_mode" value="now" class="mr-1 text-brand" x-model="sendMode"> Send now</label>
        <label class="rounded-xl ring-1 ring-gray-200 px-3 py-2 text-sm has-[:checked]:ring-brand"><input type="radio" name="send_mode" value="schedule" class="mr-1 text-brand" x-model="sendMode"> Schedule</label>
    </div>
    <div class="md:col-span-2 grid sm:grid-cols-2 gap-4" x-show="sendMode === 'schedule'" x-cloak>
        <x-admin.input name="starts_at" label="Start date" type="date" :value="optional($r?->starts_at)->format('Y-m-d')" />
        <x-admin.input name="ends_at" label="End date" type="date" :value="optional($r?->ends_at)->format('Y-m-d')" />
    </div>
    <p class="md:col-span-2 text-xs text-gray-500">If you launch SMS/email after 21:00 or before 07:00 (Dar time), send is deferred to respect quiet hours.</p>
</x-admin.step>

<x-admin.step title="Preview">
    <div class="md:col-span-2 rounded-2xl bg-brand-muted/30 ring-1 ring-brand/10 p-5 space-y-3 text-sm">
        <p><span class="text-gray-500">Goal</span> · <strong x-text="intentLabel"></strong></p>
        <p><span class="text-gray-500">Audience</span> · <strong x-text="audienceLabel"></strong></p>
        <p><span class="text-gray-500">Estimated reach</span> · <strong class="tabular-nums" x-text="estimateLabel"></strong></p>
        <p><span class="text-gray-500">Channels</span> · <strong x-text="channels.join(', ') || 'in-app'"></strong></p>
        <p><span class="text-gray-500">Timing</span> · <strong x-text="sendMode === 'now' ? 'Send now' : 'Scheduled'"></strong></p>
        <p><span class="text-gray-500">CTA</span> · <strong x-text="cta || '—'"></strong></p>
        <div class="rounded-xl bg-white p-4 ring-1 ring-gray-100">
            <p class="text-[10px] uppercase tracking-widest text-gray-400">EN preview</p>
            <p class="mt-1" x-text="messageEn || 'No English message yet.'"></p>
        </div>
        <div class="rounded-xl bg-white p-4 ring-1 ring-gray-100">
            <p class="text-[10px] uppercase tracking-widest text-gray-400">SW preview</p>
            <p class="mt-1" x-text="messageSw || 'Hakuna ujumbe wa Kiswahili bado.'"></p>
        </div>
    </div>
</x-admin.step>

<x-admin.step title="Launch">
    <div class="md:col-span-2 space-y-3">
        <p class="text-sm text-gray-800">Launch stays in this wizard. Confirm the plan, then launch. Fee discounts still use the existing Promotion engine.</p>
        <div class="rounded-2xl bg-white ring-1 ring-brand/15 p-4 text-sm space-y-1">
            <p><strong x-text="name || 'Untitled campaign'"></strong></p>
            <p class="text-gray-600" x-text="intentLabel + ' · ' + audienceLabel"></p>
            <p class="tabular-nums font-semibold text-brand" x-text="'Reach ' + estimateLabel"></p>
        </div>
        <p class="text-xs text-gray-500">Next asks for confirmation in this same flow. Demo accounts, live loans, payments and GL are not touched.</p>
    </div>
</x-admin.step>
