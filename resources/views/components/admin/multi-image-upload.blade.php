@props([
    'name' => 'photos',
    'removeName' => 'remove_photos',
    'existing' => [],
    'max' => 4,
    'min' => 1,
    'label' => 'Photos',
])

@php
    $existingPhotos = is_array($existing) ? $existing : [];
    $maxPhotos = max(1, (int) $max);
@endphp

<div
    x-data="{
        max: {{ $maxPhotos }},
        min: {{ max(1, (int) $min) }},
        existing: @js($existingPhotos),
        removed: [],
        previews: [],
        dragOver: false,
        get totalCount() {
            return this.existing.filter(p => !this.removed.includes(p)).length + this.previews.length;
        },
        canAddMore() {
            return this.totalCount < this.max;
        },
        addFiles(files) {
            const allowed = this.max - this.totalCount;
            Array.from(files).slice(0, allowed).forEach(file => {
                if (!file.type.startsWith('image/')) return;
                this.previews.push({ file, url: URL.createObjectURL(file) });
            });
        },
        removeExisting(path) {
            if (!this.removed.includes(path)) this.removed.push(path);
        },
        restoreExisting(path) {
            this.removed = this.removed.filter(p => p !== path);
        },
        removePreview(index) {
            URL.revokeObjectURL(this.previews[index].url);
            this.previews.splice(index, 1);
        },
        onDrop(e) {
            this.dragOver = false;
            if (this.canAddMore()) this.addFiles(e.dataTransfer.files);
        },
        syncInput() {
            const input = this.$refs.fileInput;
            if (!input) return;
            const dt = new DataTransfer();
            this.previews.forEach(p => dt.items.add(p.file));
            input.files = dt.files;
        }
    }"
    x-init="$watch('previews', () => syncInput()); $el.closest('form')?.addEventListener('submit', () => syncInput())"
    class="md:col-span-2 space-y-3"
>
    <div class="flex items-center justify-between gap-3">
        <p class="text-xs font-semibold text-gray-700">{{ $label }}</p>
        <p class="text-xs text-gray-500">Min {{ max(1, (int) $min) }}, max {{ $maxPhotos }} · first image is the cover</p>
    </div>

    {{-- Existing photos --}}
    @if (count($existingPhotos) > 0)
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            @foreach ($existingPhotos as $photo)
                <div class="relative rounded-lg overflow-hidden ring-1 ring-gray-200" x-show="!removed.includes(@js($photo))">
                    <img src="{{ marketplace_photo_url($photo) }}" alt="" class="aspect-square object-cover w-full">
                    <button type="button" @click="removeExisting(@js($photo))"
                            class="absolute inset-x-0 bottom-0 bg-black/60 text-white text-[10px] px-2 py-1.5 hover:bg-red-700/80">
                        Remove
                    </button>
                </div>
            @endforeach
        </div>
        <template x-for="path in removed" :key="path">
            <input type="hidden" name="{{ $removeName }}[]" :value="path">
        </template>
    @endif

    {{-- New previews --}}
    <template x-if="previews.length > 0">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <template x-for="(preview, index) in previews" :key="preview.url">
                <div class="relative rounded-lg overflow-hidden ring-1 ring-amber-200">
                    <img :src="preview.url" alt="" class="aspect-square object-cover w-full">
                    <button type="button" @click="removePreview(index)"
                            class="absolute inset-x-0 bottom-0 bg-black/60 text-white text-[10px] px-2 py-1.5 hover:bg-red-700/80">
                        Remove
                    </button>
                </div>
            </template>
        </div>
    </template>

    {{-- Drop zone --}}
    <div
        @dragover.prevent="dragOver = true"
        @dragleave.prevent="dragOver = false"
        @drop.prevent="onDrop($event)"
        :class="dragOver ? 'border-amber-400 bg-amber-50' : 'border-gray-300 bg-gray-50'"
        class="rounded-lg border-2 border-dashed p-6 text-center transition"
    >
        <p class="text-sm text-gray-600 mb-2">Drag and drop images here, or use the button below</p>
        <input type="file" x-ref="fileInput" name="{{ $name }}[]" accept="image/*" multiple class="hidden"
               @change="addFiles($event.target.files); $event.target.value = ''">
        <button type="button" @click="$refs.fileInput.click()" :disabled="!canAddMore()"
                class="rounded-lg bg-white ring-1 ring-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed">
            Add more images
        </button>
        <p class="mt-2 text-xs text-gray-400" x-text="totalCount + ' / ' + max + ' images selected'"></p>
    </div>

    @error($name)
        <p class="text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
