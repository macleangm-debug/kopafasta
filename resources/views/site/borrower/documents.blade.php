<x-site.borrower-layout :title="brand_title(__('borrower.documents_page.title'))" active="documents" content-width="wide">

    <h1 class="text-2xl font-bold mb-1">{{ __('borrower.documents_page.title') }}</h1>
    <p class="text-sm text-gray-500 mb-6">{{ __('borrower.documents_page.subtitle') }}</p>



    <div class="mb-8 bg-white rounded-2xl ring-1 ring-gray-200 p-6">
        <h2 class="font-semibold text-gray-900">{{ __('borrower.documents_page.verification_title') }}</h2>
        <p class="text-sm text-gray-500 mt-1 mb-4">{{ __('borrower.documents_page.verification_hint') }}</p>
        <div class="grid sm:grid-cols-2 gap-3">
            @foreach ($verificationSections as $section)
                @php
                    $tone = match ($section['status']) {
                        'complete' => 'bg-emerald-50 ring-emerald-200 text-emerald-800',
                        'action_required' => 'bg-amber-50 ring-amber-200 text-amber-900',
                        default => 'bg-gray-50 ring-gray-200 text-gray-700',
                    };
                @endphp
                <div class="rounded-xl ring-1 px-4 py-3 flex items-center justify-between gap-3 {{ $tone }}">
                    <div>
                        <p class="text-sm font-semibold">{{ $section['label'] }}</p>
                        <p class="text-xs mt-0.5 capitalize">{{ match ($section['status']) {
                            'complete' => __('borrower.documents_page.status_complete'),
                            'action_required' => __('borrower.documents_page.status_action_required'),
                            default => __('borrower.documents_page.status_pending'),
                        } }}</p>
                    </div>
                    @if (! empty($section['action_url']))
                        <a href="{{ $section['action_url'] }}" class="text-xs font-semibold shrink-0 hover:underline">
                            {{ __('borrower.documents_page.view_profile') }}
                        </a>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1 bg-white rounded-2xl border border-gray-200 p-6">
            <h2 class="font-semibold mb-1">{{ __('borrower.documents_page.general_upload_title') }}</h2>
            <p class="text-xs text-gray-500 mb-4">{{ __('borrower.documents_page.general_upload_hint') }}</p>

            <x-site.document-upload :action="route('site.borrower.documents.store')" :multiple="false">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.documents_page.document_type') }}</label>
                <select name="document_type_id" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2.5 text-sm mb-4">
                    @foreach ($types as $t)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endforeach
                </select>
            </x-site.document-upload>
        </div>

        <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200">
            <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="font-semibold">{{ __('borrower.documents_page.uploaded_title') }}</h2>
                <span class="text-xs text-gray-500">{{ __('borrower.documents_page.uploaded_count', ['count' => $documents->count()]) }}</span>
            </div>
            @if ($documents->isEmpty())
                <div class="p-10 text-center text-sm text-gray-500">{{ __('borrower.documents_page.empty_general') }}</div>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach ($documents as $doc)
                        @php
                            $color = match ($doc->status) {
                                'verified','approved' => 'bg-emerald-100 text-emerald-700',
                                'rejected'            => 'bg-red-100 text-red-700',
                                default               => 'bg-amber-100 text-amber-700',
                            };
                        @endphp
                        <li class="px-5 py-4 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-medium text-sm">{{ $doc->documentType->name ?? 'Document' }}</p>
                                <p class="text-xs text-gray-500 truncate">Uploaded {{ \Carbon\Carbon::parse($doc->created_at)->format('d M Y') }}</p>
                            </div>
                            <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $color }}">{{ ucfirst($doc->status) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

</x-site.borrower-layout>
