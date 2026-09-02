<x-site.borrower-layout :title="brand_title(__('borrower.profile.my_collaterals'))" active="profile" content-width="wide">

    @php
        $adding = request()->boolean('add') || filled(old('asset_type'));
        $selectedType = old('asset_type', request('type'));
        $typeIcons = \App\Models\CustomerAsset::typeIcons();
        $detailFields = $selectedType ? \App\Models\CustomerAsset::detailFieldsFor($selectedType) : [];
        $uwPrompt = request()->boolean('uw');
        $assetService = app(\App\Services\CustomerAssetService::class);
        $currentApp = $uwApplication ?? null;
        $currentAppId = $currentApp?->id;
        $assetAvailabilities = ($assets ?? collect())->mapWithKeys(
            fn ($asset) => [$asset->id => $assetService->availabilityForApplication($asset, $currentApp)]
        );
        $availableCount = $assetAvailabilities->where('selectable', true)->count();
        $pledgedCount = $assetAvailabilities->where('code', 'pledged_other')->count();
        $onThisCount = $assetAvailabilities->where('code', 'on_this_loan')->count();
    @endphp

    <div x-data="{ openAsset: {{ (int) (request('view') ?: request('edit') ?: 0) ?: 'null' }}, editingAsset: {{ request()->filled('edit') ? (int) request('edit') : 'null' }}, lightbox: null }"
         x-init="if (openAsset) { $nextTick(() => { const el = document.getElementById('asset-edit-' + openAsset); if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }); }">
        @include('site.borrower.profile._profile_shell', [
            'title' => __('borrower.profile.my_collaterals'),
            'subtitle' => __('borrower.profile.my_assets_hint'),
            'customer' => $customer,
            'active' => 'assets',
        ])

        @if ($uwPrompt && ! $adding)
            <div class="mb-4 rounded-2xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3">
                <p class="text-sm font-semibold text-amber-950">{{ __('borrower.profile.collateral_uw_title') }}</p>
                <p class="text-sm text-amber-900/80 mt-1">
                    @if (($assets ?? collect())->isEmpty())
                        {{ __('borrower.profile.collateral_uw_none_body') }}
                    @elseif ($availableCount > 0)
                        {{ __('borrower.profile.collateral_uw_choose_body') }}
                    @elseif ($onThisCount > 0 && $pledgedCount === 0)
                        {{ __('borrower.profile.collateral_uw_already_on_loan_body') }}
                    @else
                        {{ __('borrower.profile.collateral_uw_blocked_body') }}
                    @endif
                </p>
            </div>
        @endif

        @if (($assets ?? collect())->isNotEmpty() && $uwPrompt && ! $adding)
            <div class="mb-4 space-y-2">
                @foreach ($assets as $asset)
                    @php $availability = $assetAvailabilities[$asset->id] ?? ['code' => 'available', 'selectable' => false]; @endphp
                    <div @class([
                        'rounded-xl ring-1 px-4 py-3',
                        'bg-amber-50 ring-amber-200' => ($availability['code'] ?? '') === 'pledged_other',
                        'bg-red-50 ring-red-200' => ($availability['code'] ?? '') === 'declined',
                        'bg-white ring-gray-200' => ! in_array($availability['code'] ?? '', ['pledged_other', 'declined'], true),
                    ])>
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900">{{ $asset->label }}</p>
                                <p class="text-xs text-gray-500">{{ __('borrower.profile.asset_types.'.$asset->asset_type) }}</p>
                                @include('site.borrower.profile._asset_availability', ['availability' => $availability, 'showHint' => true])
                            </div>
                            <div class="flex flex-wrap items-center gap-2 shrink-0">
                                @if ($availability['selectable'] ?? false)
                                    <form method="POST" action="{{ route('site.borrower.profile.assets.use', $asset) }}">
                                        @csrf
                                        <input type="hidden" name="application_id" value="{{ $currentAppId }}">
                                        <button type="submit"
                                                class="inline-flex items-center justify-center rounded-xl bg-brand-gold px-4 py-2 text-xs font-bold text-brand shadow-sm hover:brightness-95">
                                            {{ __('borrower.profile.collateral_use_this') }}
                                        </button>
                                    </form>
                                @endif
                                <button type="button" @click="openAsset = {{ $asset->id }}; editingAsset = null"
                                        class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2 text-xs font-bold text-brand ring-1 ring-brand/20 hover:bg-brand-muted/40">
                                    {{ __('borrower.profile.view_asset') }}
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($adding && $selectedType)
            {{-- ============ Item 18: type-specific add form ============ --}}
            <x-site.profile-section-card :title="($typeIcons[$selectedType] ?? '📦').'  '.__('borrower.profile.add_asset').': '.__('borrower.profile.asset_types.'.$selectedType)" :allow-overflow="true">
                @php
                    $photoSlots = [
                        ['key' => 0, 'label' => __('borrower.profile.asset_photo_front'), 'required' => true, 'hint' => __('borrower.profile.asset_photo_front_hint')],
                        ['key' => 1, 'label' => __('borrower.profile.asset_photo_back'), 'required' => true, 'hint' => __('borrower.profile.asset_photo_back_hint')],
                    ];
                    $isVehicle = $selectedType === 'vehicle';
                    if ($isVehicle) {
                        $photoSlots[] = ['key' => 2, 'label' => __('borrower.profile.asset_photo_left'), 'required' => true, 'hint' => __('borrower.profile.asset_photo_left_hint')];
                        $photoSlots[] = ['key' => 3, 'label' => __('borrower.profile.asset_photo_right'), 'required' => true, 'hint' => __('borrower.profile.asset_photo_right_hint')];
                    }
                @endphp
                <form method="POST" action="{{ route('site.borrower.profile.assets.store') }}" enctype="multipart/form-data" class="space-y-6" novalidate
                      data-saving-message="{{ __('borrower.profile.uploading_collateral') }}"
                      x-data="collateralAddForm({ isVehicle: @js($isVehicle), photoCount: {{ count($photoSlots) }} })"
                      x-on:input="refreshGates()"
                      x-on:change="refreshGates()"
                      x-on:guided-photos-ready="refreshGates(); next()"
                      x-on:submit="saving = true; uploading = true">
                    @csrf
                    <input type="hidden" name="asset_type" value="{{ $selectedType }}">
                    @if ($currentAppId)
                        <input type="hidden" name="application" value="{{ $currentAppId }}">
                    @endif

                    @php
                        $typePickerUrl = route('site.borrower.profile', array_filter([
                            'section' => 'assets',
                            'add' => 1,
                            'uw' => $uwPrompt ? 1 : null,
                            'application' => $currentAppId,
                        ]));
                    @endphp
                    <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 mb-2">
                        <a href="{{ $typePickerUrl }}"
                           class="size-6 rounded-full grid place-items-center text-[11px] bg-brand text-white"
                           title="{{ __('borrower.profile.choose_asset_type') }}">1</a>
                        <span class="text-gray-300" aria-hidden="true">·</span>
                        <template x-for="n in lastStep" :key="'c'+n">
                            <span class="inline-flex items-center gap-1.5">
                                <span class="size-6 rounded-full grid place-items-center text-[11px]"
                                      :class="step >= n ? 'bg-brand text-white' : 'bg-gray-100 text-gray-500'"
                                      x-text="n + 1"></span>
                                <span x-show="n < lastStep" class="text-gray-300" aria-hidden="true">·</span>
                            </span>
                        </template>
                    </div>

                    {{-- Step 1: details --}}
                    <div x-show="step === 1" x-cloak class="space-y-4" data-collateral-step="details">
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
                                    $useThousands = ($field['format'] ?? null) === 'thousands';
                                    $isYearField = in_array($fieldKey, ['year', 'purchase_year'], true);
                                    $vehicleMaxAge = (int) (\App\Models\Setting::get('asset_lending.vehicle_max_age_years')
                                        ?? config('asset_lending.vehicle_max_age_years', 10));
                                    $yearMax = (int) now()->year;
                                    $yearMin = $yearMax - max(1, $vehicleMaxAge);
                                @endphp
                                <div>
                                    <label class="block text-sm font-semibold text-gray-900 mb-1.5">{{ $fieldLabel }} <span class="text-red-500">*</span></label>
                                    @if ($isYearField)
                                        @php
                                            $yearOptions = [];
                                            for ($y = $yearMax; $y >= $yearMin; $y--) {
                                                $yearOptions[(string) $y] = (string) $y;
                                            }
                                        @endphp
                                        <x-site.sheet-select
                                            :name="$inputName"
                                            :options="$yearOptions"
                                            :value="$oldVal"
                                            :required="true"
                                            :placeholder="__('borrower.profile.select_year')"
                                            select-class="kf-field"
                                        />
                                    @else
                                        <input type="text"
                                               inputmode="{{ $field['type'] === 'number' ? 'numeric' : 'text' }}"
                                               name="{{ $inputName }}" value="{{ $oldVal }}" maxlength="150" required
                                               class="kf-field"
                                               placeholder="{{ __('borrower.profile.collateral_placeholders.'.$fieldKey) }}"
                                               @if ($useThousands) x-on:input="formatThousands($event.target)" @endif>
                                    @endif
                                </div>
                            @endforeach

                            <div class="sm:col-span-2">
                                <label class="block text-sm font-semibold text-gray-900 mb-1.5">{{ __('borrower.profile.description') }}</label>
                                <textarea name="description" rows="2" class="kf-field" placeholder="{{ __('borrower.profile.collateral_placeholders.description') }}">{{ old('description') }}</textarea>
                                <p class="mt-1.5 text-xs text-gray-500">{{ __('borrower.profile.collateral_value_staff_hint') }}</p>
                            </div>
                        </div>
                    </div>

                    @if ($isVehicle)
                        <div x-show="step === 2" x-cloak class="space-y-4" data-collateral-step="insurance">
                            <p class="text-sm font-semibold text-gray-900">{{ __('borrower.profile.collateral_step_insurance') }}</p>
                            <div class="rounded-xl ring-1 ring-brand/20 bg-brand-muted/30 p-4">
                                <label class="text-xs font-semibold text-brand mb-1 block">
                                    {{ __('borrower.profile.vehicle_insurance') }} <span class="text-red-500">*</span>
                                </label>
                                <p class="text-xs text-brand/80 mb-3">{{ __('borrower.profile.vehicle_insurance_hint') }}</p>
                                <div class="mb-3">
                                    <x-site.sheet-select
                                        name="details[insurance_type]"
                                        :label="__('borrower.profile.collateral_fields.insurance_type')"
                                        :options="[
                                            'comprehensive' => __('borrower.profile.insurance_comprehensive'),
                                            'third_party' => __('borrower.profile.insurance_third_party'),
                                        ]"
                                        :value="old('details.insurance_type')"
                                        :required="true"
                                        :placeholder="__('borrower.profile.insurance_type_placeholder')"
                                        select-class="kf-field"
                                    />
                                </div>
                                <div class="grid sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-900 mb-1.5">{{ __('borrower.profile.collateral_fields.insurance_policy_number') }} <span class="text-red-500">*</span></label>
                                        <input type="text" name="details[insurance_policy_number]" value="{{ old('details.insurance_policy_number') }}" maxlength="150" required
                                               class="kf-field"
                                               placeholder="{{ __('borrower.profile.collateral_placeholders.insurance_policy_number') }}">
                                    </div>
                                    <div>
                                        <x-site.date-input
                                            name="details[insurance_expires_at]"
                                            :label="__('borrower.profile.collateral_fields.insurance_expires_at')"
                                            :value="old('details.insurance_expires_at')"
                                            :required="true"
                                            :min="now()->addDay()->format('Y-m-d')"
                                            :max="now()->addYears(5)->format('Y-m-d')"
                                            :default="now()->addYear()->format('Y-m-d')"
                                            input-class="kf-field"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- One camera session for every required asset angle --}}
                    @php
                        $angleKeys = \App\Models\CustomerAsset::bodyPhotoAngleKeys($selectedType);
                        $borrowerPhotoSteps = collect($photoSlots)->map(function ($slot) use ($angleKeys) {
                            $angle = $angleKeys[$slot['key']] ?? (string) $slot['key'];

                            return [
                                'asset_id' => 0,
                                'asset_label' => '',
                                'angle' => $angle,
                                'label' => $slot['label'],
                                'path' => null,
                                'path_url' => null,
                                'guidance' => $slot['hint'],
                                'required' => true,
                                'inputName' => 'photos['.$angle.']',
                            ];
                        })->values()->all();
                    @endphp
                    <div x-show="step === photoStep" x-cloak class="space-y-4" data-collateral-step="photos">
                        <div x-data="valuationCamera(@js([
                            'formMode' => true,
                            'dbName' => 'kf-collateral-add',
                            'facingMode' => 'environment',
                            'subjectName' => $customer->full_name ?? '',
                            'subjectLine' => $customer->full_name ?? '',
                            'cameraInsecure' => __('borrower.profile.camera_insecure'),
                            'cameraDenied' => __('borrower.profile.camera_denied'),
                            'steps' => $borrowerPhotoSteps,
                         ]))" class="space-y-4">
                        <p class="text-sm font-semibold text-gray-900">{{ __('borrower.profile.collateral_step_photos') }}</p>
                        <p class="text-xs text-gray-500">{{ __('borrower.profile.collateral_step_photos_hint') }}</p>
                        @foreach ($borrowerPhotoSteps as $photoStep)
                            <input type="file" name="{{ $photoStep['inputName'] }}" accept="image/jpeg,image/png,image/webp,image/jpg"
                                   class="sr-only" data-guided-input="{{ $photoStep['inputName'] }}" data-photo-slot="{{ $loop->index }}">
                        @endforeach
                        <div class="rounded-2xl ring-1 ring-brand/15 bg-white p-4 sm:p-5 space-y-3">
                            <p class="text-lg font-extrabold text-gray-900"
                               x-text="@js(__('site.partner_portal.valuation_photos_done', ['done' => '__D__', 'total' => '__T__'])).replace('__D__', String(requiredDone())).replace('__T__', String(requiredTotal()))">
                                {{ __('site.partner_portal.valuation_photos_done', ['done' => 0, 'total' => count($borrowerPhotoSteps)]) }}
                            </p>
                            <button type="button" x-show="pendingRequired().length" @click="start()"
                                    class="w-full rounded-xl bg-brand text-white text-sm font-extrabold py-3">{{ __('site.partner_portal.valuation_start_photos') }}</button>
                        </div>
                        <div x-show="review || requiredDone() >= requiredTotal()" x-cloak class="rounded-2xl ring-1 ring-brand/15 bg-white p-4 space-y-4">
                            <div class="grid grid-cols-2 gap-2">
                                <template x-for="s in requiredSteps()" :key="key(s)">
                                    <button type="button" @click="preview = thumbFor(s); $nextTick(() => { if (! thumbFor(s)) retake(s) })"
                                            class="rounded-xl ring-1 ring-gray-200 p-2 text-left">
                                        <div class="aspect-square rounded-lg overflow-hidden bg-gray-50 mb-1.5">
                                            <img x-show="thumbFor(s)" :src="thumbFor(s)" alt="" class="h-full w-full object-cover">
                                            <div x-show="!thumbFor(s)" class="h-full grid place-items-center text-xs text-gray-400">○</div>
                                        </div>
                                        <p class="text-xs font-bold truncate" x-text="(thumbFor(s) ? '✓ ' : '') + s.label"></p>
                                    </button>
                                </template>
                            </div>
                            <button type="button" x-show="pendingRequired().length === 0 && Object.keys(captures).length"
                                    @click="uploadAll()"
                                    class="w-full rounded-xl bg-brand text-white text-sm font-extrabold py-3">
                                {{ __('borrower.profile.continue') }}
                            </button>
                            <button type="button" @click="start()" class="w-full text-sm font-bold text-brand py-2">{{ __('site.partner_portal.valuation_retake') }}</button>
                        </div>
                        <x-site.guided-camera-overlay />
                        <template x-teleport="body">
                            <div x-show="preview" x-cloak class="fixed inset-0 z-[90] bg-black/80 flex items-center justify-center p-4" @click="preview = null">
                                <img :src="preview" alt="" class="max-h-[80vh] max-w-full rounded-xl object-contain">
                            </div>
                        </template>
                    </div>
                    </div>

                    {{-- Person + ownership --}}
                    <div x-show="step === proofStep" x-cloak class="space-y-4" data-collateral-step="proof">
                        <p class="text-sm font-semibold text-gray-900">{{ __('borrower.profile.collateral_step_proof') }}</p>
                        <div class="grid sm:grid-cols-2 gap-3">
                            <div class="rounded-xl ring-1 ring-gray-200 p-4">
                                <label class="text-xs font-semibold text-gray-700 mb-3 block">
                                    {{ __('borrower.profile.person_with_asset') }} <span class="text-red-500">*</span>
                                </label>
                                <p class="text-xs text-gray-500 mb-3">{{ __('borrower.profile.person_with_asset_hint') }}</p>
                                <x-site.single-image-document-upload name="person_photo" :required="true" facing="user" />
                            </div>
                            <div class="rounded-xl ring-1 ring-gray-200 p-4">
                                <label class="text-xs font-semibold text-gray-700 mb-3 block">
                                    {{ __('borrower.profile.ownership_document') }} <span class="text-red-500">*</span>
                                </label>
                                <p class="text-xs text-gray-500 mb-3">{{ __('borrower.profile.ownership_document_hint') }}</p>
                                <x-site.single-image-document-upload name="ownership_document" :required="true" facing="environment" />
                            </div>
                        </div>
                    </div>

                    {{-- Insurance certificate (vehicle only) --}}
                    @if ($isVehicle)
                        <div x-show="step === 5" x-cloak class="space-y-4" data-collateral-step="cert">
                            <p class="text-sm font-semibold text-gray-900">{{ __('borrower.profile.collateral_step_insurance_doc') }}</p>
                            <p class="text-xs text-gray-500">{{ __('borrower.profile.comprehensive_insurance_hint') }}</p>
                            <div class="rounded-xl ring-1 ring-brand/20 bg-brand-muted/30 p-4">
                                <x-site.single-image-document-upload name="insurance_document" :required="true" facing="environment" />
                            </div>
                        </div>
                    @endif

                    <div class="flex flex-wrap gap-3 pt-2">
                        <a href="{{ $typePickerUrl }}"
                           x-show="step === 1 && photoIndex === 0" x-cloak
                           class="inline-flex items-center px-5 py-2.5 rounded-xl text-sm font-semibold text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50">
                            {{ __('borrower.profile.back') }}
                        </a>
                        <button type="button" x-show="step > 1" x-cloak @click="prev()"
                                class="inline-flex items-center px-5 py-2.5 rounded-xl text-sm font-semibold text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50">
                            {{ __('borrower.profile.back') }}
                        </button>
                        <button type="button"
                                x-show="step === photoStep && allPhotosReady" x-cloak
                                @click="next()"
                                class="inline-flex items-center bg-brand hover:bg-brand-light text-white font-semibold px-5 py-2.5 rounded-xl text-sm">
                            {{ __('borrower.profile.continue') }}
                        </button>
                        <button type="button"
                                x-show="step === 1 && step1Ready" x-cloak
                                @click="next()"
                                class="inline-flex items-center bg-brand hover:bg-brand-light text-white font-semibold px-5 py-2.5 rounded-xl text-sm">
                            {{ __('borrower.profile.continue') }}
                        </button>
                        <button type="button"
                                x-show="isVehicle && step === 2 && step2Ready" x-cloak
                                @click="next()"
                                class="inline-flex items-center bg-brand hover:bg-brand-light text-white font-semibold px-5 py-2.5 rounded-xl text-sm">
                            {{ __('borrower.profile.continue') }}
                        </button>
                        <button type="button"
                                x-show="isVehicle && step === proofStep && step3Ready" x-cloak
                                @click="next()"
                                class="inline-flex items-center bg-brand hover:bg-brand-light text-white font-semibold px-5 py-2.5 rounded-xl text-sm">
                            {{ __('borrower.profile.continue') }}
                        </button>
                        <button type="submit"
                                x-show="((step === proofStep && !isVehicle && step3Ready) || (step === 5 && step4Ready))" x-cloak
                                :disabled="saving"
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
        @elseif ($adding)
            <x-site.profile-section-card :title="__('borrower.profile.add_asset')" :allow-overflow="true">
                <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 mb-4">
                    <span class="size-6 rounded-full grid place-items-center text-[11px] bg-brand text-white">1</span>
                    <span class="text-gray-300" aria-hidden="true">·</span>
                    <span class="size-6 rounded-full grid place-items-center text-[11px] bg-gray-100 text-gray-500">2</span>
                    <span class="text-gray-300" aria-hidden="true">·</span>
                    <span class="size-6 rounded-full grid place-items-center text-[11px] bg-gray-100 text-gray-500">3</span>
                    <span class="text-gray-300" aria-hidden="true">·</span>
                    <span class="size-6 rounded-full grid place-items-center text-[11px] bg-gray-100 text-gray-500">4</span>
                </div>
                <p class="text-sm font-semibold text-gray-900 mb-1">{{ __('borrower.profile.choose_asset_type') }}</p>
                <p class="text-xs text-gray-500 mb-4">{{ __('borrower.profile.choose_asset_type_hint') }}</p>
                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    @foreach ($assetTypes as $key => $label)
                        <a href="{{ route('site.borrower.profile', array_filter([
                                'section' => 'assets',
                                'add' => 1,
                                'type' => $key,
                                'uw' => $uwPrompt ? 1 : null,
                                'application' => $currentAppId,
                            ])) }}"
                           class="group rounded-2xl ring-1 ring-gray-200/80 p-5 hover:ring-brand/40 hover:shadow-md transition bg-white text-center">
                            <span class="text-3xl block mb-3" aria-hidden="true">{{ $typeIcons[$key] ?? '📦' }}</span>
                            <h3 class="font-bold text-gray-900 group-hover:text-brand">{{ __('borrower.profile.asset_types.'.$key) }}</h3>
                            <p class="mt-2 text-xs font-semibold text-brand">{{ __('borrower.profile.continue_with_type') }} →</p>
                        </a>
                    @endforeach
                </div>
                <div class="mt-5">
                    <a href="{{ route('site.borrower.profile', array_filter([
                            'section' => 'assets',
                            'uw' => $uwPrompt ? 1 : null,
                            'application' => $currentAppId,
                        ])) }}"
                       class="inline-flex items-center px-5 py-2.5 rounded-xl text-sm font-semibold text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50">
                        {{ __('borrower.profile.cancel') }}
                    </a>
                </div>
            </x-site.profile-section-card>
        @else
            {{-- Saved collateral cards --}}
            @if ($assets->isNotEmpty())
                <div class="flex items-center justify-between mb-3">
                    <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">
                        {{ __('borrower.profile.collateral_count', ['count' => $assets->count()]) }}
                    </p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                    @foreach ($assets as $asset)
                        @php
                            $availability = $assetAvailabilities[$asset->id] ?? ['code' => 'available'];
                            $card = app(\App\Services\CollateralCardService::class)->forAsset(
                                $asset,
                                $currentApp,
                                \App\Services\CollateralCardService::VIEWER_BORROWER,
                                [
                                    'status_label' => $asset->estimated_value
                                        ? null
                                        : __('borrower.profile.valuation_in_progress'),
                                ]
                            );
                        @endphp
                        <div class="h-full">
                            <x-site.collateral-card :selected="$card" :type-icons="$typeIcons">
                                <div class="mt-2">
                                    @include('site.borrower.profile._asset_availability', ['availability' => $availability, 'showHint' => false])
                                </div>
                                <button type="button" @click="openAsset = {{ $asset->id }}; editingAsset = null"
                                        class="mt-3 inline-flex items-center justify-center w-full bg-gray-900 hover:bg-black text-white font-semibold px-4 py-2.5 rounded-xl text-sm">
                                    {{ __('borrower.profile.view_asset') }}
                                </button>
                            </x-site.collateral-card>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Add collateral — type picker is step 1 of the wizard (?add=1) --}}
            <div class="glass-card p-5 sm:p-6">
                @if ($assets->isEmpty())
                    <div class="rounded-2xl bg-slate-50 ring-1 ring-slate-200/80 px-4 py-4 mb-4">
                        <p class="text-sm font-semibold text-gray-900">{{ __('borrower.profile.collateral_none_needed_title') }}</p>
                        <p class="text-sm text-gray-600 mt-1">{{ __('borrower.profile.collateral_none_needed_body') }}</p>
                    </div>
                @endif
                <a href="{{ route('site.borrower.profile', array_filter([
                        'section' => 'assets',
                        'add' => 1,
                        'uw' => $uwPrompt ? 1 : null,
                        'application' => $currentAppId,
                    ])) }}"
                   class="inline-flex items-center gap-2 bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-6 py-3 rounded-xl text-sm">
                    <span class="text-lg leading-none">＋</span>
                    {{ $assets->isEmpty() ? __('borrower.profile.add_first_collateral') : __('borrower.profile.add_new_collateral') }}
                </a>
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
                    $viewRows = collect(\App\Models\CustomerAsset::detailFieldsFor($asset->asset_type))
                        ->map(function ($field) use ($asset) {
                            $val = ($field['column'] ?? false) ? $asset->{$field['key']} : $asset->detail($field['key']);

                            return filled($val) ? [
                                'label' => __('borrower.profile.collateral_fields.'.$field['key']),
                                'value' => $val,
                            ] : null;
                        })
                        ->filter()
                        ->values();
                    $availability = $assetAvailabilities[$asset->id] ?? ['code' => 'available', 'selectable' => false];
                @endphp
                <div x-show="openAsset === {{ $asset->id }}" x-cloak x-transition
                     class="fixed inset-0 z-[70] bg-black/60 flex items-end lg:items-center justify-center p-0 lg:p-4"
                     @keydown.escape.window="openAsset = null" @click.self="openAsset = null">
                    <div class="bg-white w-full lg:max-w-2xl lg:rounded-2xl rounded-t-2xl max-h-[92vh] overflow-y-auto">
                        <div class="sticky top-0 bg-white/95 backdrop-blur px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3 z-10">
                            <div class="min-w-0">
                                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.profile.asset_types.'.$asset->asset_type) }}</p>
                                <h2 class="font-bold text-gray-900 truncate">{{ $asset->label }}</h2>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <button type="button" x-show="editingAsset !== {{ $asset->id }}" x-cloak
                                        @click="editingAsset = {{ $asset->id }}"
                                        class="inline-flex items-center justify-center rounded-xl bg-brand-gold px-3.5 py-2 text-xs font-bold text-brand shadow-sm hover:brightness-95">
                                    {{ __('borrower.apply.edit') }}
                                </button>
                                <button type="button" x-show="editingAsset === {{ $asset->id }}" x-cloak
                                        @click="editingAsset = null"
                                        class="inline-flex items-center justify-center rounded-xl bg-white px-3.5 py-2 text-xs font-bold text-brand ring-1 ring-brand/20 hover:bg-brand-muted/40">
                                    {{ __('borrower.profile.cancel') }}
                                </button>
                                <button type="button" @click="openAsset = null; editingAsset = null" class="size-9 grid place-items-center rounded-full hover:bg-gray-100 text-gray-500 text-xl">×</button>
                            </div>
                        </div>

                        <div class="p-5 space-y-6" id="asset-edit-{{ $asset->id }}">
                            <div x-show="editingAsset !== {{ $asset->id }}" class="space-y-4" data-asset-mode="view">
                                @include('site.borrower.profile._asset_availability', ['availability' => $availability, 'showHint' => true])
                                <dl class="grid sm:grid-cols-2 gap-3">
                                    <div class="sm:col-span-2">
                                        <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.profile.asset_label') }}</dt>
                                        <dd class="mt-0.5 text-sm font-semibold text-gray-900">{{ $asset->label }}</dd>
                                    </div>
                                    @foreach ($viewRows as $row)
                                        <div>
                                            <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ $row['label'] }}</dt>
                                            <dd class="mt-0.5 text-sm font-semibold text-gray-900 break-words">{{ $row['value'] }}</dd>
                                        </div>
                                    @endforeach
                                    @if (filled($asset->description))
                                        <div class="sm:col-span-2">
                                            <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.profile.description') }}</dt>
                                            <dd class="mt-0.5 text-sm text-gray-800">{{ $asset->description }}</dd>
                                        </div>
                                    @endif
                                </dl>
                            </div>

                            <form method="POST" action="{{ route('site.borrower.profile.assets.update', $asset) }}"
                                  x-show="editingAsset === {{ $asset->id }}" x-cloak
                                  data-asset-mode="edit"
                                  class="rounded-2xl bg-brand-muted/25 ring-1 ring-brand/10 p-4 space-y-4">
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
                                            $isYearField = in_array($field['key'], ['year', 'purchase_year'], true);
                                            $vehicleMaxAge = (int) (\App\Models\Setting::get('asset_lending.vehicle_max_age_years')
                                                ?? config('asset_lending.vehicle_max_age_years', 10));
                                            $yearMax = (int) now()->year;
                                            $yearMin = $yearMax - max(1, $vehicleMaxAge);
                                        @endphp
                                        <div>
                                            <label class="block text-[11px] font-medium text-gray-600 mb-1">{{ __('borrower.profile.collateral_fields.'.$field['key']) }} <span class="text-red-500">*</span></label>
                                            @if ($isYearField)
                                                @php
                                                    $yearOptions = [];
                                                    for ($y = $yearMax; $y >= $yearMin; $y--) {
                                                        $yearOptions[(string) $y] = (string) $y;
                                                    }
                                                @endphp
                                                <x-site.sheet-select
                                                    :name="$inputName"
                                                    :options="$yearOptions"
                                                    :value="old($oldKey, $val)"
                                                    :required="true"
                                                    :placeholder="__('borrower.profile.select_year')"
                                                    select-class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm"
                                                />
                                            @else
                                                <input type="{{ ($field['type'] ?? '') === 'number' ? 'number' : 'text' }}"
                                                       name="{{ $inputName }}" value="{{ old($oldKey, $val) }}" required maxlength="150"
                                                       placeholder="{{ __('borrower.profile.collateral_placeholders.'.$field['key']) }}"
                                                       class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                                            @endif
                                        </div>
                                    @endforeach
                                    <div class="sm:col-span-2">
                                        <label class="block text-[11px] font-medium text-gray-600 mb-1">{{ __('borrower.profile.description') }}</label>
                                        <textarea name="description" rows="2" class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm">{{ old('description', $asset->description) }}</textarea>
                                    </div>
                                </div>
                                @if ($asset->asset_type === 'vehicle')
                                    <div class="rounded-2xl bg-white ring-1 ring-brand/15 p-4 space-y-3">
                                        <p class="text-xs uppercase tracking-widest text-brand font-semibold">{{ __('borrower.profile.vehicle_insurance') }}</p>
                                        <x-site.sheet-select
                                            name="details[insurance_type]"
                                            :label="__('borrower.profile.collateral_fields.insurance_type')"
                                            :options="[
                                                'comprehensive' => __('borrower.profile.insurance_comprehensive'),
                                                'third_party' => __('borrower.profile.insurance_third_party'),
                                            ]"
                                            :value="old('details.insurance_type', $asset->detail('insurance_type'))"
                                            :required="true"
                                            :placeholder="__('borrower.profile.insurance_type_placeholder')"
                                            select-class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm"
                                        />
                                        <p class="text-[11px] text-gray-500">{{ __('borrower.profile.insurance_type_help') }}</p>
                                        <div class="grid sm:grid-cols-2 gap-3">
                                            <div>
                                                <label class="block text-[11px] font-medium text-gray-600 mb-1">{{ __('borrower.profile.collateral_fields.insurance_policy_number') }} <span class="text-red-500">*</span></label>
                                                <input type="text" name="details[insurance_policy_number]" value="{{ old('details.insurance_policy_number', $asset->detail('insurance_policy_number')) }}" required maxlength="150"
                                                       placeholder="{{ __('borrower.profile.collateral_placeholders.insurance_policy_number') }}"
                                                       class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                                            </div>
                                            <div>
                                                <x-site.date-input
                                                    name="details[insurance_expires_at]"
                                                    :label="__('borrower.profile.collateral_fields.insurance_expires_at')"
                                                    :value="old('details.insurance_expires_at', $asset->detail('insurance_expires_at'))"
                                                    :required="true"
                                                    :min="now()->format('Y-m-d')"
                                                    :max="now()->addYears(5)->format('Y-m-d')"
                                                    :default="now()->addYear()->format('Y-m-d')"
                                                    input-class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-base"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                @endif
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
                                    <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold mb-3">{{ __('borrower.profile.ownership_document') }}</p>
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
                                                        class="h-16 w-16 rounded-lg overflow-hidden ring-1 ring-gray-200 bg-white cursor-zoom-in block">
                                                    <img src="{{ asset('storage/'.$ownershipDoc) }}" alt="" class="h-full w-full object-cover">
                                                </button>
                                            @endif
                                        </div>
                                    @else
                                        <p class="text-xs text-gray-500 mb-2">{{ __('borrower.profile.no_document_yet') }}</p>
                                    @endif
                                    <form method="POST" action="{{ route('site.borrower.profile.assets.documents.replace', $asset) }}" enctype="multipart/form-data" class="mt-3" x-show="editingAsset === {{ $asset->id }}" x-cloak>
                                        @csrf
                                        <input type="hidden" name="document" value="ownership_document">
                                        <label class="inline-flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-900 font-semibold px-4 py-2.5 rounded-xl text-sm cursor-pointer">
                                            {{ $ownershipDoc ? __('borrower.profile.replace_document') : __('borrower.profile.upload_document') }}
                                            <input type="file" name="file" accept="image/jpeg,image/png,image/webp,application/pdf,.jpg,.jpeg,.png,.webp,.pdf" class="sr-only" onchange="this.form.submit()">
                                        </label>
                                    </form>
                                </div>

                                @if ($asset->asset_type === 'vehicle')
                                    <div class="rounded-2xl ring-1 ring-brand/20 bg-brand-muted/20 p-4 space-y-3">
                                        <p class="text-xs uppercase tracking-widest text-brand font-semibold">{{ __('borrower.profile.vehicle_insurance') }}</p>
                                        <dl x-show="editingAsset !== {{ $asset->id }}" class="grid sm:grid-cols-2 gap-3">
                                            @if ($insuranceDetails['insurance_type'])
                                                <div>
                                                    <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.profile.collateral_fields.insurance_type') }}</dt>
                                                    <dd class="mt-0.5 text-sm font-semibold text-gray-900">
                                                        {{ $insuranceDetails['insurance_type'] === 'third_party'
                                                            ? __('borrower.profile.insurance_third_party')
                                                            : __('borrower.profile.insurance_comprehensive') }}
                                                    </dd>
                                                </div>
                                            @endif
                                            @if ($insuranceDetails['insurance_policy_number'])
                                                <div>
                                                    <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.profile.collateral_fields.insurance_policy_number') }}</dt>
                                                    <dd class="mt-0.5 text-sm font-semibold text-gray-900">{{ $insuranceDetails['insurance_policy_number'] }}</dd>
                                                </div>
                                            @endif
                                            @if ($insuranceDetails['insurance_expires_at'])
                                                <div>
                                                    <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.profile.collateral_fields.insurance_expires_at') }}</dt>
                                                    <dd class="mt-0.5 text-sm font-semibold text-gray-900 tabular-nums">{{ $insuranceDetails['insurance_expires_at'] }}</dd>
                                                </div>
                                            @endif
                                        </dl>
                                        @if ($insuranceDoc)
                                            <div class="flex flex-wrap items-center gap-3">
                                                @if (str_ends_with(strtolower($insuranceDoc), '.pdf'))
                                                    <x-site.document-view-button
                                                        :url="asset('storage/'.$insuranceDoc)"
                                                        type="pdf"
                                                        class="inline-flex items-center gap-2 text-sm text-brand font-semibold"
                                                    >📄 {{ __('borrower.profile.view_document') }}</x-site.document-view-button>
                                                @else
                                                    <button type="button" @click="lightbox = @js(asset('storage/'.$insuranceDoc))"
                                                            class="h-16 w-16 rounded-lg overflow-hidden ring-1 ring-gray-200 bg-white cursor-zoom-in block">
                                                        <img src="{{ asset('storage/'.$insuranceDoc) }}" alt="" class="h-full w-full object-cover">
                                                    </button>
                                                @endif
                                            </div>
                                        @else
                                            <p class="text-xs text-amber-700">{{ __('borrower.profile.no_document_yet') }}</p>
                                        @endif
                                        <form method="POST" action="{{ route('site.borrower.profile.assets.documents.replace', $asset) }}" enctype="multipart/form-data" x-show="editingAsset === {{ $asset->id }}" x-cloak>
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
                            <div class="pt-4 border-t border-gray-100" x-show="editingAsset === {{ $asset->id }}" x-cloak>
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
