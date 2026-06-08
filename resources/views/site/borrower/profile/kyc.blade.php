<x-site.borrower-layout :title="brand_title(__('borrower.profile.title'))" active="profile">

    <div class="max-w-3xl">
        @include('site.borrower.profile._heading', [
            'title' => __('borrower.profile.title'),
            'subtitle' => __('borrower.profile.kyc_subtitle'),
        ])

        @include('site.borrower.profile._tabs', ['active' => 'kyc'])
        @include('site.borrower.profile._kyc_progress', ['customer' => $customer, 'active' => 'kyc'])
        @include('site.borrower.profile._completion')

        @if (session('status'))
            <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('site.borrower.profile.update', ['section' => 'kyc']) }}{{ ! empty($returnUrl) ? '?return='.urlencode($returnUrl) : '' }}"
              enctype="multipart/form-data" class="bg-white rounded-2xl border border-gray-200 p-6 mb-6">
            @csrf @method('PUT')
            @if (! empty($returnUrl))
                <input type="hidden" name="return" value="{{ $returnUrl }}">
            @endif

            <h2 class="font-semibold mb-1">{{ __('borrower.profile.proof_of_income_title') }}</h2>
            <p class="text-sm text-gray-600 mb-4">{{ __('borrower.profile.proof_of_income_hint') }}</p>

            <div class="space-y-5">
                @foreach ($incomeProofChecklist ?? [] as $item)
                    <div class="rounded-xl border border-gray-100 p-4">
                        <div class="flex items-start justify-between gap-3 mb-2">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">
                                    {{ $item['label'] }}
                                    @if ($item['required'])
                                        <span class="text-red-500">*</span>
                                    @else
                                        <span class="text-xs font-normal text-gray-400">{{ __('borrower.profile.optional') }}</span>
                                    @endif
                                </p>
                                @if (($item['key'] ?? '') === 'bank_statement' || ($item['key'] ?? '') === 'mobile_money_statement')
                                    <p class="text-xs text-gray-500 mt-0.5">{{ __('borrower.profile.income_primary_hint') }}</p>
                                @endif
                            </div>
                            @if ($item['complete'] ?? false)
                                <span class="text-xs font-semibold rounded-full px-2.5 py-1 bg-emerald-100 text-emerald-700">{{ __('borrower.profile.document_on_file') }}</span>
                            @endif
                        </div>

                        @if (! empty($item['document']?->file_path))
                            <p class="text-xs text-gray-500 mb-3">
                                <a href="{{ asset('storage/'.$item['document']->file_path) }}" target="_blank" class="text-amber-600 hover:underline">
                                    {{ __('borrower.profile.view_document') }}
                                </a>
                            </p>
                        @endif

                        <input type="file" name="{{ $item['key'] }}" accept=".jpg,.jpeg,.png,.pdf"
                               class="w-full text-sm file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-amber-50 file:text-amber-800 file:font-semibold">
                        @error($item['key'])<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                @endforeach
            </div>

            <p class="text-xs text-gray-500 mt-4">{{ __('borrower.profile.income_validation_hint') }}</p>

            <button class="mt-6 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
                {{ __('borrower.profile.save_documents') }}
            </button>
        </form>

        <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <h2 class="font-semibold mb-2">{{ __('borrower.kyc_tab.documents_title') }}</h2>
            <p class="text-sm text-gray-600 mb-4">{{ __('borrower.kyc_tab.documents_hint') }}</p>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('site.borrower.kyc') }}" class="inline-flex items-center bg-white ring-1 ring-gray-200 hover:bg-gray-50 text-gray-700 font-semibold px-5 py-2.5 rounded-full text-sm">
                    {{ __('borrower.kyc_tab.manage_documents') }}
                </a>
                <a href="{{ route('site.borrower.face-verification') }}" class="inline-flex items-center bg-white ring-1 ring-gray-200 hover:bg-gray-50 text-gray-700 font-semibold px-5 py-2.5 rounded-full text-sm">
                    {{ __('borrower.kyc_tab.face_verification') }}
                </a>
            </div>
        </div>
    </div>

</x-site.borrower-layout>
