@php
    $optionalItems = collect($incomeProofChecklist ?? [])->where('required', false)->values();
    $uploadedOptional = $optionalItems->filter(fn ($item) => ! empty($item['document']));
    $focusOpen = request()->query('focus') === 'additional'
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
        :empty="$uploadedOptional->isEmpty()"
        :default-open="$focusOpen">
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
                      docType: @js(old('additional_document_type', '')),
                      uploading: false,
                      options: @js($optionMap),
                  }"
                  @submit="if (!docType) { $event.preventDefault(); return; } uploading = true">
                @csrf @method('PUT')
                @if ($wizardMode ?? false)
                    <input type="hidden" name="wizard" value="1">
                @endif
                @if (! empty($returnUrl))
                    <input type="hidden" name="return" value="{{ $returnUrl }}">
                @endif
                <input type="hidden" name="focus" value="additional">
                <input type="hidden" name="additional_document_type" :value="docType">

                <p class="text-sm text-gray-600 mb-5">{{ __('borrower.profile.additional_documents_hint') }}</p>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('borrower.profile.additional_document_type') }}</label>
                    <select x-model="docType"
                            class="w-full rounded-xl border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3.5 py-3 text-sm bg-white">
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
                                :required="false"
                                :labels="[
                                    'hint' => __('borrower.profile.multi_page_hint'),
                                    'uploadFile' => __('borrower.profile.capture_pages_upload'),
                                    'capturePage' => __('borrower.profile.capture_pages'),
                                ]"
                            />
                        </div>
                    @endforeach
                </div>

                <button type="submit"
                        :disabled="!docType"
                        class="mt-6 bg-amber-500 hover:bg-amber-400 disabled:opacity-50 disabled:cursor-not-allowed text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
                    {{ __('borrower.profile.save_documents') }}
                </button>

                <x-site.upload-busy-overlay />
            </form>
        </x-slot:form>
    </x-site.profile-section-card>
@endif
