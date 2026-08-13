@php
    $incomeProofChecklist = $incomeProofChecklist ?? [];
    $incomeProofEmployed = $incomeProofEmployed ?? false;
    $incomeProofMethod = $incomeProofMethod ?? null;
    $incomePrimaryOptions = $incomePrimaryOptions ?? [];
    $incomeProofService = app(\App\Services\IncomeProofService::class);
    $statementAccounts = $incomeProofService->allStatementAccountDetails($customer);
    $presentMethods = $incomeProofService->presentPrimaryMethods($customer);
    $requiredItems = collect($incomeProofChecklist)->where('required', true);
    $primaryItems = $requiredItems->where('group', 'primary');
    $employedItems = $incomeProofEmployed ? $requiredItems : collect();
    $hasStatement = $incomeProofEmployed
        ? $requiredItems->every(fn ($item) => ! empty($item['complete']))
        : collect($incomeProofChecklist)->contains(fn ($item) => ($item['group'] ?? null) === 'primary' && ! empty($item['complete']));
    $canAddAnotherStatement = ! $incomeProofEmployed && count($presentMethods) === 1;
    $documentsStale = in_array('documents', app(\App\Services\KycFreshnessService::class)->sectionsDueForRefresh($customer), true);
    $focusOpen = in_array(request()->query('focus'), ['income', 'statement', 'documents'], true)
        || ($wizardMode ?? false)
        || $errors->hasAny(['bank_statement', 'salary_slip', 'mobile_money_statement', 'income_proof_method']);
    // Editing always starts at bank/mobile chooser (like payment accounts), unless validation bounced mid-flow.
    $initialMethod = old('income_proof_method', '');
    $initialStep = $incomeProofEmployed
        ? 1
        : match (true) {
            $errors->hasAny(['bank_statement', 'mobile_money_statement', 'salary_slip']) => 3,
            $errors->hasAny(['income_account_provider', 'income_account_number', 'income_account_name']) => 2,
            default => 1,
        };
    if ($initialStep > 1 && $initialMethod === '') {
        $initialMethod = old('income_proof_method', $incomeProofMethod ?? '');
    }
@endphp

<x-site.profile-section-card
    section-id="profile-income-statement"
    icon="🏦"
    :title="__('borrower.profile.income_statement_card_title')"
    :complete="$hasStatement"
    :stale="$documentsStale && $hasStatement"
    :empty="! $hasStatement"
    :default-open="$focusOpen && request()->query('focus') !== 'additional'"
    :default-edit="$errors->hasAny(['bank_statement', 'salary_slip', 'mobile_money_statement', 'income_proof_method', 'income_account_provider', 'income_account_number', 'income_account_name'])">
    <x-slot:view>
        @if ($hasStatement)
            <div class="space-y-4">
                @foreach ($primaryItems->filter(fn ($item) => ! empty($item['document'])) as $item)
                    @php
                        $account = $statementAccounts[$item['key']] ?? ['provider' => '', 'number' => '', 'name' => ''];
                    @endphp
                    <div class="rounded-xl ring-1 ring-gray-200 bg-white p-4 space-y-3">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ $item['label'] }}</p>
                                @if (filled($account['provider']) || filled($account['number']))
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        {{ collect([$account['provider'], $account['number']])->filter()->implode(' · ') }}
                                    </p>
                                @endif
                            </div>
                            <button type="button"
                                    @click="open = true; $dispatch('income-statement-reset')"
                                    class="text-xs font-semibold text-brand hover:underline">
                                {{ __('borrower.profile.income_update_this') }}
                            </button>
                        </div>
                        <x-site.profile-document-field
                            :document="$item['document']"
                            :field-name="$item['key'] ?? 'income_proof'"
                            :document-code="$item['document_code'] ?? ($item['key'] ?? null)"
                            mode="multi"
                            :label="$item['label']"
                            :input-host-id="'income-view-'.($item['key'] ?? $loop->index)"
                            :read-only="false"
                        />
                    </div>
                @endforeach
                @unless ($incomeProofEmployed)
                    <div class="flex flex-wrap gap-3">
                        <button type="button"
                                @click="open = true; $dispatch('income-statement-reset')"
                                class="text-sm font-semibold text-brand hover:underline">
                            {{ $canAddAnotherStatement
                                ? __('borrower.profile.income_add_another_statement')
                                : __('borrower.profile.income_add_or_update_statement') }}
                        </button>
                    </div>
                @endunless
            </div>
        @else
            <p class="text-sm text-gray-600">{{ __('borrower.profile.income_statement_card_hint') }}</p>
            <button type="button" @click="open = true; $dispatch('income-statement-reset')" class="mt-3 text-sm font-semibold text-amber-700 hover:text-amber-800">
                {{ __('borrower.profile.add_details') }}
            </button>
        @endif
    </x-slot:view>
    <x-slot:form>
        <form method="POST"
              action="{{ route('site.borrower.profile.update', ['section' => 'kyc']) }}{{ ($wizardMode ?? false) ? '?wizard=1' : '' }}{{ ! empty($returnUrl) ? (($wizardMode ?? false) ? '&' : '?').'return='.urlencode($returnUrl) : '' }}"
              enctype="multipart/form-data"
              novalidate
              x-data="{
                  incomeMethod: @js($initialMethod),
                  step: {{ (int) $initialStep }},
                  employed: @js($incomeProofEmployed),
                  accounts: @js($statementAccounts),
                  uploading: false,
                  ready: false,
                  provider: '',
                  number: '',
                  maxStep() { return this.employed ? 1 : 3; },
                  applyAccountFields() {
                      const row = this.accounts[this.incomeMethod] || { provider: '', number: '' };
                      this.provider = row.provider || '';
                      this.number = row.number || '';
                  },
                  chooseMethod(method) {
                      this.incomeMethod = method;
                      this.applyAccountFields();
                      this.step = 2;
                  },
                  next() {
                      if (this.step === 1 && ! this.incomeMethod && ! this.employed) return;
                      if (this.step < this.maxStep()) this.step++;
                  },
                  prev() {
                      if (this.step === 2) {
                          this.step = 1;
                          this.incomeMethod = '';
                          return;
                      }
                      if (this.step > 1) this.step--;
                  },
                  refreshReady() {
                      this.ready = window.KopaFastaForm?.isComplete(this.$el, { onlyVisible: true }) ?? false;
                  },
                  resetToChooser() {
                      this.step = 1;
                      this.incomeMethod = '';
                      this.provider = '';
                      this.number = '';
                      this.refreshReady();
                  },
                  init() {
                      if (this.incomeMethod) this.applyAccountFields();
                      this.refreshReady();
                      setInterval(() => this.refreshReady(), 400);
                      this._onIncomeReset = () => this.resetToChooser();
                      this._onSectionEdit = (e) => {
                          if (e.detail === 'profile-income-statement') {
                              this.resetToChooser();
                          }
                      };
                      window.addEventListener('income-statement-reset', this._onIncomeReset);
                      window.addEventListener('profile-section-edit', this._onSectionEdit);
                  },
                  destroy() {
                      window.removeEventListener('income-statement-reset', this._onIncomeReset);
                      window.removeEventListener('profile-section-edit', this._onSectionEdit);
                  },
              }"
              x-on:input="refreshReady()"
              x-on:change="refreshReady()"
              @submit="uploading = true">
            @csrf @method('PUT')
            @if ($wizardMode ?? false)
                <input type="hidden" name="wizard" value="1">
            @endif
            @if (! empty($returnUrl))
                <input type="hidden" name="return" value="{{ $returnUrl }}">
            @endif
            <input type="hidden" name="focus" value="income">

            <div class="mb-5 flex items-center gap-2 text-xs font-semibold text-gray-500" x-show="!employed">
                <template x-for="n in maxStep()" :key="'s'+n">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="size-6 rounded-full grid place-items-center text-[11px]"
                              :class="step >= n ? 'bg-brand text-white' : 'bg-gray-100 text-gray-500'"
                              x-text="n"></span>
                        <span x-show="n < maxStep()" class="text-gray-300" aria-hidden="true">·</span>
                    </span>
                </template>
            </div>

            @if ($incomeProofEmployed)
                <div class="space-y-4">
                    <p class="text-sm font-semibold text-gray-900">{{ __('borrower.profile.income_step_docs_title') }}</p>
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
                                    'hint' => __('borrower.profile.multi_page_hint_short'),
                                    'uploadFile' => __('borrower.profile.capture_pages_upload'),
                                    'capturePage' => __('borrower.profile.capture_pages'),
                                ]"
                            />
                        </div>
                    @endforeach
                </div>
            @else
                {{-- Step 1: choose statement type (same pattern as payment accounts) --}}
                <div x-show="step === 1" x-cloak class="space-y-4">
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-500">{{ __('borrower.profile.income_method_question') }}</p>
                    <p class="text-sm text-gray-600">{{ __('borrower.profile.income_method_both_hint') }}</p>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <button type="button"
                                @click="chooseMethod('bank_statement')"
                                class="rounded-3xl ring-2 px-6 py-7 text-left transition shadow-sm"
                                :class="incomeMethod === 'bank_statement' ? 'ring-brand bg-brand-muted/40' : 'ring-gray-200 bg-gradient-to-br from-white to-gray-50 hover:ring-brand/40'">
                            <span class="text-3xl" aria-hidden="true">🏦</span>
                            <p class="text-base font-bold text-gray-900 mt-3">{{ __('borrower.profile.income_method_bank_title') }}</p>
                            <p class="text-sm text-gray-500 mt-1.5 leading-relaxed">{{ __('borrower.profile.income_method_bank_hint') }}</p>
                            @if (in_array('bank_statement', $presentMethods, true))
                                <p class="text-xs font-semibold text-emerald-700 mt-3">{{ __('borrower.profile.income_already_on_file') }}</p>
                            @endif
                        </button>
                        <button type="button"
                                @click="chooseMethod('mobile_money_statement')"
                                class="rounded-3xl ring-2 px-6 py-7 text-left transition shadow-sm"
                                :class="incomeMethod === 'mobile_money_statement' ? 'ring-brand bg-brand-muted/40' : 'ring-gray-200 bg-gradient-to-br from-white to-gray-50 hover:ring-brand/40'">
                            <span class="text-3xl" aria-hidden="true">📱</span>
                            <p class="text-base font-bold text-gray-900 mt-3">{{ __('borrower.profile.income_method_mobile_title') }}</p>
                            <p class="text-sm text-gray-500 mt-1.5 leading-relaxed">{{ __('borrower.profile.income_method_mobile_hint') }}</p>
                            @if (in_array('mobile_money_statement', $presentMethods, true))
                                <p class="text-xs font-semibold text-emerald-700 mt-3">{{ __('borrower.profile.income_already_on_file') }}</p>
                            @endif
                        </button>
                    </div>
                    <input type="hidden" name="income_proof_method" :value="incomeMethod">
                    @error('income_proof_method')
                        <p class="text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Step 2: account details (labels follow chosen method) --}}
                <div x-show="step === 2" x-cloak class="space-y-4" data-income-step="2">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-gray-900"
                           x-text="incomeMethod === 'bank_statement' ? @js(__('borrower.profile.income_bank_section')) : @js(__('borrower.profile.income_mobile_section'))"></p>
                        <button type="button" @click="step = 1; incomeMethod = ''" class="text-xs font-semibold text-gray-600 hover:text-gray-900">
                            {{ __('borrower.profile.income_change_method_short') }}
                        </button>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-semibold text-gray-900 mb-1.5">
                                <span x-text="incomeMethod === 'bank_statement' ? @js(__('borrower.profile.income_bank_name')) : @js(__('borrower.profile.income_mobile_provider'))"></span>
                                <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="income_account_provider"
                                   x-model="provider"
                                   class="kf-field"
                                   x-bind:required="step === 2"
                                   :placeholder="incomeMethod === 'bank_statement' ? @js(__('borrower.profile.income_bank_placeholder')) : @js(__('borrower.profile.income_momo_placeholder'))">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-900 mb-1.5">
                                <span x-text="incomeMethod === 'bank_statement' ? @js(__('borrower.profile.income_bank_account_number')) : @js(__('borrower.profile.income_mobile_number'))"></span>
                                <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="income_account_number"
                                   x-model="number"
                                   class="kf-field"
                                   x-bind:required="step === 2"
                                   :placeholder="incomeMethod === 'bank_statement' ? @js(__('borrower.profile.income_bank_account_placeholder')) : @js(__('borrower.profile.income_mobile_number_placeholder'))">
                        </div>
                        <div class="sm:col-span-2 rounded-xl bg-gray-50 ring-1 ring-gray-200 px-4 py-3">
                            <p class="text-xs text-gray-500">{{ __('borrower.profile.income_account_name') }}</p>
                            <p class="text-sm font-semibold text-gray-900 mt-0.5">{{ $customer->legalDisplayName() }}</p>
                            <input type="hidden" name="income_account_name" value="{{ old('income_account_name', $customer->legalDisplayName()) }}">
                        </div>
                    </div>
                </div>

                {{-- Step 3: always start fresh (no prior PDF) so Save appears after upload --}}
                <div x-show="step === 3" x-cloak class="space-y-4">
                    <p class="text-sm font-semibold text-gray-900">{{ __('borrower.profile.income_step_scan_title') }}</p>
                    <p class="text-xs text-gray-500">{{ __('borrower.profile.income_replace_fresh_hint') }}</p>
                    @foreach ($primaryItems as $item)
                        <div class="rounded-xl border border-gray-100 p-4 bg-white space-y-3" x-show="incomeMethod === @js($item['key'])" x-cloak>
                            <p class="text-sm font-semibold text-gray-900">
                                {{ $item['label'] }}
                                <span class="text-red-500">*</span>
                            </p>
                            <x-site.profile-document-field
                                :document="null"
                                :field-name="$item['key']"
                                :pages-field-name="$item['key'].'_pages'"
                                :mode="($item['multi'] ?? false) ? 'multi' : 'single'"
                                :label="$item['label']"
                                :input-host-id="$item['key'].'-statement-upload'"
                                :required="true"
                                :labels="[
                                    'hint' => __('borrower.profile.multi_page_hint_short'),
                                    'uploadFile' => __('borrower.profile.upload'),
                                    'capturePage' => __('borrower.profile.use_camera_optional'),
                                ]"
                            />
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="mt-6 flex flex-wrap items-center gap-3">
                <button type="button" x-show="step > 1" x-cloak @click="prev()"
                        class="inline-flex items-center px-5 py-2.5 rounded-full text-sm font-semibold text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50">
                    {{ __('borrower.profile.back') }}
                </button>
                <button type="button"
                        x-show="step < maxStep() && (employed || (step > 1 ? ready : !!incomeMethod))" x-cloak
                        @click="next()"
                        class="inline-flex items-center bg-brand hover:bg-brand-light text-white font-semibold px-5 py-2.5 rounded-full text-sm">
                    {{ __('borrower.profile.continue') }}
                </button>
                <button type="submit" x-show="step >= maxStep() && ready" x-cloak
                        class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
                    {{ __('borrower.profile.save_documents') }}
                </button>
            </div>

            <x-site.upload-busy-overlay />
        </form>
    </x-slot:form>
</x-site.profile-section-card>
