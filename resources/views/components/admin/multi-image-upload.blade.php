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

    @if (count($existingPhotos) > 0)
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3" data-existing-grid>
            @foreach ($existingPhotos as $index => $photo)
                @php $photoUrl = marketplace_photo_url($photo); @endphp
                <label class="relative rounded-lg overflow-hidden ring-1 ring-gray-200 bg-gray-100 cursor-pointer group block" data-existing-card>
                    @if ($photoUrl)
                        <img src="{{ $photoUrl }}" alt="Asset photo {{ $index + 1 }}" class="aspect-square object-cover w-full" loading="lazy">
                    @else
                        <div class="aspect-square grid place-items-center text-xs text-gray-400 px-2 text-center">Image unavailable</div>
                    @endif
                    @if ($index === 0)
                        <span class="absolute top-2 left-2 rounded-full bg-amber-500 text-gray-900 text-[10px] font-semibold px-2 py-0.5">Cover</span>
                    @endif
                    <span class="absolute inset-x-0 bottom-0 bg-black/60 text-white text-[10px] px-2 py-1.5 flex items-center gap-2">
                        <input type="checkbox" name="{{ $removeName }}[]" value="{{ $photo }}" data-remove-toggle class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                        Remove
                    </span>
                </label>
            @endforeach
        </div>
    @endif

    <div data-preview-grid class="grid grid-cols-2 sm:grid-cols-4 gap-3 hidden"></div>

    <div data-drop-zone class="rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 p-6 text-center transition">
        <p class="text-sm text-gray-600 mb-2">Drag and drop images here, or choose files</p>
        <label data-picker-label class="inline-flex rounded-lg bg-white ring-1 ring-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100 cursor-pointer">
            Add images
            <input type="file" data-picker accept="image/jpeg,image/png,image/webp,image/jpg" multiple class="sr-only">
        </label>
        <p class="mt-2 text-xs text-gray-400" data-count-label>{{ count($existingPhotos) }} / {{ $maxPhotos }} images selected</p>
        <p class="mt-1 text-xs text-gray-400">You can select multiple images at once.</p>
    </div>

    <div data-file-host id="{{ $uid }}-host" class="hidden" aria-hidden="true"></div>

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
                function initMultiImageUpload(root) {
                    if (root.dataset.ready === '1') return;
                    root.dataset.ready = '1';

                    const fieldName = root.dataset.name || 'photos';
                    const max = Math.max(1, parseInt(root.dataset.max || '4', 10));
                    const existingCount = Math.max(0, parseInt(root.dataset.existingCount || '0', 10));
                    const picker = root.querySelector('[data-picker]');
                    const pickerLabel = root.querySelector('[data-picker-label]');
                    const host = root.querySelector('[data-file-host]');
                    const previewGrid = root.querySelector('[data-preview-grid]');
                    const countLabel = root.querySelector('[data-count-label]');
                    const dropZone = root.querySelector('[data-drop-zone]');
                    const files = [];

                    function removedCount() {
                        return root.querySelectorAll('[data-remove-toggle]:checked').length;
                    }

                    function keptExisting() {
                        return Math.max(0, existingCount - removedCount());
                    }

                    function remainingSlots() {
                        return Math.max(0, max - keptExisting() - files.length);
                    }

                    function refreshCount() {
                        const total = keptExisting() + files.length;
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

                    function syncHost() {
                        if (!host || !previewGrid) return;
                        host.innerHTML = '';
                        previewGrid.innerHTML = '';

                        files.forEach(function (entry, index) {
                            const input = document.createElement('input');
                            input.type = 'file';
                            input.name = fieldName + '[]';
                            input.className = 'sr-only';
                            const dt = new DataTransfer();
                            dt.items.add(entry.file);
                            input.files = dt.files;
                            host.appendChild(input);

                            const card = document.createElement('div');
                            card.className = 'relative rounded-lg overflow-hidden ring-1 ring-amber-200 bg-gray-100';
                            const img = document.createElement('img');
                            img.src = entry.url;
                            img.alt = '';
                            img.className = 'aspect-square object-cover w-full';
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.textContent = 'Remove';
                            btn.className = 'absolute inset-x-0 bottom-0 bg-black/60 text-white text-[10px] px-2 py-1.5 hover:bg-red-700/80';
                            btn.addEventListener('click', function () {
                                URL.revokeObjectURL(entry.url);
                                files.splice(index, 1);
                                syncHost();
                            });
                            card.appendChild(img);
                            card.appendChild(btn);
                            previewGrid.appendChild(card);
                        });

                        previewGrid.classList.toggle('hidden', files.length === 0);
                        refreshCount();
                    }

                    function addFileList(fileList) {
                        const slots = remainingSlots();
                        Array.from(fileList || []).slice(0, slots).forEach(function (file) {
                            if (!file.type || !file.type.startsWith('image/')) return;
                            files.push({ file: file, url: URL.createObjectURL(file) });
                        });
                        syncHost();
                    }

                    if (picker) {
                        picker.addEventListener('change', function () {
                            addFileList(picker.files);
                            picker.value = '';
                        });
                    }

                    root.querySelectorAll('[data-remove-toggle]').forEach(function (box) {
                        box.addEventListener('change', refreshCount);
                    });

                    if (dropZone) {
                        dropZone.addEventListener('dragover', function (e) {
                            e.preventDefault();
                            dropZone.classList.add('border-amber-400', 'bg-amber-50');
                        });
                        dropZone.addEventListener('dragleave', function (e) {
                            e.preventDefault();
                            dropZone.classList.remove('border-amber-400', 'bg-amber-50');
                        });
                        dropZone.addEventListener('drop', function (e) {
                            e.preventDefault();
                            dropZone.classList.remove('border-amber-400', 'bg-amber-50');
                            addFileList(e.dataTransfer.files);
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
