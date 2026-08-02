@php
    $r = $record ?? null;
    $fields = \App\Services\Messaging\TemplatePersonalization::grouped();
    $catalog = \App\Services\Messaging\MessagingCatalog::eventsByCode();
    $groupedEvents = $eventsGrouped ?? \App\Services\Messaging\MessagingCatalog::eventsGroupedByLifecycle();
    $lifecycles = $lifecycles ?? \App\Services\Messaging\MessagingCatalog::LIFECYCLES;
    $localeLabels = $locales ?? config('notification_templates.locales', ['en' => 'English', 'sw' => 'Kiswahili']);
    $translations = $translations ?? [];
    $initialCode = old('code', $r?->code ?? request('code', ''));
    $lifecycleMeta = $initialCode !== ''
        ? \App\Services\Messaging\MessagingCatalog::lifecycleMeta($initialCode)
        : ['key' => '', 'label' => 'Choose when this sends', 'hint' => ''];
    $activeLang = old('_active_lang', array_key_first($localeLabels) ?: 'en');
@endphp

<div
    class="space-y-6"
    x-data="{
        targetField: 'body_en',
        code: @js($initialCode),
        name: @js(old('name', $r?->name ?? ($catalog[$initialCode]['name'] ?? ''))),
        catalog: @js(collect($catalog)->map(fn ($e) => ['name' => $e['name'], 'description' => $e['description']])->all()),
        lifecycleLabels: @js(collect($lifecycles)->map(fn ($m) => $m['label'])->all()),
        eventsByLifecycle: @js($groupedEvents),
        activeLang: @js($activeLang),
        get description() { return this.catalog[this.code]?.description || ''; },
        get stageLabel() {
            if (! this.code) return 'Choose when this sends';
            for (const [key, events] of Object.entries(this.eventsByLifecycle)) {
                if (events.some(e => e.code === this.code)) return this.lifecycleLabels[key] || key;
            }
            return this.lifecycleLabels.other || 'Other';
        },
        syncName() {
            const ev = this.catalog[this.code];
            if (ev && (! this.name || Object.values(this.catalog).some(c => c.name === this.name))) {
                this.name = ev.name;
            }
        },
        insert(token) {
            const el = document.getElementById(this.targetField);
            if (! el) return;
            const snippet = '{' + '{ ' + token + ' }' + '}';
            const start = el.selectionStart ?? el.value.length;
            const end = el.selectionEnd ?? start;
            el.value = el.value.substring(0, start) + snippet + el.value.substring(end);
            el.dispatchEvent(new Event('input'));
            el.focus();
            el.setSelectionRange(start + snippet.length, start + snippet.length);
        }
    }"
>
    <div class="rounded-2xl bg-brand text-white px-5 py-4 shadow-sm">
        <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">When this sends</p>
        <p class="text-lg font-bold mt-1" x-text="stageLabel"></p>
        <p class="text-sm text-white/80 mt-1" x-text="description || 'Pick an event below. English and Kiswahili edit side by side.'"></p>
    </div>

    <div class="grid sm:grid-cols-2 gap-5">
        <div class="sm:col-span-2">
            <label class="block text-xs font-semibold text-gray-700 mb-1">Event</label>
            <select name="code" x-model="code" @change="syncName()" required
                    class="w-full text-sm bg-white border border-brand/15 rounded-xl shadow-sm px-3.5 py-2.5 focus:border-brand focus:ring-2 focus:ring-brand/15">
                <option value="">Select event…</option>
                @foreach ($groupedEvents as $lifeKey => $events)
                    <optgroup label="{{ $lifecycles[$lifeKey]['label'] ?? $lifeKey }}">
                        @foreach ($events as $ev)
                            <option value="{{ $ev['code'] }}" @selected($initialCode === $ev['code'])>
                                {{ $ev['name'] }} — {{ $ev['code'] }}
                            </option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            @error('code')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            <p class="mt-1 text-[11px] text-gray-500">Events are grouped by stage (registration, application, repayment, late payment…).</p>
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Display name</label>
            <input type="text" name="name" x-model="name" required
                   class="w-full text-sm bg-white border border-brand/15 rounded-xl shadow-sm px-3.5 py-2.5 focus:border-brand focus:ring-2 focus:ring-brand/15">
            @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="grid grid-cols-2 gap-3">
            <x-admin.select name="channel" label="Channel" :options="$channels" :value="$r?->channel ?? 'sms'" required />
            <x-admin.select name="is_active" label="Status" :options="['1'=>'Active','0'=>'Inactive']" :value="(string)($r?->is_active ?? '1')" required />
        </div>
    </div>

    <div class="rounded-2xl bg-sky-50 ring-1 ring-sky-200 p-4">
        <p class="text-xs font-semibold text-sky-900 mb-1">Personalization — click to insert into the focused language field</p>
        <div class="flex flex-wrap gap-1.5 mt-2">
            @foreach ($fields as $group => $items)
                @foreach ($items as $field)
                    <button type="button" @click="insert(@js($field['token']))"
                            class="inline-flex items-center gap-1 rounded-full bg-white ring-1 ring-sky-200 hover:ring-brand/40 px-2.5 py-1 text-[11px] font-semibold text-gray-800"
                            title="{{ $field['example'] }}">
                        {{ $field['label'] }}
                        <span class="font-mono text-brand text-[10px]">{{ '{'.'{'.$field['token'].'}'.'}' }}</span>
                    </button>
                @endforeach
            @endforeach
        </div>
    </div>

    @error('translations')
        <div class="rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">{{ $message }}</div>
    @enderror

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        @foreach ($localeLabels as $locale => $label)
            @php $t = $translations[$locale] ?? ['subject' => '', 'body' => '', 'locale' => $locale]; @endphp
            <div class="rounded-2xl ring-1 ring-brand/15 bg-white overflow-hidden"
                 @focusin="activeLang = @js($locale)">
                <div class="px-4 py-3 border-b border-gray-100 bg-brand-muted/30 flex items-center justify-between gap-2">
                    <div>
                        <p class="text-sm font-bold text-gray-900">{{ $label }}</p>
                        <p class="text-[10px] font-mono text-gray-500 uppercase">{{ $locale }}</p>
                    </div>
                    <span class="text-[10px] font-semibold rounded-full px-2 py-0.5 ring-1"
                          :class="activeLang === @js($locale) ? 'bg-brand text-white ring-brand' : 'bg-white text-gray-500 ring-gray-200'">
                        Editing
                    </span>
                </div>
                <div class="p-4 space-y-3">
                    <input type="hidden" name="translations[{{ $locale }}][locale]" value="{{ $locale }}">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1" for="subject_{{ $locale }}">Subject</label>
                        <input id="subject_{{ $locale }}" type="text"
                               name="translations[{{ $locale }}][subject]"
                               value="{{ $t['subject'] ?? '' }}"
                               @focus="targetField = @js('body_'.$locale); activeLang = @js($locale)"
                               class="w-full text-sm border border-brand/15 rounded-xl px-3 py-2 focus:border-brand focus:ring-2 focus:ring-brand/15"
                               placeholder="Optional for SMS">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1" for="body_{{ $locale }}">Message body</label>
                        <textarea id="body_{{ $locale }}"
                                  name="translations[{{ $locale }}][body]"
                                  rows="10"
                                  @focus="targetField = @js('body_'.$locale); activeLang = @js($locale)"
                                  class="w-full text-sm font-mono border border-brand/15 rounded-xl px-3 py-2 focus:border-brand focus:ring-2 focus:ring-brand/15 leading-relaxed"
                                  placeholder="Write {{ $label }} message…">{{ $t['body'] ?? '' }}</textarea>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <p class="text-[11px] text-gray-500">
        To add another language later (e.g. French), add it in <code class="text-xs">config/notification_templates.php</code> — a new column appears here automatically.
    </p>
</div>
