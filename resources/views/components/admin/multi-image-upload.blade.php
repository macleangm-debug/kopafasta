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
        <p class="text-xs text-gray-500">Min {{ $minPhotos }}, max {{ $maxPhotos }} · first image is the cover</p>
    </div>

    <div x-data="{ expandedUrl: null }" class="space-y-3">
    @if (count($existingPhotos) > 0)
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3" data-existing-grid>
            @foreach ($existingPhotos as $index => $photo)
                @php $photoUrl = marketplace_photo_url($photo); @endphp
                <div class="relative rounded-lg overflow-hidden ring-1 ring-gray-200 bg-gray-100 group block" data-existing-card>
                    @if ($photoUrl)
                        <button type="button" class="block w-full cursor-zoom-in" @click="expandedUrl = @js($photoUrl)" title="Preview">
                            <img src="{{ $photoUrl }}" alt="Asset photo {{ $index + 1 }}"
                                 class="aspect-square object-cover w-full" loading="lazy" referrerpolicy="no-referrer"
                                 onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'aspect-square grid place-items-center text-xs text-gray-400 px-2 text-center',textContent:'Image unavailable'}))">
                        </button>
                    @else
                        <div class="aspect-square grid place-items-center text-xs text-gray-400 px-2 text-center">Image unavailable</div>
                    @endif
                    @if ($index === 0)
                        <span class="absolute top-2 left-2 rounded-full bg-amber-500 text-gray-900 text-[10px] font-semibold px-2 py-0.5">Cover</span>
                    @endif
                    <label class="absolute inset-x-0 bottom-0 bg-black/60 text-white text-[10px] px-2 py-1.5 flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="{{ $removeName }}[]" value="{{ $photo }}" data-remove-toggle class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                        Remove
                    </label>
                </div>
            @endforeach
        </div>
    @else
        <div class="rounded-lg bg-slate-50 ring-1 ring-slate-200 px-4 py-3 text-xs text-slate-600">
            No photos saved yet. Add at least one image below — you can select multiple at once.
        </div>
    @endif

    <div x-show="expandedUrl" x-cloak x-transition
         class="fixed inset-0 z-[80] bg-black/70 flex items-center justify-center p-4"
         @keydown.escape.window="expandedUrl = null"
         @click.self="expandedUrl = null">
        <img :src="expandedUrl" alt="Preview" class="max-h-[90vh] max-w-[95vw] object-contain rounded-xl shadow-2xl">
    </div>
    </div>

    <div data-preview-grid class="grid grid-cols-2 sm:grid-cols-4 gap-3 hidden"></div>

    {{-- Host for one named file input per pending photo (survives submit; picker stays unnamed). --}}
    <div data-file-host class="hidden" aria-hidden="true"></div>

    <div data-drop-zone class="rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 p-6 text-center transition">
        <p class="text-sm text-gray-600 mb-2">Drag and drop images here, or choose files</p>
        <label data-picker-label class="inline-flex rounded-lg bg-white ring-1 ring-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100 cursor-pointer">
            Add images
            <input
                type="file"
                data-picker
                accept="image/jpeg,image/png,image/webp,image/jpg"
                multiple
                class="sr-only"
            >
        </label>
        <p class="mt-2 text-xs text-gray-400" data-count-label>{{ count($existingPhotos) }} / {{ $maxPhotos }} images selected</p>
        <p class="mt-1 text-xs text-gray-400">You can select multiple images at once (up to {{ $maxPhotos }} total).</p>
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

                function isImageFile(file) {
                    if (!file) return false;
                    if (file.type && file.type.startsWith('image/')) return true;
                    // Chrome (esp. Windows) often leaves file.type empty for valid images.
                    return IMAGE_EXT.test(file.name || '');
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
                    const previewGrid = root.querySelector('[data-preview-grid]');
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
                        if (!previewGrid) return;
                        previewGrid.innerHTML = '';

                        pending.forEach(function (entry, index) {
                            const card = document.createElement('div');
                            card.className = 'relative rounded-lg overflow-hidden ring-1 ring-amber-200 bg-gray-100';

                            const img = document.createElement('img');
                            img.src = entry.url;
                            img.alt = 'New photo ' + (index + 1);
                            img.className = 'aspect-square object-cover w-full';
                            img.referrerPolicy = 'no-referrer';

                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.textContent = 'Remove';
                            btn.className = 'absolute inset-x-0 bottom-0 bg-black/60 text-white text-[10px] px-2 py-1.5 hover:bg-red-700/80';
                            btn.addEventListener('click', function () {
                                URL.revokeObjectURL(entry.url);
                                pending.splice(index, 1);
                                syncHostFiles();
                                renderPreviews();
                            });

                            card.appendChild(img);
                            card.appendChild(btn);
                            previewGrid.appendChild(card);
                        });

                        previewGrid.classList.toggle('hidden', pending.length === 0);
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
                        chosen.slice(0, slots + chosen.length).forEach(function (file) {
                            if (accepted >= slots) return;
                            if (!isImageFile(file)) {
                                rejected += 1;
                                return;
                            }
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
