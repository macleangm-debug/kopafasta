@props([
    'name' => 'photos',
    'removeName' => 'remove_photos',
    'existing' => [],
    'max' => 4,
    'min' => 1,
    'label' => 'Photos',
])

@php
    $existingPhotos = is_array($existing) ? array_values(array_filter($existing)) : [];
    $maxPhotos = max(1, (int) $max);
    $minPhotos = max(1, (int) $min);
    $hasPhotoError = $errors->has($name) || $errors->has($name.'.*');
    $uid = 'miu-'.substr(md5($name.uniqid('', true)), 0, 10);
    $existingUrls = array_map(fn ($photo) => marketplace_photo_url($photo), $existingPhotos);
@endphp

<div
    class="md:col-span-2 space-y-3"
    @if ($hasPhotoError) data-has-error="true" @endif
    data-multi-image-upload
    data-name="{{ $name }}"
    data-max="{{ $maxPhotos }}"
    data-existing-count="{{ count($existingPhotos) }}"
    data-uid="{{ $uid }}"
>
    <div class="flex items-center justify-between gap-3">
        <p class="text-xs font-semibold text-gray-700">{{ $label }}</p>
        <p class="text-xs text-gray-500">Min {{ $minPhotos }}, max {{ $maxPhotos }} · first image is the cover · swipe to browse</p>
    </div>

    <div x-data="{
            index: 0,
            zoomed: false,
            photos: @js(array_values(array_filter($existingUrls))),
            paths: @js($existingPhotos),
            prev() { if (!this.photos.length) return; this.index = (this.index - 1 + this.photos.length) % this.photos.length },
            next() { if (!this.photos.length) return; this.index = (this.index + 1) % this.photos.length },
            touchStartX: 0,
            onTouchStart(e) { this.touchStartX = e.changedTouches[0].screenX },
            onTouchEnd(e) {
                const diff = e.changedTouches[0].screenX - this.touchStartX;
                if (Math.abs(diff) > 50) diff > 0 ? this.prev() : this.next();
            }
         }" class="space-y-3">
        @if (count($existingPhotos) > 0)
            <div class="relative rounded-xl overflow-hidden bg-gray-100 aspect-square sm:aspect-[4/3] ring-1 ring-gray-200"
                 data-existing-carousel
                 @touchstart="onTouchStart($event)" @touchend="onTouchEnd($event)">
                <template x-for="(photo, i) in photos" :key="'ex-'+i+'-'+photo">
                    <div class="absolute inset-0 transition-opacity duration-300"
                         :class="i === index ? 'opacity-100 z-10' : 'opacity-0 z-0 pointer-events-none'">
                        <button type="button" class="block w-full h-full cursor-zoom-in" @click="zoomed = true" title="Preview">
                            <img :src="photo" :alt="'Asset photo ' + (i + 1)"
                                 class="w-full h-full object-cover" loading="lazy" referrerpolicy="no-referrer"
                                 onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'w-full h-full grid place-items-center text-xs text-gray-400 px-2 text-center',textContent:'Image unavailable'}))">
                        </button>
                        <span x-show="i === 0" class="absolute top-2 left-2 z-20 rounded-full bg-amber-500 text-gray-900 text-[10px] font-semibold px-2 py-0.5">Cover</span>
                        <label class="absolute inset-x-0 bottom-0 z-20 bg-black/60 text-white text-[10px] px-2 py-1.5 flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" :name="@js($removeName) + '[]'" :value="paths[i]" data-remove-toggle class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                            Remove
                        </label>
                    </div>
                </template>

                <template x-if="photos.length > 1">
                    <div>
                        <button type="button" @click="prev()"
                                class="absolute left-2 top-1/2 -translate-y-1/2 z-20 size-9 rounded-full bg-white/90 shadow grid place-items-center text-gray-800 hover:bg-white"
                                aria-label="Previous photo">‹</button>
                        <button type="button" @click="next()"
                                class="absolute right-2 top-1/2 -translate-y-1/2 z-20 size-9 rounded-full bg-white/90 shadow grid place-items-center text-gray-800 hover:bg-white"
                                aria-label="Next photo">›</button>
                        <div class="absolute bottom-10 inset-x-0 z-20 flex justify-center gap-1.5">
                            <template x-for="(photo, i) in photos" :key="'dot-'+i">
                                <button type="button" @click="index = i"
                                        class="size-2 rounded-full transition"
                                        :class="i === index ? 'bg-white scale-125' : 'bg-white/50'"
                                        :aria-label="'Photo ' + (i + 1)"></button>
                            </template>
                        </div>
                        <div class="absolute top-2 right-2 z-20 rounded-full bg-black/40 text-white text-xs px-2.5 py-1"
                             x-text="(index + 1) + ' / ' + photos.length"></div>
                    </div>
                </template>
            </div>

            <div x-show="photos.length > 1" class="flex gap-2 overflow-x-auto pb-1">
                <template x-for="(photo, i) in photos" :key="'thumb-'+i">
                    <button type="button" @click="index = i"
                            class="shrink-0 size-14 rounded-lg overflow-hidden ring-2 transition"
                            :class="index === i ? 'ring-amber-500' : 'ring-transparent opacity-70 hover:opacity-100'">
                        <img :src="photo" alt="" class="w-full h-full object-cover" referrerpolicy="no-referrer">
                    </button>
                </template>
            </div>
        @else
            <div class="rounded-lg bg-slate-50 ring-1 ring-slate-200 px-4 py-3 text-xs text-slate-600">
                No photos saved yet. Add at least one image below — you can select multiple at once.
            </div>
        @endif

        <div x-show="zoomed" x-cloak x-transition
             class="fixed inset-0 z-[80] bg-black/70 flex items-center justify-center p-4"
             @keydown.escape.window="zoomed = false"
             @click.self="zoomed = false">
            <img :src="photos[index]" alt="Preview" class="max-h-[90vh] max-w-[95vw] object-contain rounded-xl shadow-2xl">
        </div>
    </div>

    <div data-preview-carousel class="hidden relative rounded-xl overflow-hidden bg-gray-100 aspect-square sm:aspect-[4/3] ring-1 ring-amber-200"></div>
    <div data-preview-thumbs class="hidden flex gap-2 overflow-x-auto pb-1"></div>

    {{-- Host for one named file input per pending photo (survives submit; picker stays unnamed). --}}
    <div data-file-host class="hidden" aria-hidden="true"></div>

    <div data-drop-zone class="rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 p-6 text-center transition">
        <p class="text-sm text-gray-600 mb-2">Drag and drop images here, or choose files</p>
        <label data-picker-label class="inline-flex rounded-lg bg-white ring-1 ring-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100 cursor-pointer">
            Add images
            <input
                type="file"
                data-picker
                accept="image/jpeg,image/png,image/webp,image/jpg,.jpg,.jpeg,.png,.webp"
                multiple
                class="sr-only"
            >
        </label>
        <p class="mt-2 text-xs text-gray-400" data-count-label>{{ count($existingPhotos) }} / {{ $maxPhotos }} images selected</p>
        <p class="mt-1 text-xs text-gray-400">You can select multiple images at once (up to {{ $maxPhotos }} total). Swipe across photos to review.</p>
    </div>

    @error($name)
        <p class="text-xs text-red-600">{{ $message }}</p>
    @enderror
    @error($name.'.*')
        <p class="text-xs text-red-600">{{ $message }}</p>
    @enderror
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
                    // Chrome (esp. Windows) often leaves file.type empty for valid images.
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

                function fileKey(file) {
                    return [file.name || '', file.size || 0, file.lastModified || 0].join(':');
                }

                function initMultiImageUpload(root) {
                    if (root.dataset.ready === '1') return;
                    root.dataset.ready = '1';

                    const max = Math.max(1, parseInt(root.dataset.max || '4', 10));
                    const existingCount = Math.max(0, parseInt(root.dataset.existingCount || '0', 10));
                    const fieldName = root.dataset.name || 'photos';
                    const picker = root.querySelector('[data-picker]');
                    const pickerLabel = root.querySelector('[data-picker-label]');
                    const previewCarousel = root.querySelector('[data-preview-carousel]');
                    const previewThumbs = root.querySelector('[data-preview-thumbs]');
                    const fileHost = root.querySelector('[data-file-host]');
                    const countLabel = root.querySelector('[data-count-label]');
                    const dropZone = root.querySelector('[data-drop-zone]');
                    let rejectNotice = root.querySelector('[data-reject-notice]');
                    if (!rejectNotice) {
                        rejectNotice = document.createElement('p');
                        rejectNotice.className = 'text-xs text-red-600 hidden';
                        rejectNotice.setAttribute('data-reject-notice', '');
                        if (dropZone && dropZone.parentNode) {
                            dropZone.parentNode.insertBefore(rejectNotice, dropZone.nextSibling);
                        }
                    }
                    /** @type {{ file: File, url: string }[]} */
                    let pending = [];
                    let previewIndex = 0;
                    let touchStartX = 0;

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

                    function removedCount() {
                        return root.querySelectorAll('[data-remove-toggle]:checked').length;
                    }

                    function keptExisting() {
                        return Math.max(0, existingCount - removedCount());
                    }

                    function remainingSlots() {
                        return Math.max(0, max - keptExisting() - pending.length);
                    }

                    function refreshCount() {
                        const total = keptExisting() + pending.length;
                        if (countLabel) {
                            countLabel.textContent = total + ' / ' + max + ' images selected';
                        }
                        const full = remainingSlots() <= 0;
                        if (picker) picker.disabled = full;
                        if (pickerLabel) {
                            pickerLabel.classList.toggle('opacity-50', full);
                            pickerLabel.classList.toggle('pointer-events-none', full);
                        }
                    }

                    /**
                     * Mirror pending files into real named inputs so the POST always
                     * includes photos[] even when DataTransfer on a single picker fails.
                     */
                    function syncHostFiles() {
                        if (!fileHost) return;
                        fileHost.innerHTML = '';
                        pending.forEach(function (entry) {
                            const input = document.createElement('input');
                            input.type = 'file';
                            input.name = fieldName + '[]';
                            // Do not set accept — Chrome rejects empty MIME types that still
                            // pass isImageFile() via extension, which surfaces as a native
                            // "Please select an item in the list" / file constraint error.
                            input.className = 'hidden';
                            const dt = new DataTransfer();
                            dt.items.add(entry.file);
                            input.files = dt.files;
                            fileHost.appendChild(input);
                        });
                    }

                    function renderPreviews() {
                        if (!previewCarousel || !previewThumbs) return;
                        previewCarousel.innerHTML = '';
                        previewThumbs.innerHTML = '';

                        if (!pending.length) {
                            previewCarousel.classList.add('hidden');
                            previewThumbs.classList.add('hidden');
                            refreshCount();
                            return;
                        }

                        if (previewIndex >= pending.length) previewIndex = pending.length - 1;
                        if (previewIndex < 0) previewIndex = 0;

                        pending.forEach(function (entry, index) {
                            const slide = document.createElement('div');
                            slide.className = 'absolute inset-0 transition-opacity duration-300 ' +
                                (index === previewIndex ? 'opacity-100 z-10' : 'opacity-0 z-0 pointer-events-none');

                            const img = document.createElement('img');
                            img.src = entry.url;
                            img.alt = 'New photo ' + (index + 1);
                            img.className = 'w-full h-full object-cover';
                            img.referrerPolicy = 'no-referrer';

                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.textContent = 'Remove';
                            btn.className = 'absolute inset-x-0 bottom-0 z-20 bg-black/60 text-white text-[10px] px-2 py-1.5 hover:bg-red-700/80';
                            btn.addEventListener('click', function () {
                                URL.revokeObjectURL(entry.url);
                                pending.splice(index, 1);
                                if (previewIndex >= pending.length) previewIndex = Math.max(0, pending.length - 1);
                                syncHostFiles();
                                renderPreviews();
                            });

                            slide.appendChild(img);
                            slide.appendChild(btn);
                            previewCarousel.appendChild(slide);

                            const thumb = document.createElement('button');
                            thumb.type = 'button';
                            thumb.className = 'shrink-0 size-14 rounded-lg overflow-hidden ring-2 transition ' +
                                (index === previewIndex ? 'ring-amber-500' : 'ring-transparent opacity-70 hover:opacity-100');
                            const thumbImg = document.createElement('img');
                            thumbImg.src = entry.url;
                            thumbImg.alt = '';
                            thumbImg.className = 'w-full h-full object-cover';
                            thumbImg.referrerPolicy = 'no-referrer';
                            thumb.appendChild(thumbImg);
                            thumb.addEventListener('click', function () {
                                previewIndex = index;
                                renderPreviews();
                            });
                            previewThumbs.appendChild(thumb);
                        });

                        if (pending.length > 1) {
                            const prevBtn = document.createElement('button');
                            prevBtn.type = 'button';
                            prevBtn.textContent = '‹';
                            prevBtn.className = 'absolute left-2 top-1/2 -translate-y-1/2 z-20 size-9 rounded-full bg-white/90 shadow grid place-items-center text-gray-800 hover:bg-white';
                            prevBtn.addEventListener('click', function () {
                                previewIndex = (previewIndex - 1 + pending.length) % pending.length;
                                renderPreviews();
                            });
                            const nextBtn = document.createElement('button');
                            nextBtn.type = 'button';
                            nextBtn.textContent = '›';
                            nextBtn.className = 'absolute right-2 top-1/2 -translate-y-1/2 z-20 size-9 rounded-full bg-white/90 shadow grid place-items-center text-gray-800 hover:bg-white';
                            nextBtn.addEventListener('click', function () {
                                previewIndex = (previewIndex + 1) % pending.length;
                                renderPreviews();
                            });
                            const counter = document.createElement('div');
                            counter.className = 'absolute top-2 right-2 z-20 rounded-full bg-black/40 text-white text-xs px-2.5 py-1';
                            counter.textContent = (previewIndex + 1) + ' / ' + pending.length;
                            previewCarousel.appendChild(prevBtn);
                            previewCarousel.appendChild(nextBtn);
                            previewCarousel.appendChild(counter);
                        }

                        previewCarousel.classList.remove('hidden');
                        previewThumbs.classList.toggle('hidden', pending.length <= 1);
                        refreshCount();
                    }

                    function addFiles(fileList) {
                        const slots = remainingSlots();
                        const chosen = Array.from(fileList || []);
                        if (!chosen.length) return;

                        const known = {};
                        pending.forEach(function (entry) {
                            known[fileKey(entry.file)] = true;
                        });

                        let accepted = 0;
                        let rejected = 0;
                        chosen.slice(0, slots + chosen.length).forEach(function (raw) {
                            if (accepted >= slots) return;
                            if (!isImageFile(raw)) {
                                rejected += 1;
                                return;
                            }
                            const file = normalizeImageFile(raw);
                            const key = fileKey(file);
                            if (known[key]) return;
                            known[key] = true;
                            pending.push({ file: file, url: URL.createObjectURL(file) });
                            accepted += 1;
                        });

                        if (accepted === 0 && rejected > 0) {
                            setRejectNotice('Could not add those files. Use JPG, PNG, or WebP images.');
                        } else {
                            setRejectNotice('');
                        }

                        if (accepted > 0) previewIndex = pending.length - 1;
                        syncHostFiles();
                        renderPreviews();
                        if (picker) picker.value = '';
                    }

                    if (picker) {
                        picker.addEventListener('change', function () {
                            const chosen = Array.from(picker.files || []);
                            addFiles(chosen);
                        });
                    }

                    root.querySelectorAll('[data-remove-toggle]').forEach(function (box) {
                        box.addEventListener('change', refreshCount);
                    });

                    if (previewCarousel) {
                        previewCarousel.addEventListener('touchstart', function (e) {
                            touchStartX = e.changedTouches[0].screenX;
                        }, { passive: true });
                        previewCarousel.addEventListener('touchend', function (e) {
                            if (pending.length < 2) return;
                            const diff = e.changedTouches[0].screenX - touchStartX;
                            if (Math.abs(diff) > 50) {
                                previewIndex = diff > 0
                                    ? (previewIndex - 1 + pending.length) % pending.length
                                    : (previewIndex + 1) % pending.length;
                                renderPreviews();
                            }
                        }, { passive: true });
                    }

                    if (dropZone) {
                        ['dragenter', 'dragover'].forEach(function (evt) {
                            dropZone.addEventListener(evt, function (e) {
                                e.preventDefault();
                                e.stopPropagation();
                                dropZone.classList.add('border-amber-400', 'bg-amber-50');
                            });
                        });
                        dropZone.addEventListener('dragleave', function (e) {
                            e.preventDefault();
                            dropZone.classList.remove('border-amber-400', 'bg-amber-50');
                        });
                        dropZone.addEventListener('drop', function (e) {
                            e.preventDefault();
                            e.stopPropagation();
                            dropZone.classList.remove('border-amber-400', 'bg-amber-50');
                            addFiles(e.dataTransfer && e.dataTransfer.files);
                        });
                    }

                    const form = root.closest('form');
                    if (form && !form.dataset.miuSubmitGuard) {
                        form.dataset.miuSubmitGuard = '1';
                        form.addEventListener('submit', function (e) {
                            syncHostFiles();
                            const hostInputs = fileHost
                                ? Array.from(fileHost.querySelectorAll('input[type="file"]'))
                                : [];
                            const hasPendingFiles = hostInputs.some(function (input) {
                                return input.files && input.files.length > 0;
                            });

                            if (dropZone) {
                                dropZone.classList.add('opacity-60', 'pointer-events-none');
                                const hint = dropZone.querySelector('p');
                                if (hint && !dropZone.dataset.saving) {
                                    dropZone.dataset.saving = '1';
                                    hint.textContent = 'Saving images…';
                                }
                            }

                            // Never strip photos when pending previews exist.
                            if (pending.length > 0 && !hasPendingFiles) {
                                e.preventDefault();
                                setRejectNotice('Could not attach the selected images. Please remove and add them again.');
                                if (dropZone) {
                                    dropZone.classList.remove('opacity-60', 'pointer-events-none');
                                    delete dropZone.dataset.saving;
                                    const hint = dropZone.querySelector('p');
                                    if (hint) hint.textContent = 'Drag and drop images here, or choose files';
                                }
                            }
                        });
                    }

                    refreshCount();
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
