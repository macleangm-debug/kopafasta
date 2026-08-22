@props([
    'name' => 'photos',
    'removeName' => 'remove_photos',
    'coverName' => 'cover_path',
    'existing' => [],
    'max' => 4,
    'min' => 1,
    'label' => null,
])

@php
    $existingPhotos = array_values(array_filter(is_array($existing) ? $existing : []));
    $maxPhotos = min(4, max(1, (int) $max));
    $minPhotos = max(1, (int) $min);
    $hasPhotoError = $errors instanceof \Illuminate\Support\ViewErrorBag
        && ($errors->has($name) || $errors->has($name.'.*'));
    $uid = 'miu-'.substr(md5($name.uniqid('', true)), 0, 10);
    $label = $label ?? __('borrower.marketplace.asset_photos');
    $coverPath = old($coverName, $existingPhotos[0] ?? null);
    $slots = [];
    for ($i = 0; $i < $maxPhotos; $i++) {
        $path = $existingPhotos[$i] ?? null;
        $slots[] = [
            'index' => $i,
            'path' => $path,
            'url' => $path ? marketplace_photo_url($path) : null,
            'required' => $i < $minPhotos && count($existingPhotos) === 0,
        ];
    }
@endphp

{{-- Collateral-style compact slot grid (max 4) + explicit cover picker --}}
<div
    class="md:col-span-2 space-y-3"
    @if ($hasPhotoError) data-has-error="true" @endif
    data-multi-image-upload
    data-name="{{ $name }}"
    data-max="{{ $maxPhotos }}"
    data-uid="{{ $uid }}"
    x-data="{
        lightbox: null,
        cover: @js($coverPath),
        openPreview(url) { if (url) this.lightbox = url },
        closePreview() { this.lightbox = null },
    }"
>
    <div class="flex items-center justify-between gap-3">
        <div>
            <p class="text-xs font-semibold text-gray-700">{{ $label }}</p>
            <p class="text-[11px] text-gray-500 mt-0.5">{{ __('borrower.marketplace.asset_photos_hint', ['max' => $maxPhotos]) }}</p>
        </div>
        <p class="text-xs text-gray-500 tabular-nums">{{ count($existingPhotos) }} / {{ $maxPhotos }}</p>
    </div>

    <div class="grid grid-cols-2 gap-3">
        @foreach ($slots as $slot)
            <div class="rounded-xl ring-1 ring-gray-200 p-3 bg-white">
                <div class="flex items-center justify-between gap-2 mb-2">
                    <label class="text-[11px] font-semibold text-gray-700">
                        {{ __('borrower.marketplace.asset_photo_n', ['n' => $slot['index'] + 1]) }}
                        @if ($slot['required']) <span class="text-red-500">*</span> @endif
                    </label>
                    @if ($slot['path'])
                        <label class="inline-flex items-center gap-1 text-[10px] font-semibold text-brand cursor-pointer">
                            <input type="radio"
                                   name="{{ $coverName }}"
                                   value="{{ $slot['path'] }}"
                                   x-model="cover"
                                   class="text-brand focus:ring-brand size-3.5">
                            {{ __('borrower.marketplace.set_as_cover') }}
                        </label>
                    @endif
                </div>

                @if ($slot['url'])
                    <div class="relative">
                        <button type="button"
                                @click="openPreview(@js($slot['url']))"
                                class="block w-full aspect-[4/3] rounded-lg overflow-hidden ring-1 ring-gray-200 bg-gray-100 cursor-zoom-in">
                            <img src="{{ $slot['url'] }}" alt="" class="w-full h-full object-cover" loading="lazy" referrerpolicy="no-referrer">
                        </button>
                        <span x-show="cover === @js($slot['path'])"
                              class="absolute top-2 left-2 rounded-full bg-brand-gold text-brand text-[10px] font-semibold px-2 py-0.5">
                            {{ __('borrower.marketplace.cover_badge') }}
                        </span>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <button type="button" @click="openPreview(@js($slot['url']))"
                                    class="inline-flex items-center rounded-full bg-white ring-1 ring-gray-200 px-2.5 py-1 text-[11px] font-semibold text-gray-700 hover:bg-gray-50">
                                {{ __('borrower.profile.view_document') }}
                            </button>
                            <label class="inline-flex items-center rounded-full bg-white ring-1 ring-brand/20 px-2.5 py-1 text-[11px] font-semibold text-brand hover:bg-brand-muted cursor-pointer">
                                {{ __('borrower.profile.replace_photo') }}
                                {{-- No name on picker — host carries named File clones (avoids Chrome accept popup). --}}
                                <input type="file"
                                       accept="image/jpeg,image/png,image/webp,image/jpg,.jpg,.jpeg,.png,.webp"
                                       class="sr-only"
                                       data-slot-file
                                       data-slot-index="{{ $slot['index'] }}">
                            </label>
                            <label class="inline-flex items-center gap-1 rounded-full bg-white ring-1 ring-red-200 px-2.5 py-1 text-[11px] font-semibold text-red-700 hover:bg-red-50 cursor-pointer">
                                <input type="checkbox" name="{{ $removeName }}[]" value="{{ $slot['path'] }}"
                                       class="rounded border-gray-300 text-red-600 focus:ring-red-500 size-3.5"
                                       data-remove-toggle>
                                {{ __('borrower.profile.delete_photo') }}
                            </label>
                        </div>
                    </div>
                @else
                    <label class="flex flex-col items-center justify-center gap-2 aspect-[4/3] rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 hover:bg-brand-muted hover:border-brand/40 cursor-pointer transition text-center px-2">
                        <span class="text-lg leading-none text-gray-400">＋</span>
                        <span class="text-[11px] font-semibold text-gray-600">{{ __('borrower.marketplace.add_photo_slot') }}</span>
                        <input type="file"
                               accept="image/jpeg,image/png,image/webp,image/jpg,.jpg,.jpeg,.png,.webp"
                               class="sr-only"
                               data-slot-file
                               data-slot-index="{{ $slot['index'] }}"
                               @if ($slot['required']) data-required-slot="1" @endif>
                        <img data-slot-preview="{{ $slot['index'] }}" alt="" class="hidden absolute inset-0 w-full h-full object-cover rounded-lg">
                    </label>
                    <label class="mt-2 hidden items-center gap-1 text-[10px] font-semibold text-brand cursor-pointer"
                           data-new-cover-label="{{ $slot['index'] }}">
                        <input type="radio"
                               name="{{ $coverName }}"
                               value="__new_{{ $slot['index'] }}"
                               x-model="cover"
                               class="text-brand focus:ring-brand size-3.5">
                        {{ __('borrower.marketplace.set_as_cover') }}
                    </label>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Host for Chrome-safe named File clones (empty MIME normalize) --}}
    <div data-file-host class="hidden" aria-hidden="true"></div>
    <p class="text-xs text-red-600 hidden" data-reject-notice></p>

    @error($name)
        <p class="text-xs text-red-600">{{ $message }}</p>
    @enderror
    @error($name.'.*')
        <p class="text-xs text-red-600">{{ $message }}</p>
    @enderror

    <template x-teleport="body">
        <div x-show="lightbox" x-cloak x-transition
             class="fixed inset-0 z-[90] bg-black/80 flex items-center justify-center p-4"
             @keydown.escape.window="closePreview()"
             @click.self="closePreview()">
            <button type="button" class="absolute top-4 right-4 text-white/90 text-2xl font-semibold" @click="closePreview()">×</button>
            <img :src="lightbox" alt="" class="max-h-[90vh] max-w-[95vw] object-contain rounded-xl shadow-2xl">
        </div>
    </template>
</div>

@once
    @push('scripts')
        <script>
            (function () {
                const IMAGE_EXT = /\.(jpe?g|png|webp)$/i;
                const MIME_BY_EXT = {
                    jpg: 'image/jpeg',
                    jpeg: 'image/jpeg',
                    png: 'image/png',
                    webp: 'image/webp',
                };

                function isImageFile(file) {
                    if (!file) return false;
                    if (file.type && file.type.startsWith('image/')) return true;
                    return IMAGE_EXT.test(file.name || '');
                }

                function normalizeImageFile(file) {
                    if (!file) return null;
                    if (file.type && file.type.startsWith('image/')) return file;
                    const match = (file.name || '').match(/\.([a-z0-9]+)$/i);
                    const ext = match ? match[1].toLowerCase() : '';
                    const mime = MIME_BY_EXT[ext];
                    if (!mime) return file;
                    try {
                        return new File([file], file.name || ('photo.' + ext), {
                            type: mime,
                            lastModified: file.lastModified || Date.now(),
                        });
                    } catch (e) {
                        return file;
                    }
                }

                function initMultiImageUpload(root) {
                    if (root.dataset.ready === '1') return;
                    root.dataset.ready = '1';

                    const fieldName = root.dataset.name || 'photos';
                    const fileHost = root.querySelector('[data-file-host]');
                    const rejectNotice = root.querySelector('[data-reject-notice]');
                    /** @type {Record<string, File>} */
                    const pendingBySlot = {};

                    function setRejectNotice(message) {
                        if (!rejectNotice) return;
                        if (message) {
                            rejectNotice.textContent = message;
                            rejectNotice.classList.remove('hidden');
                        } else {
                            rejectNotice.textContent = '';
                            rejectNotice.classList.add('hidden');
                        }
                    }

                    function syncHostFiles() {
                        if (!fileHost) return;
                        fileHost.innerHTML = '';
                        Object.keys(pendingBySlot).forEach(function (slot) {
                            const file = pendingBySlot[slot];
                            if (!file) return;
                            const input = document.createElement('input');
                            input.type = 'file';
                            input.name = fieldName + '[' + slot + ']';
                            // No accept on cloned inputs — Chrome rejects empty MIME otherwise.
                            input.className = 'hidden';
                            const dt = new DataTransfer();
                            dt.items.add(file);
                            input.files = dt.files;
                            fileHost.appendChild(input);
                        });
                    }

                    function clearPickerName(picker) {
                        // Prevent double-submit: picker keeps UI, host carries named files.
                        picker.removeAttribute('name');
                    }

                    root.querySelectorAll('[data-slot-file]').forEach(function (picker) {
                        picker.addEventListener('change', function () {
                            const raw = picker.files && picker.files[0];
                            const slot = picker.getAttribute('data-slot-index') || '0';
                            if (!raw) {
                                delete pendingBySlot[slot];
                                syncHostFiles();
                                return;
                            }
                            if (!isImageFile(raw)) {
                                setRejectNotice('Could not add that file. Use JPG, PNG, or WebP.');
                                picker.value = '';
                                return;
                            }
                            setRejectNotice('');
                            const file = normalizeImageFile(raw);
                            pendingBySlot[slot] = file;
                            clearPickerName(picker);
                            syncHostFiles();
                            // Clear accept-constrained picker so Chrome does not block submit
                            // with "Please select an item in the list" on empty MIME files.
                            picker.value = '';

                            const preview = root.querySelector('[data-slot-preview="' + slot + '"]');
                            const label = root.querySelector('[data-new-cover-label="' + slot + '"]');
                            if (preview) {
                                preview.src = URL.createObjectURL(file);
                                preview.classList.remove('hidden');
                                preview.classList.add('block');
                                const wrap = preview.closest('label');
                                if (wrap) {
                                    wrap.classList.add('relative', 'overflow-hidden', 'border-solid', 'border-brand/40', 'bg-black');
                                    wrap.querySelectorAll('span').forEach(function (el) { el.classList.add('hidden'); });
                                }
                            }
                            if (label) {
                                label.classList.remove('hidden');
                                label.classList.add('inline-flex');
                            }

                            // If no cover chosen yet, pick this new slot.
                            const checked = root.querySelector('input[type="radio"][name$="cover_path"]:checked, input[type="radio"][name="cover_path"]:checked');
                            if (!checked) {
                                const radio = label && label.querySelector('input[type="radio"]');
                                if (radio) {
                                    radio.checked = true;
                                    radio.dispatchEvent(new Event('input', { bubbles: true }));
                                    radio.dispatchEvent(new Event('change', { bubbles: true }));
                                }
                            }
                        });
                    });

                    const form = root.closest('form');
                    if (form && !form.dataset.miuSubmitGuard) {
                        form.dataset.miuSubmitGuard = '1';
                        form.addEventListener('submit', function () {
                            // Ensure host mirrors latest pending files before POST.
                            root.querySelectorAll('[data-slot-file]').forEach(function (picker) {
                                if (picker.files && picker.files[0] && isImageFile(picker.files[0])) {
                                    const slot = picker.getAttribute('data-slot-index') || '0';
                                    pendingBySlot[slot] = normalizeImageFile(picker.files[0]);
                                }
                                clearPickerName(picker);
                                picker.value = '';
                            });
                            syncHostFiles();
                        });
                    }
                }

                function boot() {
                    document.querySelectorAll('[data-multi-image-upload]').forEach(initMultiImageUpload);
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', boot);
                } else {
                    boot();
                }
            })();
        </script>
    @endpush
@endonce
