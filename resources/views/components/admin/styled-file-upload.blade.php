@props([
    'name' => 'file',
    'label' => 'Upload',
    'accept' => 'image/png,image/jpeg,image/webp',
    'required' => false,
    'preview' => true,
    'removeBackgroundName' => null,
    'removeBackgroundLabel' => 'Remove image background',
    'removeBackgroundDefault' => true,
])

<div
    x-data="{
        fileName: '',
        previewUrl: null,
        removeBg: @js((bool) $removeBackgroundDefault),
        pick() { this.$refs.input.click() },
        onChange(e) {
            const file = e.target.files?.[0];
            if (!file) {
                this.fileName = '';
                this.previewUrl = null;
                return;
            }
            this.fileName = file.name;
            if (@js($preview) && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = (ev) => { this.previewUrl = ev.target.result; };
                reader.readAsDataURL(file);
            }
        },
        clear() {
            this.fileName = '';
            this.previewUrl = null;
            if (this.$refs.input) this.$refs.input.value = '';
        }
    }"
    class="space-y-3"
>
    <input
        x-ref="input"
        type="file"
        name="{{ $name }}"
        accept="{{ $accept }}"
        class="sr-only"
        @if ($required) required @endif
        @change="onChange($event)"
    >

    <button type="button"
            @click="pick()"
            class="inline-flex items-center gap-2 rounded-xl bg-brand-gold px-4 py-2.5 text-sm font-bold text-brand hover:brightness-95 ring-1 ring-brand/10">
        <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M9 2a1 1 0 012 0v6h6a1 1 0 110 2h-6v6a1 1 0 11-2 0v-6H3a1 1 0 110-2h6V2z"/></svg>
        {{ $label }}
    </button>

    <template x-if="previewUrl || fileName">
        <div class="rounded-xl ring-1 ring-brand/15 bg-white p-3 space-y-3 max-w-md">
            <template x-if="previewUrl">
                <img :src="previewUrl" alt=""
                     class="h-24 object-contain bg-[linear-gradient(45deg,#f3f4f6_25%,transparent_25%),linear-gradient(-45deg,#f3f4f6_25%,transparent_25%),linear-gradient(45deg,transparent_75%,#f3f4f6_75%),linear-gradient(-45deg,transparent_75%,#f3f4f6_75%)] bg-[length:12px_12px] bg-[position:0_0,0_6px,6px_-6px,-6px_0] rounded-lg">
            </template>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs text-gray-600 truncate" x-text="fileName"></p>
                <button type="button" @click="pick()" class="text-xs font-semibold text-brand hover:underline">Change image</button>
            </div>
            @if ($removeBackgroundName)
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="{{ $removeBackgroundName }}" value="1" x-model="removeBg" class="rounded border-gray-300 text-brand">
                    {{ $removeBackgroundLabel }}
                </label>
            @endif
        </div>
    </template>
</div>
