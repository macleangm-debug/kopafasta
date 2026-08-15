@php
    $optionalItems = collect($incomeProofChecklist ?? [])->where('required', false)->values();
    $uploadedOptional = $optionalItems->filter(fn ($item) => ! empty($item['document']));
    $focusOpen = request()->query('focus') === 'additional'
        || filled(request()->query('doc'))
        || $errors->hasAny($optionalItems->pluck('key')->all());
    $optionMap = $optionalItems->mapWithKeys(fn ($item) => [$item['key'] => [
        'label' => $item['label'],
        'multi' => (bool) ($item['multi'] ?? true),
        'hasDocument' => ! empty($item['document']),
    ]])->all();
@endphp

@if ($optionalItems->isNotEmpty())
    <x-site.profile-section-card
        section-id="profile-additional-documents"
        icon="📎"
        :title="__('borrower.profile.additional_documents_title')"
        :complete="$uploadedOptional->isNotEmpty()"
        :empty="$uploadedOptional->isEmpty()"
        :default-open="$focusOpen"
        :default-edit="$focusOpen">
        <x-slot:view>
            @if ($uploadedOptional->isNotEmpty())
                <div class="space-y-4">
                    @foreach ($uploadedOptional as $item)
                        <x-site.profile-document-field
                            :document="$item['document']"
                            :field-name="$item['key']"
                            :document-code="$item['key']"
                            mode="multi"
                            :label="$item['label']"
                            :input-host-id="'additional-view-'.$item['key']"
                            :read-only="true"
                        />
                    @endforeach
                    <button type="button" @click="open = true" class="text-sm font-semibold text-amber-700 hover:text-amber-800">
                        {{ __('borrower.profile.add_another_document') }}
                    </button>
                </div>
            @else
                <p class="text-sm text-gray-600">{{ __('borrower.profile.additional_documents_hint') }}</p>
                <button type="button" @click="open = true" class="mt-3 text-sm font-semibold text-amber-700 hover:text-amber-800">
                    {{ __('borrower.profile.add_details') }}
                </button>
            @endif
        </x-slot:view>
        <x-slot:form>
            <form method="POST"
                  action="{{ route('site.borrower.profile.update', ['section' => 'kyc']) }}{{ ($wizardMode ?? false) ? '?wizard=1' : '' }}{{ ! empty($returnUrl) ? (($wizardMode ?? false) ? '&' : '?').'return='.urlencode($returnUrl) : '' }}"
                  enctype="multipart/form-data"
                  x-data="{
                      docType: @js(old('additional_document_type', request()->query('doc', ''))),
                      uploading: false,
                      ready: false,
                      pickerOpen: false,
                      options: @js($optionMap),
                      refreshReady() {
                          this.ready = !!this.docType && (window.KopaFastaForm?.isComplete(this.$el, { onlyVisible: true }) ?? false);
                      },
                      init() {
                          this.refreshReady();
                          setInterval(() => this.refreshReady(), 400);
                      },
                  }"
                  x-on:input="refreshReady()"
                  x-on:change="refreshReady()"
                  data-saving-message="{{ __('borrower.profile.uploading_documents') }}"
                  @submit="if (!docType || !ready) { $event.preventDefault(); return; } uploading = true">
                @csrf @method('PUT')
                @if ($wizardMode ?? false)
                    <input type="hidden" name="wizard" value="1">
                @endif
                @if (! empty($returnUrl))
                    <input type="hidden" name="return" value="{{ $returnUrl }}">
                @endif
                <input type="hidden" name="focus" value="additional">
                <input type="hidden" name="additional_document_type" :value="docType">

                <p class="text-sm text-gray-600 mb-5">{{ __('borrower.profile.additional_documents_hint_short') }}</p>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('borrower.profile.additional_document_type') }}</label>
                    <div class="lg:hidden">
                        <button type="button" @click="pickerOpen = true"
                                class="w-full inline-flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-800 hover:border-brand/30 transition">
                            <span class="flex-1 text-left truncate"
                                  x-text="docType && options[docType] ? options[docType].label : @js(__('borrower.profile.additional_document_type_placeholder'))"></span>
                            <svg class="w-4 h-4 text-gray-400 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                        </button>
                        <x-site.bottom-sheet :title="__('borrower.profile.additional_document_type')" open="pickerOpen">
                            <div class="space-y-1 max-h-[60vh] overflow-y-auto">
                                <button type="button" @click="docType = ''; pickerOpen = false; refreshReady()"
                                        class="w-full text-left px-4 py-3 rounded-xl text-sm font-medium text-gray-500 hover:bg-gray-50"
                                        :class="!docType ? 'bg-brand-muted text-brand ring-1 ring-brand/20' : ''">
                                    {{ __('borrower.profile.additional_document_type_placeholder') }}
                                </button>
                                @foreach ($optionalItems as $item)
                                    <button type="button" @click="docType = @js($item['key']); pickerOpen = false; refreshReady()"
                                            class="w-full text-left px-4 py-3 rounded-xl text-sm font-medium text-gray-800 hover:bg-gray-50"
                                            :class="docType === @js($item['key']) ? 'bg-brand-muted text-brand ring-1 ring-brand/20' : ''">
                                        {{ $item['label'] }}
                                    </button>
                                @endforeach
                            </div>
                        </x-site.bottom-sheet>
                    </div>
                    <select x-model="docType" @change="refreshReady()"
                            class="hidden lg:block w-full rounded-xl border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3.5 py-3 text-sm bg-white">
                        <option value="">{{ __('borrower.profile.additional_document_type_placeholder') }}</option>
                        @foreach ($optionalItems as $item)
                            <option value="{{ $item['key'] }}">{{ $item['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mt-5" x-show="docType" x-cloak>
                    @foreach ($optionalItems as $item)
                        <div x-show="docType === @js($item['key'])" x-cloak class="rounded-xl border border-gray-100 p-4 bg-white">
                            <p class="text-sm font-semibold text-gray-900 mb-3">{{ $item['label'] }}</p>
                            @if (! empty($item['document']))
                                <div class="mb-4">
                                    <x-site.profile-document-field
                                        :document="$item['document']"
                                        :field-name="$item['key']"
                                        :document-code="$item['key']"
                                        mode="multi"
                                        :label="$item['label']"
                                        :input-host-id="'additional-existing-'.$item['key']"
                                        :read-only="true"
                                    />
                                </div>
                            @endif
                            <x-site.profile-document-field
                                :document="null"
                                :field-name="$item['key']"
                                :pages-field-name="$item['key'].'_pages'"
                                :mode="($item['multi'] ?? false) ? 'multi' : 'single'"
                                :label="$item['label']"
                                :input-host-id="'additional-upload-'.$item['key']"
                                :required="true"
                                :labels="[
                                    'uploadFile' => __('borrower.profile.capture_pages_upload'),
                                    'capturePage' => __('borrower.profile.capture_pages'),
                                ]"
                            />
                        </div>
                    @endforeach
                </div>

                <button type="submit"
                        x-show="ready"
                        x-cloak
                        class="mt-6 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
                    {{ __('borrower.profile.save_documents') }}
                </button>

            </form>
        </x-slot:form>
    </x-site.profile-section-card>
@endif
