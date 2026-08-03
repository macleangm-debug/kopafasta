@php
    $incomeProofChecklist = $incomeProofChecklist ?? [];
    $incomeProofEmployed = $incomeProofEmployed ?? false;
    $incomeProofMethod = $incomeProofMethod ?? null;
    $incomePrimaryOptions = $incomePrimaryOptions ?? [];
    $requiredItems = collect($incomeProofChecklist)->where('required', true);
    $primaryItems = $requiredItems->where('group', 'primary');
    $employedItems = $incomeProofEmployed ? $requiredItems : collect();
    $hasStatement = $incomeProofEmployed
        ? $requiredItems->every(fn ($item) => ! empty($item['complete']))
        : collect($incomeProofChecklist)->contains(fn ($item) => ($item['group'] ?? null) === 'primary' && ! empty($item['complete']));
    $focusOpen = in_array(request()->query('focus'), ['income', 'statement', 'documents'], true)
        || ($wizardMode ?? false)
        || $errors->hasAny(['bank_statement', 'salary_slip', 'mobile_money_statement', 'income_proof_method']);
@endphp

<x-site.profile-section-card
    section-id="profile-income-statement"
    icon="🏦"
    :title="__('borrower.profile.income_statement_card_title')"
    :empty="! $hasStatement"
    :default-open="$focusOpen && request()->query('focus') !== 'additional'">
    <x-slot:view>
        @if ($hasStatement)
            <div class="space-y-4">
                @foreach ($requiredItems->filter(fn ($item) => ! empty($item['document'])) as $item)
                    <x-site.profile-document-field
                        :document="$item['document']"
                        :field-name="$item['key'] ?? 'income_proof'"
                        :document-code="$item['document_code'] ?? ($item['key'] ?? null)"
                        mode="multi"
                        :label="$item['label']"
                        :input-host-id="'income-view-'.($item['key'] ?? $loop->index)"
                        :read-only="true"
                    />
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-600">{{ __('borrower.profile.income_statement_card_hint') }}</p>
            <button type="button" @click="open = true" class="mt-3 text-sm font-semibold text-amber-700 hover:text-amber-800">
                {{ __('borrower.profile.add_details') }}
            </button>
        @endif
    </x-slot:view>
    <x-slot:form>
        <form method="POST"
              action="{{ route('site.borrower.profile.update', ['section' => 'kyc']) }}{{ ($wizardMode ?? false) ? '?wizard=1' : '' }}{{ ! empty($returnUrl) ? (($wizardMode ?? false) ? '&' : '?').'return='.urlencode($returnUrl) : '' }}"
              enctype="multipart/form-data"
              x-data="{ incomeMethod: @js(old('income_proof_method', $incomeProofMethod ?? '')), uploading: false }"
              @submit="uploading = true">
            @csrf @method('PUT')
            @if ($wizardMode ?? false)
                <input type="hidden" name="wizard" value="1">
            @endif
            @if (! empty($returnUrl))
                <input type="hidden" name="return" value="{{ $returnUrl }}">
            @endif
            <input type="hidden" name="focus" value="income">

            <p class="text-sm text-gray-600 mb-5">{{ __('borrower.profile.income_statement_card_hint') }}</p>

            <div class="space-y-5">
                @if ($incomeProofEmployed)
                    @foreach ($employedItems as $item)
                        <div class="rounded-xl border border-gray-100 p-4 bg-white">
                            <p class="text-sm font-semibold text-gray-900 mb-3">
                                {{ $item['label'] }} <span class="text-red-500">*</span>
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
                @else
                    <div class="space-y-3">
                        <p class="text-xs font-semibold uppercase tracking-widest text-gray-500">{{ __('borrower.profile.income_method_question') }}</p>
                        @foreach ($incomePrimaryOptions as $option)
                            <label class="flex items-start gap-3 rounded-xl border px-4 py-3 cursor-pointer"
                                   :class="incomeMethod === @js($option['key']) ? 'border-amber-300 bg-amber-50 ring-1 ring-amber-200' : 'border-gray-200 hover:border-gray-300'">
                                <input type="radio" name="income_proof_method" value="{{ $option['key'] }}"
                                       class="mt-1 text-amber-500 focus:ring-amber-500"
                                       x-model="incomeMethod" required>
                                <span class="text-sm font-medium text-gray-900">{{ $option['label'] }}</span>
                            </label>
                        @endforeach
                    </div>

                    @foreach ($primaryItems as $item)
                        <div class="rounded-xl border border-gray-100 p-4 bg-white" x-show="incomeMethod === @js($item['key'])" x-cloak>
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
                                :input-host-id="$item['key'].'-statement-upload'"
                                :required="true"
                                :labels="[
                                    'hint' => __('borrower.profile.multi_page_hint'),
                                    'uploadFile' => __('borrower.profile.capture_pages_upload'),
                                    'capturePage' => __('borrower.profile.capture_pages'),
                                ]"
                            />
                        </div>
                    @endforeach
                @endif
            </div>

            <button type="submit" class="mt-6 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
                {{ __('borrower.profile.save_documents') }}
            </button>

            <x-site.upload-busy-overlay />
        </form>
    </x-slot:form>
</x-site.profile-section-card>
