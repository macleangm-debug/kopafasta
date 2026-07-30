<x-site.borrower-layout :title="brand_title(__('borrower.profile.account_title'))" active="profile" content-width="wide">

    <div>
        @include('site.borrower.profile._profile_shell', [
            'title' => __('borrower.profile.account_title'),
            'subtitle' => __('borrower.profile.kyc_subtitle'),
            'customer' => $customer,
            'active' => 'kyc',
            'wizardMode' => $wizardMode ?? false,
            'wizardKey' => $wizardKey ?? 'documents',
        ])

        @php
            $documentsComplete = app(\App\Services\ProfileCompletionService::class)->isDocumentsComplete($customer);
            $incomeLabel = filled($customer->income_range)
                ? income_range_label($customer->income_range)
                : null;
            $saveConfirm = [
                'title' => __('borrower.profile.save_confirm_title'),
                'message' => __('borrower.profile.save_confirm_message'),
                'confirmLabel' => __('borrower.profile.save'),
                'confirmClass' => 'bg-amber-500 hover:bg-amber-400 text-gray-900',
            ];
        @endphp

        <x-site.profile-section-card
            section-id="profile-documents"
            icon="📄"
            :title="__('borrower.profile.proof_of_income_title')"
            :complete="$documentsComplete"
            :empty="! $documentsComplete"
            :default-open="($wizardMode ?? false) || ($editing ?? false)">
            <x-slot:view>
                <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-gray-500">{{ __('borrower.profile.income_range') }}</dt>
                        @if ($incomeLabel)
                            <dd class="font-medium mt-0.5">{{ $incomeLabel }}</dd>
                        @else
                            <dd class="mt-0.5"><button type="button" @click="open = true" class="text-sm font-semibold text-amber-700 hover:text-amber-800">{{ __('borrower.profile.add_details') }}</button></dd>
                        @endif
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('borrower.profile.documents_proof') }}</dt>
                        <dd class="font-medium mt-0.5">{{ $documentsComplete ? __('borrower.profile.section_complete') : __('borrower.profile.section_incomplete') }}</dd>
                    </div>
                </dl>
                @unless ($documentsComplete)
                    <button type="button" @click="open = true"
                            class="mt-4 text-sm font-semibold text-amber-700 hover:text-amber-800">
                        {{ __('borrower.profile.add_details') }}
                    </button>
                @endunless
            </x-slot:view>
            <x-slot:form>
                <form method="POST" action="{{ route('site.borrower.profile.update', ['section' => 'kyc']) }}{{ ($wizardMode ?? false) ? '?wizard=1' : '' }}{{ ! empty($returnUrl) ? (($wizardMode ?? false) ? '&' : '?').'return='.urlencode($returnUrl) : '' }}"
                      enctype="multipart/form-data"
                      x-data="{ incomeMethod: @js(old('income_proof_method', $incomeProofMethod ?? '')) }"
                      @submit.prevent="window.confirmForm($el, @js($saveConfirm))">
                    @csrf @method('PUT')
                    @if ($wizardMode ?? false)
                        <input type="hidden" name="wizard" value="1">
                    @endif
                    @if (! empty($returnUrl))
                        <input type="hidden" name="return" value="{{ $returnUrl }}">
                    @endif

                    @if ($incomeProofEmployed ?? false)
                        <p class="text-sm text-gray-600 mb-4">{{ __('borrower.profile.proof_of_income_employed_hint') }}</p>
                    @else
                        <p class="text-sm text-gray-600 mb-4">{{ __('borrower.profile.proof_of_income_informal_hint') }}</p>
                    @endif

                    <div class="space-y-5">
                        @php
                            $requiredItems = collect($incomeProofChecklist ?? [])->where('required', true);
                            $optionalItems = collect($incomeProofChecklist ?? [])->where('required', false);
                            $primaryItems = $requiredItems->where('group', 'primary');
                        @endphp

                        @if ($incomeProofEmployed ?? false)
                            <div class="space-y-5">
                                @foreach ($requiredItems as $item)
                                    <div class="rounded-xl border border-gray-100 p-4">
                                        <p class="text-sm font-semibold text-gray-900 mb-3">
                                            {{ $item['label'] }}
                                            <span class="text-red-500">*</span>
                                        </p>
                                        <x-site.profile-document-field
                                            :document="$item['document'] ?? null"
                                            :field-name="$item['key']"
                                            :pages-field-name="$item['key'].'_pages'"
                                            :mode="($item['multi'] ?? false) ? 'multi' : 'single'"
                                            :label="$item['label']"
                                            :input-host-id="$item['key'].'-upload'"
                                            :required="true"
                                            :labels="[
                                                'hint' => __('borrower.profile.multi_page_hint'),
                                                'uploadFile' => __('borrower.profile.capture_pages_upload'),
                                                'capturePage' => __('borrower.profile.capture_pages'),
                                            ]"
                                        />
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="rounded-xl border border-gray-100 p-4 space-y-4">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">{{ __('borrower.profile.proof_of_income_title') }}</p>
                                    <p class="text-xs text-gray-500 mt-1">{{ __('borrower.profile.income_method_question') }}</p>
                                </div>

                                <div class="space-y-2">
                                    @foreach ($incomePrimaryOptions ?? [] as $option)
                                        <label class="flex items-start gap-3 rounded-lg border px-4 py-3 cursor-pointer"
                                               :class="incomeMethod === @js($option['key']) ? 'border-amber-300 bg-amber-50 ring-1 ring-amber-200' : 'border-gray-200 hover:border-gray-300'">
                                            <input type="radio" name="income_proof_method" value="{{ $option['key'] }}"
                                                   class="mt-1 text-amber-500 focus:ring-amber-500"
                                                   x-model="incomeMethod">
                                            <span class="text-sm font-medium text-gray-900">{{ $option['label'] }}</span>
                                        </label>
                                    @endforeach
                                </div>

                                @foreach ($primaryItems as $item)
                                    <div class="rounded-xl border border-gray-100 p-4" x-show="incomeMethod === @js($item['key'])" x-cloak>
                                        <p class="text-sm font-semibold text-gray-900 mb-3">
                                            {{ __('borrower.profile.upload') }} {{ $item['label'] }}
                                            <span class="text-red-500">*</span>
                                        </p>
                                        <x-site.profile-document-field
                                            :document="$item['document'] ?? null"
                                            :field-name="$item['key']"
                                            :pages-field-name="$item['key'].'_pages'"
                                            :mode="($item['multi'] ?? false) ? 'multi' : 'single'"
                                            :label="$item['label']"
                                            :input-host-id="$item['key'].'-upload'"
                                            :required="true"
                                            :labels="[
                                                'hint' => __('borrower.profile.multi_page_hint'),
                                                'uploadFile' => __('borrower.profile.capture_pages_upload'),
                                                'capturePage' => __('borrower.profile.capture_pages'),
                                            ]"
                                        />
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if ($optionalItems->isNotEmpty())
                            <div class="rounded-xl border border-dashed border-gray-200 p-4 bg-gray-50/40">
                                <div class="flex items-start justify-between gap-3 mb-1">
                                    <h3 class="text-sm font-semibold text-gray-900">{{ __('borrower.profile.strengthen_application_title') }}</h3>
                                    <span class="shrink-0 text-[10px] uppercase tracking-wider font-semibold text-gray-500 bg-white ring-1 ring-gray-200 rounded-full px-2 py-0.5">
                                        {{ __('borrower.profile.optional_not_required') }}
                                    </span>
                                </div>
                                <p class="text-xs text-gray-500 mb-4">{{ __('borrower.profile.strengthen_application_hint') }}</p>
                                <div class="space-y-5">
                                    @foreach ($optionalItems as $item)
                                        <div class="rounded-xl border border-gray-100 p-4 bg-white">
                                            <p class="text-sm font-semibold text-gray-900 mb-3">
                                                {{ $item['label'] }}
                                                <span class="text-xs font-normal text-gray-400">{{ __('borrower.profile.optional') }}</span>
                                            </p>
                                            <x-site.profile-document-field
                                                :document="$item['document'] ?? null"
                                                :field-name="$item['key']"
                                                :pages-field-name="$item['key'].'_pages'"
                                                :mode="($item['multi'] ?? false) ? 'multi' : 'single'"
                                                :label="$item['label']"
                                                :input-host-id="$item['key'].'-upload'"
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
                            </div>
                        @endif
                    </div>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <button class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
                            {{ ($wizardMode ?? false) ? __('borrower.profile_wizard.save_continue') : __('borrower.profile.save_documents') }}
                        </button>
                    </div>
                </form>
            </x-slot:form>
        </x-site.profile-section-card>

        @include('site.borrower.profile._wizard_footer', ['customer' => $customer, 'wizardMode' => $wizardMode ?? false, 'wizardKey' => $wizardKey ?? 'documents'])
    </div>

    @stack('scripts')
</x-site.borrower-layout>
