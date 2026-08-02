@php
    $r = $record ?? null;
    $fields = \App\Services\Messaging\TemplatePersonalization::grouped();
    $catalog = \App\Services\Messaging\MessagingCatalog::eventsByCode();
    $groupedEvents = \App\Services\Messaging\MessagingCatalog::eventsGroupedByLifecycle();
    $lifecycles = \App\Services\Messaging\MessagingCatalog::LIFECYCLES;
    $initialCode = old('code', $r?->code ?? request('code', ''));
    $initialLocale = old('locale', $r?->locale ?? request('locale', 'en'));
    $lifecycleMeta = $initialCode !== ''
        ? \App\Services\Messaging\MessagingCatalog::lifecycleMeta($initialCode)
        : ['key' => '', 'label' => 'Choose when this sends', 'hint' => 'Pick a stage, then the event.'];
    $sibling = null;
    if ($r?->code) {
        $sibling = \App\Models\NotificationTemplate::query()
            ->where('code', $r->code)
            ->where('locale', ($r->locale ?? 'en') === 'sw' ? 'en' : 'sw')
            ->first();
    }
@endphp

<div
    class="space-y-6"
    x-data="{
        target: 'body',
        code: @js($initialCode),
        name: @js(old('name', $r?->name ?? ($catalog[$initialCode]['name'] ?? ''))),
        lifecycle: @js($lifecycleMeta['key'] ?: ''),
        eventsByLifecycle: @js($groupedEvents),
        catalog: @js(collect($catalog)->map(fn ($e) => [
            'name' => $e['name'],
            'description' => $e['description'],
        ])->all()),
        lifecycleLabels: @js(collect($lifecycles)->map(fn ($m) => $m['label'])->all()),
        get description() {
            return this.catalog[this.code]?.description || '';
        },
        get stageLabel() {
            if (this.lifecycle && this.lifecycleLabels[this.lifecycle]) {
                return this.lifecycleLabels[this.lifecycle];
            }
            if (! this.code) return 'Choose when this sends';
            for (const [key, events] of Object.entries(this.eventsByLifecycle)) {
                if (events.some(e => e.code === this.code)) {
                    return this.lifecycleLabels[key] || key;
                }
            }
            return this.lifecycleLabels.other || 'Other / custom';
        },
        onLifecycleChange() {
            if (! this.lifecycle) return;
            const list = this.eventsByLifecycle[this.lifecycle] || [];
            if (this.code && ! list.some(e => e.code === this.code)) {
                this.code = list[0]?.code || '';
                this.syncNameFromEvent(true);
            }
        },
        syncNameFromEvent(force = false) {
            const ev = this.catalog[this.code];
            if (! ev) return;
            if (force || ! this.name || Object.values(this.catalog).some(c => c.name === this.name)) {
                this.name = ev.name;
            }
        },
        insert(token) {
            const el = document.getElementById(this.target);
            if (! el) return;
            const snippet = '{' + '{ ' + token + ' }' + '}';
            const start = el.selectionStart ?? el.value.length;
            const end = el.selectionEnd ?? start;
            el.value = el.value.substring(0, start) + snippet + el.value.substring(end);
            el.dispatchEvent(new Event('input'));
            el.focus();
            const pos = start + snippet.length;
            el.setSelectionRange(pos, pos);
        }
    }"
>
    <div class="rounded-2xl bg-brand text-white px-5 py-4 shadow-sm">
        <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">When this sends</p>
        <p class="text-lg font-bold mt-1" x-text="stageLabel"></p>
        <p class="text-sm text-white/80 mt-1" x-show="description" x-text="description"></p>
        <p class="text-sm text-white/80 mt-1" x-show="!description">Select a stage and event. Use the same event code for English and Kiswahili.</p>
        @if ($sibling)
            <a href="{{ route('admin.notification-templates.edit', $sibling) }}"
               class="inline-flex mt-3 rounded-xl bg-brand-gold text-brand text-xs font-semibold px-3 py-1.5 hover:brightness-95">
                Edit {{ strtoupper($sibling->locale) }} version →
            </a>
        @elseif ($r?->code)
            <a href="{{ route('admin.notification-templates.create', ['code' => $r->code, 'locale' => ($r->locale ?? 'en') === 'sw' ? 'en' : 'sw']) }}"
               class="inline-flex mt-3 rounded-xl bg-white/15 text-white text-xs font-semibold px-3 py-1.5 hover:bg-white/25">
                Add {{ ($r->locale ?? 'en') === 'sw' ? 'EN' : 'SW' }} version →
            </a>
        @endif
    </div>

    <div class="grid lg:grid-cols-2 gap-5">
        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">1. Where should this send?</label>
            <select x-model="lifecycle" @change="onLifecycleChange()"
                    class="w-full text-sm bg-white border border-brand/15 rounded-xl shadow-sm px-3.5 py-2.5 focus:border-brand focus:ring-2 focus:ring-brand/15">
                <option value="">All stages (show every event)</option>
                @foreach ($lifecycles as $key => $meta)
                    @continue($key === 'other')
                    <option value="{{ $key }}">{{ $meta['label'] }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-[11px] text-gray-500">Registration, application, repayment, late payment…</p>
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">2. Event</label>
            <select name="code" x-model="code" @change="syncNameFromEvent(true)" required
                    class="w-full text-sm bg-white border border-brand/15 rounded-xl shadow-sm px-3.5 py-2.5 focus:border-brand focus:ring-2 focus:ring-brand/15">
                <option value="">Select event…</option>
                @foreach ($groupedEvents as $lifeKey => $events)
                    <optgroup label="{{ $lifecycles[$lifeKey]['label'] ?? $lifeKey }}"
                              x-bind:disabled="lifecycle && lifecycle !== @js($lifeKey)"
                              x-show="!lifecycle || lifecycle === @js($lifeKey)">
                        @foreach ($events as $ev)
                            <option value="{{ $ev['code'] }}" @selected($initialCode === $ev['code'])>
                                {{ $ev['name'] }} — {{ $ev['code'] }}
                            </option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            @error('code')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="sm:col-span-2">
            <label class="block text-xs font-semibold text-gray-700 mb-1">Display name</label>
            <input type="text" name="name" x-model="name" required
                   class="w-full text-sm bg-white border border-brand/15 rounded-xl shadow-sm px-3.5 py-2.5 focus:border-brand focus:ring-2 focus:ring-brand/15">
            @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Language</label>
            <select name="locale" required
                    class="w-full text-sm bg-white border border-brand/15 rounded-xl shadow-sm px-3.5 py-2.5 focus:border-brand focus:ring-2 focus:ring-brand/15">
                <option value="en" @selected($initialLocale === 'en')>English</option>
                <option value="sw" @selected($initialLocale === 'sw')>Kiswahili</option>
            </select>
        </div>
        <div>
            <x-admin.select name="channel" label="Channel" :options="$channels" :value="$r?->channel ?? 'sms'" required />
        </div>
        <div class="lg:col-span-1 sm:col-span-2">
            <x-admin.select name="is_active" label="Status" :options="['1'=>'Active','0'=>'Inactive']" :value="(string)($r?->is_active ?? '1')" required />
        </div>
    </div>

    <div class="rounded-2xl bg-sky-50 ring-1 ring-sky-200 p-4">
        <p class="text-xs font-semibold text-sky-900 mb-1">Personalization — click to insert</p>
        <p class="text-[11px] text-sky-800 mb-3">
            Click Subject or Body first. Example: Hello <code>@{{ name }}</code>, amount <code>@{{ amount }}</code>.
        </p>
        <div class="flex gap-2 mb-3 text-[11px]">
            <button type="button" @click="target = 'subject'" :class="target === 'subject' ? 'bg-brand text-white' : 'bg-white text-gray-700'" class="rounded-lg px-2.5 py-1 font-semibold ring-1 ring-sky-200">Insert into Subject</button>
            <button type="button" @click="target = 'body'" :class="target === 'body' ? 'bg-brand text-white' : 'bg-white text-gray-700'" class="rounded-lg px-2.5 py-1 font-semibold ring-1 ring-sky-200">Insert into Body</button>
        </div>
        @foreach ($fields as $group => $items)
            <div class="mb-3 last:mb-0">
                <p class="text-[10px] uppercase tracking-widest text-sky-700 font-semibold mb-1.5">{{ $group }}</p>
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($items as $field)
                        <button type="button"
                                @click="insert(@js($field['token']))"
                                class="inline-flex items-center gap-1 rounded-full bg-white ring-1 ring-sky-200 hover:ring-brand/40 hover:bg-brand-muted/40 px-2.5 py-1 text-[11px] font-semibold text-gray-800"
                                title="Example: {{ $field['example'] }}">
                            {{ $field['label'] }}
                            <span class="font-mono text-brand text-[10px]">{{ '{'.'{'.$field['token'].'}'.'}' }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <div>
        <label class="block text-xs font-semibold text-gray-700 mb-1" for="subject">Subject</label>
        <input id="subject" type="text" name="subject" value="{{ old('subject', $r?->subject) }}"
               @focus="target = 'subject'"
               class="w-full text-sm bg-white border border-brand/15 rounded-xl shadow-sm px-3.5 py-2.5 focus:border-brand focus:ring-2 focus:ring-brand/15"
               placeholder="Short title for email / push (optional for SMS)">
    </div>

    <div>
        <label class="block text-xs font-semibold text-gray-700 mb-1" for="body">Message body</label>
        <textarea id="body" name="body" rows="10" required
                  @focus="target = 'body'"
                  class="w-full text-sm bg-white border border-brand/15 rounded-xl shadow-sm px-3.5 py-2.5 focus:border-brand focus:ring-2 focus:ring-brand/15 font-mono leading-relaxed"
                  placeholder="Write the SMS / email text borrowers will receive…">{{ old('body', $r?->body) }}</textarea>
        @error('body')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        <p class="mt-1 text-[11px] text-gray-500">Keep SMS short. Create one row per language with the same event code.</p>
    </div>
</div>
