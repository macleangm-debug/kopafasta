@php
    $r = $record ?? null;
    $fields = \App\Services\Messaging\TemplatePersonalization::grouped();
@endphp
<div
    class="md:col-span-2 space-y-4"
    x-data="{
        target: 'body',
        insert(token) {
            const el = document.getElementById(this.target);
            if (! el) return;
            const snippet = '{{ ' + token + ' }}';
            const start = el.selectionStart ?? el.value.length;
            const end = el.selectionEnd ?? start;
            el.value = el.value.substring(0, start) + snippet + el.value.substring(end);
            el.focus();
            const pos = start + snippet.length;
            el.setSelectionRange(pos, pos);
        }
    }"
>
    <x-admin.step title="Notification template">
        <x-admin.input  name="code"    label="Code (event key)" :value="$r?->code" required placeholder="payment_received, repayment_due_soon…" />
        <x-admin.input  name="name"    label="Name"    :value="$r?->name" required />
        <x-admin.select name="locale" label="Language" :options="['en' => 'English', 'sw' => 'Kiswahili']" :value="$r?->locale ?? 'en'" required />
        <x-admin.select name="channel" label="Channel" :options="$channels" :value="$r?->channel ?? 'sms'" required />
        <x-admin.select name="is_active" label="Status" :options="['1'=>'Active','0'=>'Inactive']" :value="(string)($r?->is_active ?? '1')" required />

        <div class="md:col-span-2 rounded-xl bg-sky-50 ring-1 ring-sky-200 p-4">
            <p class="text-xs font-semibold text-sky-900 mb-1">Click to insert personalization</p>
            <p class="text-[11px] text-sky-800 mb-3">
                Click Subject or Body first, then tap a chip.
                Example: Hello <code>{{ '{{ name }}' }}</code>, balance <code>{{ '{{ balance }}' }}</code>.
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
                                <span class="font-mono text-brand text-[10px]">{{ '{{'.$field['token'].'}}' }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="md:col-span-2">
            <label class="block text-xs font-medium text-gray-600 mb-1" for="subject">Subject (email / push title)</label>
            <input id="subject" type="text" name="subject" value="{{ old('subject', $r?->subject) }}"
                   @focus="target = 'subject'"
                   class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand">
        </div>
        <div class="md:col-span-2">
            <label class="block text-xs font-medium text-gray-600 mb-1" for="body">Body</label>
            <textarea id="body" name="body" rows="8" required
                      @focus="target = 'body'"
                      class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand font-mono">{{ old('body', $r?->body) }}</textarea>
            <p class="mt-1 text-[11px] text-gray-500">Create one row per language with the same Code (e.g. <code>payment_received</code> in English and Kiswahili). Sends use the borrower’s locale when available.</p>
        </div>
    </x-admin.step>
</div>
