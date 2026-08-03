<x-site.borrower-layout :title="brand_title(__('borrower.profile.my_collaterals'))" active="profile" content-width="wide">

    @php
        $adding = request()->boolean('add') || filled(old('asset_type'));
        $selectedType = old('asset_type', request('type'));
        $typeIcons = \App\Models\CustomerAsset::typeIcons();
        $detailFields = $selectedType ? \App\Models\CustomerAsset::detailFieldsFor($selectedType) : [];
    @endphp

    <div x-data="{ addOpen: false, openAsset: {{ (int) request('edit', 0) ?: 'null' }}, lightbox: null }"
         x-init="if (openAsset) { $nextTick(() => { const el = document.getElementById('asset-edit-' + openAsset); if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }); }">
        @include('site.borrower.profile._profile_shell', [
            'title' => __('borrower.profile.my_collaterals'),
            'subtitle' => __('borrower.profile.my_assets_hint'),
            'customer' => $customer,
            'active' => 'assets',
        ])

        @if ($adding && $selectedType)
            {{-- ============ Item 18: type-specific add form ============ --}}
            <x-site.profile-section-card :title="($typeIcons[$selectedType] ?? '📦').'  '.__('borrower.profile.add_asset').': '.($assetTypes[$selectedType] ?? $selectedType)">
                @php
                    $photoSlots = [
                        ['key' => 0, 'label' => __('borrower.profile.asset_photo_front'), 'required' => true, 'hint' => __('borrower.profile.asset_photo_front_hint')],
                        ['key' => 1, 'label' => __('borrower.profile.asset_photo_back'), 'required' => true, 'hint' => __('borrower.profile.asset_photo_back_hint')],
                        ['key' => 2, 'label' => __('borrower.profile.asset_photo_side'), 'required' => false, 'hint' => __('borrower.profile.asset_photo_side_hint')],
                        ['key' => 3, 'label' => __('borrower.profile.asset_photo_angle'), 'required' => false, 'hint' => __('borrower.profile.asset_photo_angle_hint')],
                        ['key' => 4, 'label' => __('borrower.profile.asset_photo').' 5', 'required' => false, 'hint' => __('borrower.profile.asset_photo_extra_hint')],
                        ['key' => 5, 'label' => __('borrower.profile.asset_photo').' 6', 'required' => false, 'hint' => __('borrower.profile.asset_photo_extra_hint')],
                    ];
                    $isVehicle = $selectedType === 'vehicle';
                @endphp
                <form method="POST" action="{{ route('site.borrower.profile.assets.store') }}" enctype="multipart/form-data" class="space-y-6"
                      x-data="{
                          saving: false,
                          uploading: false,
                          step: 1,
                          photoIndex: 0,
                          isVehicle: @js($isVehicle),
                          photoCount: {{ count($photoSlots) }},
                          get lastStep() { return this.isVehicle ? 4 : 3; },
                          next() { if (this.step < this.lastStep) this.step++; },
                          prev() {
                              if (this.step === 2 && this.photoIndex > 0) { this.photoIndex--; return; }
                              if (this.step > 1) this.step--;
                          },
                          nextPhoto() {
                              if (this.photoIndex < this.photoCount - 1) this.photoIndex++;
                              else this.next();
                          },
                      }"
                      @submit="saving = true; uploading = true">
                    @csrf
                    <input type="hidden" name="asset_type" value="{{ $selectedType }}">
                    <x-site.upload-busy-overlay :message="__('borrower.profile.uploading_collateral')" />

                    <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 mb-2">
                        <template x-for="n in lastStep" :key="'c'+n">
                            <span class="inline-flex items-center gap-1.5">
                                <span class="size-6 rounded-full grid place-items-center text-[11px]"
                                      :class="step >= n ? 'bg-brand text-white' : 'bg-gray-100 text-gray-500'"
                                      x-text="n"></span>
                                <span x-show="n < lastStep" class="text-gray-300" aria-hidden="true">·</span>
                            </span>
                        </template>
                    </div>

                    {{-- Step 1: details (+ insurance fields for vehicle) --}}
                    <div x-show="step === 1" x-cloak class="space-y-4">
                        <p class="text-sm font-semibold text-gray-900">{{ __('borrower.profile.collateral_step_details') }}</p>
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-semibold text-gray-900 mb-1.5">{{ __('borrower.profile.asset_label') }} <span class="text-red-500">*</span></label>
                                <input type="text" name="label" value="{{ old('label') }}" required maxlength="150"
                                       placeholder="{{ __('borrower.profile.asset_label_placeholder') }}"
                                       class="kf-field">
                            </div>

                            @foreach ($detailFields as $field)
                                @php
                                    $fieldKey = $field['key'];
                                    $isColumn = $field['column'] ?? false;
                                    $inputName = $isColumn ? $fieldKey : 'details['.$fieldKey.']';
                                    $oldVal = $isColumn ? old($fieldKey) : old('details.'.$fieldKey);
                                    $fieldLabel = __('borrower.profile.collateral_fields.'.$fieldKey);
                                @endphp
                                <div>
                                    <label class="block text-sm font-semibold text-gray-900 mb-1.5">{{ $fieldLabel }} <span class="text-red-500">*</span></label>
                                    <input type="{{ $field['type'] === 'number' ? 'number' : 'text' }}"
                                           @if ($field['type'] === 'number') inputmode="numeric" min="0" @endif
                                           name="{{ $inputName }}" value="{{ $oldVal }}" maxlength="150" required
                                           class="kf-field">
                                </div>
                            @endforeach

                            <div class="sm:col-span-2">
                                <label class="block text-sm font-semibold text-gray-900 mb-1.5">{{ __('borrower.profile.description') }}</label>
                                <textarea name="description" rows="2" class="kf-field">{{ old('description') }}</textarea>
                                <p class="mt-1.5 text-xs text-gray-500">{{ __('borrower.profile.collateral_value_staff_hint') }}</p>
                            </div>
                        </div>

                        @if ($isVehicle)
                            <div class="rounded-xl ring-1 ring-brand/20 bg-brand-muted/30 p-4">
                                <label class="text-xs font-semibold text-brand mb-1 block">
                                    {{ __('borrower.profile.comprehensive_insurance') }} <span class="text-red-500">*</span>
                                </label>
                                <p class="text-xs text-brand/80 mb-3">{{ __('borrower.profile.comprehensive_insurance_hint') }}</p>
                                <input type="hidden" name="details[insurance_type]" value="comprehensive">
                                <div class="grid sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-900 mb-1.5">{{ __('borrower.profile.collateral_fields.insurance_policy_number') }} <span class="text-red-500">*</span></label>
                                        <input type="text" name="details[insurance_policy_number]" value="{{ old('details.insurance_policy_number') }}" maxlength="150" required
                                               class="kf-field">
                                    </div>
                                    <div>
                                        <x-site.date-input
                                            name="details[insurance_expires_at]"
                                            :label="__('borrower.profile.collateral_fields.insurance_expires_at')"
                                            :value="old('details.insurance_expires_at')"
                                            :required="true"
                                            input-class="kf-field"
                                        />
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Step 2: guided photos one-by-one --}}
                    <div x-show="step === 2" x-cloak class="space-y-4">
                        <p class="text-sm font-semibold text-gray-900">{{ __('borrower.profile.collateral_step_photos') }}</p>
                        <p class="text-xs text-gray-500">{{ __('borrower.profile.collateral_step_photos_hint') }}</p>
                        @foreach ($photoSlots as $slot)
                            <div x-show="photoIndex === {{ $slot['key'] }}" x-cloak
                                 class="rounded-2xl ring-1 ring-brand/15 bg-white p-5 space-y-3 {{ $slot['required'] ? 'ring-amber-200' : '' }}">
                                <div>
                                    <p class="text-base font-bold text-gray-900">
                                        {{ $slot['label'] }}
                                        @if ($slot['required']) <span class="text-red-500">*</span> @endif
                                    </p>
                                    <p class="text-sm text-gray-600 mt-1">{{ $slot['hint'] }}</p>
                                    <p class="text-xs text-gray-400 mt-1">{{ __('borrower.profile.collateral_photo_progress', ['current' => $slot['key'] + 1, 'total' => count($photoSlots)]) }}</p>
                                </div>
                                <x-site.single-image-document-upload :name="'photos['.$slot['key'].']'" />
                            </div>
                        @endforeach
                    </div>

                    {{-- Step 3: person + ownership --}}
                    <div x-show="step === 3" x-cloak class="space-y-4">
                        <p class="text-sm font-semibold text-gray-900">{{ __('borrower.profile.collateral_step_proof') }}</p>
                        <div class="grid sm:grid-cols-2 gap-3">
                            <div class="rounded-xl ring-1 ring-gray-200 p-4">
                                <label class="text-xs font-semibold text-gray-700 mb-3 block">
                                    {{ __('borrower.profile.person_with_asset') }} <span class="text-red-500">*</span>
                                </label>
                                <p class="text-xs text-gray-500 mb-3">{{ __('borrower.profile.person_with_asset_hint') }}</p>
                                <x-site.single-image-document-upload name="person_photo" />
                            </div>
                            <div class="rounded-xl ring-1 ring-gray-200 p-4">
                                <label class="text-xs font-semibold text-gray-700 mb-3 block">
                                    {{ __('borrower.profile.ownership_document') }} <span class="text-red-500">*</span>
                                </label>
                                <p class="text-xs text-gray-500 mb-3">{{ __('borrower.profile.ownership_document_hint') }}</p>
                                <x-site.single-image-document-upload name="ownership_document" />
                            </div>
                        </div>
                    </div>

                    {{-- Step 4: insurance certificate (vehicle only) --}}
                    @if ($isVehicle)
                        <div x-show="step === 4" x-cloak class="space-y-4">
                            <p class="text-sm font-semibold text-gray-900">{{ __('borrower.profile.collateral_step_insurance_doc') }}</p>
                            <p class="text-xs text-gray-500">{{ __('borrower.profile.comprehensive_insurance_hint') }}</p>
                            <div class="rounded-xl ring-1 ring-brand/20 bg-brand-muted/30 p-4">
                                <x-site.single-image-document-upload name="insurance_document" />
                            </div>
                        </div>
                    @endif

                    <div class="flex flex-wrap gap-3 pt-2">
                        <button type="button" x-show="step > 1 || photoIndex > 0" x-cloak @click="prev()"
                                class="inline-flex items-center px-5 py-2.5 rounded-xl text-sm font-semibold text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50">
                            {{ __('borrower.profile.back') }}
                        </button>
                        <button type="button" x-show="step === 2 && photoIndex < photoCount - 1" x-cloak @click="nextPhoto()"
                                class="inline-flex items-center bg-brand hover:bg-brand-light text-white font-semibold px-5 py-2.5 rounded-xl text-sm">
                            {{ __('borrower.profile.collateral_next_photo') }}
                        </button>
                        <button type="button" x-show="step === 2 && photoIndex >= photoCount - 1" x-cloak @click="next()"
                                class="inline-flex items-center bg-brand hover:bg-brand-light text-white font-semibold px-5 py-2.5 rounded-xl text-sm">
                            {{ __('borrower.profile.continue') }}
                        </button>
                        <button type="button" x-show="step === 1 || (step === 3 && isVehicle)" x-cloak @click="next()"
                                class="inline-flex items-center bg-brand hover:bg-brand-light text-white font-semibold px-5 py-2.5 rounded-xl text-sm">
                            {{ __('borrower.profile.continue') }}
                        </button>
                        <button type="submit" x-show="(step === 3 && !isVehicle) || step === 4" x-cloak :disabled="saving"
                                class="inline-flex items-center gap-2 bg-brand hover:bg-brand-light text-white font-semibold px-6 py-2.5 rounded-xl text-sm disabled:opacity-70">
                            <svg x-show="saving" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            <span x-text="saving ? @js(__('borrower.profile.saving')) : @js(__('borrower.profile.save_asset'))"></span>
                        </button>
                        <a href="{{ route('site.borrower.profile', ['section' => 'assets']) }}" class="inline-flex items-center px-5 py-2.5 rounded-xl text-sm font-semibold text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50">{{ __('borrower.profile.cancel') }}</a>
                    </div>
                </form>
            </x-site.profile-section-card>
        @else
            {{-- ============ Item 17: swipeable collateral cards ============ --}}
            @if ($assets->isNotEmpty())
                <div class="flex items-center justify-between mb-3">
                    <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">
                        {{ __('borrower.profile.collateral_count', ['count' => $assets->count()]) }}
                    </p>
                </div>
                <div class="-mx-1 px-1 flex gap-4 overflow-x-auto snap-x snap-mandatory pb-4 mb-6"
                     style="scrollbar-width: thin;">
                    @foreach ($assets as $asset)
                        @php
                            $thumb = $asset->thumbnailPath();
                            $gallery = $asset->galleryPaths();
                            $cardDetails = collect(\App\Models\CustomerAsset::detailFieldsFor($asset->asset_type))
                                ->map(function ($field) use ($asset) {
                                    $val = ($field['column'] ?? false) ? $asset->{$field['key']} : $asset->detail($field['key']);

                                    return filled($val) ? [
                                        'key' => $field['key'],
                                        'label' => __('borrower.profile.collateral_fields.'.$field['key']),
                                        'value' => $val,
                                    ] : null;
                                })
                                ->filter()
                                ->take(4)
                                ->values();
                        @endphp
                        <div class="snap-start shrink-0 w-[80%] sm:w-72">
                            <div class="glass-card overflow-hidden ring-1 ring-gray-200/80 h-full flex flex-col">
                                <div class="relative h-40 bg-gradient-to-br from-brand-muted/60 to-white">
                                    @if ($thumb)
                                        <img src="{{ asset('storage/'.$thumb) }}" alt="" class="absolute inset-0 h-full w-full object-cover">
                                    @else
                                        <span class="absolute inset-0 grid place-items-center text-5xl" aria-hidden="true">{{ $typeIcons[$asset->asset_type] ?? '📦' }}</span>
                                    @endif
                                    <span class="absolute top-3 left-3 inline-flex items-center gap-1 text-[11px] font-semibold bg-white/90 backdrop-blur px-2.5 py-1 rounded-full text-gray-800 ring-1 ring-black/5">
                                        {{ $typeIcons[$asset->asset_type] ?? '📦' }} {{ $assetTypes[$asset->asset_type] ?? $asset->asset_type }}
                                    </span>
                                    <span class="absolute top-3 right-3 inline-flex items-center gap-1 text-[11px] font-semibold bg-emerald-500/90 text-white px-2.5 py-1 rounded-full">
                                        {{ __('borrower.profile.collateral_status_active') }}
                                    </span>
                                    @if (count($gallery) > 1)
                                        <span class="absolute bottom-3 right-3 text-[11px] font-semibold bg-black/55 text-white px-2 py-0.5 rounded-full">
                                            {{ count($gallery) }} 📷
                                        </span>
                                    @endif
                                </div>
                                <div class="p-4 flex-1 flex flex-col">
                                    <h3 class="font-bold text-gray-900 truncate">{{ $asset->label }}</h3>
                                    @if ($asset->estimated_value)
                                        <p class="text-sm text-brand font-semibold mt-1 tabular-nums">{{ format_money($asset->estimated_value) }}</p>
                                    @else
                                        <p class="text-xs text-gray-500 mt-1">{{ __('borrower.profile.collateral_awaiting_valuation') }}</p>
                                    @endif
                                    @if ($cardDetails->isNotEmpty())
                                        <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2">
                                            @foreach ($cardDetails as $detail)
                                                <div class="min-w-0">
                                                    <dt class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold truncate">{{ $detail['label'] }}</dt>
                                                    <dd class="text-xs font-semibold text-gray-900 truncate" title="{{ $detail['value'] }}">{{ $detail['value'] }}</dd>
                                                </div>
                                            @endforeach
                                        </dl>
                                    @elseif (filled($asset->description))
                                        <p class="mt-2 text-xs text-gray-500 line-clamp-2">{{ $asset->description }}</p>
                                    @endif
                                    <button type="button" @click="openAsset = {{ $asset->id }}"
                                            class="mt-4 inline-flex items-center justify-center w-full bg-gray-900 hover:bg-black text-white font-semibold px-4 py-2.5 rounded-xl text-sm">
                                        {{ __('borrower.profile.view_manage') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Type-first collateral add (choose type → form) --}}
            <div class="glass-card p-5 sm:p-6">
                @if ($assets->isEmpty())
                    <p class="text-sm text-gray-600 mb-4">{{ __('borrower.profile.no_assets_yet') }}</p>
                @endif
                <button type="button" @click="addOpen = !addOpen"
                        class="inline-flex items-center gap-2 bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-6 py-3 rounded-xl text-sm">
                    <span class="text-lg leading-none">＋</span>
                    {{ $assets->isEmpty() ? __('borrower.profile.add_first_collateral') : __('borrower.profile.add_new_collateral') }}
                </button>

                <div x-show="addOpen" x-cloak class="mt-5">
                    <p class="text-sm font-semibold text-gray-900 mb-1">{{ __('borrower.profile.choose_asset_type') }}</p>
                    <p class="text-xs text-gray-500 mb-4">{{ __('borrower.profile.choose_asset_type_hint') }}</p>
                    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        @foreach ($assetTypes as $key => $label)
                            <a href="{{ route('site.borrower.profile', ['section' => 'assets', 'add' => 1, 'type' => $key]) }}"
                               class="group rounded-2xl ring-1 ring-gray-200/80 p-5 hover:ring-brand/40 hover:shadow-md transition bg-white text-center">
                                <span class="text-3xl block mb-3" aria-hidden="true">{{ $typeIcons[$key] ?? '📦' }}</span>
                                <h3 class="font-bold text-gray-900 group-hover:text-brand">{{ $label }}</h3>
                                <p class="mt-2 text-xs font-semibold text-brand">{{ __('borrower.profile.continue_with_type') }} →</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- ============ Per-collateral detail modal (Item 18 details + Item 19 gallery) ============ --}}
            @foreach ($assets as $asset)
                @php
                    $meta = $asset->metadata ?? [];
                    $gallery = $asset->galleryPaths();
                    $ownershipDoc = $meta['ownership_document_path'] ?? null;
                    $insuranceDoc = $meta['insurance_document_path'] ?? null;
                    $insuranceDetails = [
                        'insurance_type' => $asset->detail('insurance_type'),
                        'insurance_policy_number' => $asset->detail('insurance_policy_number'),
                        'insurance_expires_at' => $asset->detail('insurance_expires_at'),
                    ];
                @endphp
                <div x-show="openAsset === {{ $asset->id }}" x-cloak x-transition
                     class="fixed inset-0 z-[70] bg-black/60 flex items-end sm:items-center justify-center p-0 sm:p-4"
                     @keydown.escape.window="openAsset = null" @click.self="openAsset = null">
                    <div class="bg-white w-full sm:max-w-2xl sm:rounded-2xl rounded-t-2xl max-h-[92vh] overflow-y-auto">
                        <div class="sticky top-0 bg-white/95 backdrop-blur px-5 py-4 border-b border-gray-100 flex items-center justify-between z-10">
                            <div class="min-w-0">
                                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ $assetTypes[$asset->asset_type] ?? $asset->asset_type }}</p>
                                <h2 class="font-bold text-gray-900 truncate">{{ $asset->label }}</h2>
                            </div>
                            <button type="button" @click="openAsset = null" class="shrink-0 size-9 grid place-items-center rounded-full hover:bg-gray-100 text-gray-500 text-xl">×</button>
                        </div>

                        <div class="p-5 space-y-6" id="asset-edit-{{ $asset->id }}">
                            <form method="POST" action="{{ route('site.borrower.profile.assets.update', $asset) }}" class="rounded-2xl bg-brand-muted/25 ring-1 ring-brand/10 p-4 space-y-4">
                                @csrf
                                @method('PUT')
                                <p class="text-xs uppercase tracking-widest text-brand font-semibold">{{ __('borrower.profile.collateral_details') }}</p>
                                <div class="grid sm:grid-cols-2 gap-3">
                                    <div class="sm:col-span-2">
                                        <label class="block text-[11px] font-medium text-gray-600 mb-1">{{ __('borrower.profile.asset_label') }} <span class="text-red-500">*</span></label>
                                        <input type="text" name="label" value="{{ old('label', $asset->label) }}" required maxlength="150"
                                               class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                                        <p class="mt-1.5 text-[11px] text-gray-500">{{ __('borrower.profile.collateral_value_staff_hint') }}</p>
                                    </div>
                                    @foreach (\App\Models\CustomerAsset::detailFieldsFor($asset->asset_type) as $field)
                                        @php
                                            $val = ($field['column'] ?? false) ? $asset->{$field['key']} : $asset->detail($field['key']);
                                            $inputName = ($field['column'] ?? false) ? $field['key'] : 'details['.$field['key'].']';
                                            $oldKey = ($field['column'] ?? false) ? $field['key'] : 'details.'.$field['key'];
                                        @endphp
                                        <div>
                                            <label class="block text-[11px] font-medium text-gray-600 mb-1">{{ __('borrower.profile.collateral_fields.'.$field['key']) }} <span class="text-red-500">*</span></label>
                                            <input type="{{ ($field['type'] ?? '') === 'number' ? 'number' : 'text' }}"
                                                   name="{{ $inputName }}" value="{{ old($oldKey, $val) }}" required maxlength="150"
                                                   class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                                        </div>
                                    @endforeach
                                    @if ($asset->asset_type === 'vehicle')
                                        <div>
                                            <label class="block text-[11px] font-medium text-gray-600 mb-1">{{ __('borrower.profile.collateral_fields.insurance_policy_number') }} <span class="text-red-500">*</span></label>
                                            <input type="text" name="details[insurance_policy_number]" value="{{ old('details.insurance_policy_number', $asset->detail('insurance_policy_number')) }}" required maxlength="150"
                                                   class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                                        </div>
                                        <div>
                                            <x-site.date-input
                                                name="details[insurance_expires_at]"
                                                :label="__('borrower.profile.collateral_fields.insurance_expires_at')"
                                                :value="old('details.insurance_expires_at', $asset->detail('insurance_expires_at'))"
                                                :required="true"
                                                input-class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-base"
                                            />
                                        </div>
                                    @endif
                                    <div class="sm:col-span-2">
                                        <label class="block text-[11px] font-medium text-gray-600 mb-1">{{ __('borrower.profile.description') }}</label>
                                        <textarea name="description" rows="2" class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm">{{ old('description', $asset->description) }}</textarea>
                                    </div>
                                </div>
                                <button type="submit" class="inline-flex bg-brand hover:bg-brand-light text-white font-semibold px-5 py-2.5 rounded-xl text-sm">
                                    {{ __('borrower.profile.save') }}
                                </button>
                            </form>

                            {{-- Item 19: swipe gallery with replace-on-slot + delete --}}
                            <div>
                                <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold mb-1">{{ __('borrower.profile.collateral_gallery') }}</p>
                                <p class="text-[11px] text-gray-400 mb-3">{{ __('borrower.profile.swipe_to_browse') }}</p>
                                @if (count($gallery))
                                    @php
                                        $gallerySlides = collect($asset->photo_paths ?? [])
                                            ->values()
                                            ->map(fn ($path, $i) => [
                                                'url' => asset('storage/'.$path),
                                                'index' => $i,
                                                'replaceable' => true,
                                            ])
                                            ->all();
                                        if ($meta['person_with_asset_path'] ?? null) {
                                            $gallerySlides[] = [
                                                'url' => asset('storage/'.$meta['person_with_asset_path']),
                                                'index' => null,
                                                'replaceable' => false,
                                                'label' => __('borrower.profile.person_with_asset'),
                                            ];
                                        }
                                    @endphp
                                    <div x-data="{
                                            gIndex: 0,
                                            slides: @js($gallerySlides),
                                            touchStartX: 0,
                                            prev() { this.gIndex = (this.gIndex - 1 + this.slides.length) % this.slides.length },
                                            next() { this.gIndex = (this.gIndex + 1) % this.slides.length },
                                            onTouchStart(e) { this.touchStartX = e.changedTouches[0].screenX },
                                            onTouchEnd(e) {
                                                const diff = e.changedTouches[0].screenX - this.touchStartX;
                                                if (Math.abs(diff) > 50) diff > 0 ? this.prev() : this.next();
                                            }
                                         }" class="space-y-3">
                                        <div class="relative aspect-square sm:aspect-[4/3] rounded-2xl overflow-hidden ring-1 ring-gray-200 bg-gray-100"
                                             @touchstart="onTouchStart($event)" @touchend="onTouchEnd($event)">
                                            <template x-for="(slide, i) in slides" :key="'g-'+i">
                                                <div class="absolute inset-0 transition-opacity duration-300"
                                                     :class="i === gIndex ? 'opacity-100 z-10' : 'opacity-0 z-0 pointer-events-none'">
                                                    <button type="button" @click="lightbox = slide.url"
                                                            class="absolute inset-0 block w-full h-full cursor-zoom-in">
                                                        <img :src="slide.url" alt="" class="h-full w-full object-cover">
                                                    </button>
                                                    <p x-show="slide.label" x-cloak class="absolute bottom-3 left-3 z-20 text-[11px] font-semibold bg-black/55 text-white px-2 py-0.5 rounded-full" x-text="slide.label"></p>
                                                </div>
                                            </template>

                                            <template x-if="slides.length > 1">
                                                <div>
                                                    <button type="button" @click="prev()"
                                                            class="absolute left-2 top-1/2 -translate-y-1/2 z-20 size-9 rounded-full bg-white/90 shadow grid place-items-center text-gray-800"
                                                            aria-label="{{ __('borrower.profile.prev_photo') }}">‹</button>
                                                    <button type="button" @click="next()"
                                                            class="absolute right-2 top-1/2 -translate-y-1/2 z-20 size-9 rounded-full bg-white/90 shadow grid place-items-center text-gray-800"
                                                            aria-label="{{ __('borrower.profile.next_photo') }}">›</button>
                                                    <div class="absolute top-3 right-3 z-20 rounded-full bg-black/45 text-white text-xs px-2.5 py-1"
                                                         x-text="(gIndex + 1) + ' / ' + slides.length"></div>
                                                </div>
                                            </template>

                                            <div class="absolute top-3 left-3 z-20 flex gap-1.5" x-show="slides[gIndex]?.replaceable">
                                                <template x-for="(slide, i) in slides" :key="'act-'+i">
                                                    <div x-show="i === gIndex && slide.replaceable" x-cloak class="flex gap-1.5">
                                                        <form method="POST" action="{{ route('site.borrower.profile.assets.photos.replace', $asset) }}" enctype="multipart/form-data">
                                                            @csrf
                                                            <input type="hidden" name="index" :value="slide.index">
                                                            <label class="size-8 grid place-items-center rounded-full bg-black/55 hover:bg-brand text-white text-sm cursor-pointer" title="{{ __('borrower.profile.replace_photo') }}">
                                                                ↻
                                                                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp,image/jpg,.jpg,.jpeg,.png,.webp" class="sr-only"
                                                                       onchange="this.form.submit()">
                                                            </label>
                                                        </form>
                                                        <form method="POST" action="{{ route('site.borrower.profile.assets.photos.delete', $asset) }}"
                                                              @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.profile.delete_photo_confirm')), message: '', confirmLabel: @js(__('borrower.profile.delete_photo')), confirmClass: 'bg-red-600 hover:bg-red-700 text-white' })">
                                                            @csrf @method('DELETE')
                                                            <input type="hidden" name="index" :value="slide.index">
                                                            <button type="submit" class="size-8 grid place-items-center rounded-full bg-black/55 hover:bg-red-600 text-white text-sm" aria-label="{{ __('borrower.profile.delete_photo') }}">🗑</button>
                                                        </form>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>

                                        <div x-show="slides.length > 1" class="flex gap-2 overflow-x-auto pb-1">
                                            <template x-for="(slide, i) in slides" :key="'gt-'+i">
                                                <button type="button" @click="gIndex = i"
                                                        class="shrink-0 size-14 rounded-lg overflow-hidden ring-2 transition"
                                                        :class="gIndex === i ? 'ring-brand' : 'ring-transparent opacity-70 hover:opacity-100'">
                                                    <img :src="slide.url" alt="" class="w-full h-full object-cover">
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                @endif

                                {{-- Add more photos (up to 6) --}}
                                @if (count($asset->photo_paths ?? []) < 6)
                                    <form method="POST" action="{{ route('site.borrower.profile.assets.photos.add', $asset) }}" enctype="multipart/form-data"
                                          class="mt-3" x-data="{ busy: false }" @submit="busy = true">
                                        @csrf
                                        <label class="inline-flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-900 font-semibold px-4 py-2.5 rounded-xl text-sm cursor-pointer">
                                            <span class="text-lg leading-none">＋</span> {{ __('borrower.profile.add_photos') }}
                                            <input type="file" name="photos[]" accept="image/*" multiple class="sr-only"
                                                   @change="if ($el.files.length) { busy = true; $el.form.submit(); }">
                                        </label>
                                        <span x-show="busy" x-cloak class="ml-2 text-xs text-gray-500">{{ __('borrower.profile.saving') }}</span>
                                    </form>
                                @endif
                            </div>

                            {{-- Ownership + insurance documents --}}
                            <div class="space-y-4">
                                <div class="rounded-2xl ring-1 ring-gray-200 p-4">
                                    <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold mb-2">{{ __('borrower.profile.ownership_document') }}</p>
                                    @if ($ownershipDoc)
                                        <div class="flex flex-wrap items-center gap-3">
                                            @if (str_ends_with(strtolower($ownershipDoc), '.pdf'))
                                                <x-site.document-view-button
                                                    :url="asset('storage/'.$ownershipDoc)"
                                                    type="pdf"
                                                    class="inline-flex items-center gap-2 text-sm text-brand font-semibold"
                                                >📄 {{ __('borrower.profile.view_document') }}</x-site.document-view-button>
                                            @else
                                                <button type="button" @click="lightbox = @js(asset('storage/'.$ownershipDoc))"
                                                        class="block h-28 w-28 rounded-xl overflow-hidden ring-1 ring-gray-200 cursor-zoom-in">
                                                    <img src="{{ asset('storage/'.$ownershipDoc) }}" alt="" class="h-full w-full object-cover">
                                                </button>
                                            @endif
                                        </div>
                                    @else
                                        <p class="text-xs text-gray-500 mb-2">{{ __('borrower.profile.no_document_yet') }}</p>
                                    @endif
                                    <form method="POST" action="{{ route('site.borrower.profile.assets.documents.replace', $asset) }}" enctype="multipart/form-data" class="mt-3">
                                        @csrf
                                        <input type="hidden" name="document" value="ownership_document">
                                        <label class="inline-flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-900 font-semibold px-4 py-2.5 rounded-xl text-sm cursor-pointer">
                                            {{ $ownershipDoc ? __('borrower.profile.replace_document') : __('borrower.profile.upload_document') }}
                                            <input type="file" name="file" accept="image/jpeg,image/png,image/webp,application/pdf,.jpg,.jpeg,.png,.webp,.pdf" class="sr-only" onchange="this.form.submit()">
                                        </label>
                                    </form>
                                </div>

                                @if ($asset->asset_type === 'vehicle')
                                    <div class="rounded-2xl ring-1 ring-brand/20 bg-brand-muted/20 p-4">
                                        <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-1">{{ __('borrower.profile.comprehensive_insurance') }}</p>
                                        <p class="text-[11px] text-brand/80 mb-3">{{ __('borrower.profile.comprehensive_insurance_hint') }}</p>
                                        @if ($insuranceDoc)
                                            <div class="flex flex-wrap items-center gap-3 mb-3">
                                                @if (str_ends_with(strtolower($insuranceDoc), '.pdf'))
                                                    <x-site.document-view-button
                                                        :url="asset('storage/'.$insuranceDoc)"
                                                        type="pdf"
                                                        class="inline-flex items-center gap-2 text-sm text-brand font-semibold"
                                                    >📄 {{ __('borrower.profile.view_document') }}</x-site.document-view-button>
                                                @else
                                                    <button type="button" @click="lightbox = @js(asset('storage/'.$insuranceDoc))"
                                                            class="block h-28 w-28 rounded-xl overflow-hidden ring-1 ring-gray-200 cursor-zoom-in">
                                                        <img src="{{ asset('storage/'.$insuranceDoc) }}" alt="" class="h-full w-full object-cover">
                                                    </button>
                                                @endif
                                            </div>
                                        @else
                                            <p class="text-xs text-amber-700 mb-2">{{ __('borrower.profile.no_document_yet') }}</p>
                                        @endif
                                        <form method="POST" action="{{ route('site.borrower.profile.assets.documents.replace', $asset) }}" enctype="multipart/form-data">
                                            @csrf
                                            <input type="hidden" name="document" value="insurance_document">
                                            <label class="inline-flex items-center gap-2 bg-brand hover:bg-brand-light text-white font-semibold px-4 py-2.5 rounded-xl text-sm cursor-pointer">
                                                {{ $insuranceDoc ? __('borrower.profile.replace_document') : __('borrower.profile.upload_document') }}
                                                <input type="file" name="file" accept="image/jpeg,image/png,image/webp,application/pdf,.jpg,.jpeg,.png,.webp,.pdf" class="sr-only" onchange="this.form.submit()">
                                            </label>
                                        </form>
                                    </div>
                                @endif
                            </div>

                            {{-- Remove collateral --}}
                            <div class="pt-4 border-t border-gray-100">
                                <form method="POST" action="{{ route('site.borrower.profile.assets.destroy', $asset) }}"
                                      @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.profile.remove_asset_confirm')), message: '', confirmLabel: @js(__('borrower.profile.remove_asset')), confirmClass: 'bg-red-600 hover:bg-red-700 text-white' })">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-sm font-semibold text-red-600 hover:underline">{{ __('borrower.profile.remove_asset') }}</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach

            {{-- Shared lightbox --}}
            <div x-show="lightbox" x-cloak x-transition
                 class="fixed inset-0 z-[90] bg-black/80 flex items-center justify-center p-4"
                 @keydown.escape.window="lightbox = null" @click.self="lightbox = null">
                <button type="button" class="absolute top-4 right-4 text-white/90 text-2xl font-semibold" @click="lightbox = null">×</button>
                <img :src="lightbox" alt="" class="max-h-[90vh] max-w-[95vw] object-contain rounded-xl shadow-2xl">
            </div>
        @endif
    </div>
</x-site.borrower-layout>
