<x-site.borrower-layout :title="brand_title(__('borrower.documents_page.title'))" active="documents" content-width="wide">

    <div class="mb-8 glass-card p-6">
        <h2 class="font-semibold text-gray-900">{{ __('borrower.documents_page.verification_title') }}</h2>
        <p class="text-sm text-gray-500 mt-1 mb-4">{{ __('borrower.documents_page.verification_hint') }}</p>
        <div class="grid sm:grid-cols-2 gap-3">
            @foreach ($verificationSections as $section)
                @php
                    $tone = match ($section['status']) {
                        'complete' => 'bg-emerald-50 ring-emerald-200 text-emerald-800',
                        'action_required', 'stale', 'missing' => 'bg-amber-50 ring-amber-200 text-amber-900',
                        default => 'bg-gray-50 ring-gray-200 text-gray-700',
                    };
                    $statusLabel = match ($section['status']) {
                        'complete' => __('borrower.documents_page.status_complete'),
                        'action_required', 'stale', 'missing' => __('borrower.documents_page.status_action_required'),
                        default => __('borrower.documents_page.status_pending'),
                    };
                @endphp
                <div class="rounded-xl ring-1 px-4 py-3 flex items-center justify-between gap-3 {{ $tone }}">
                    <div>
                        <p class="text-sm font-semibold">{{ $section['label'] }}</p>
                        <p class="text-xs mt-0.5">{{ $statusLabel }}</p>
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

    <div class="space-y-4 mb-8">
        <div>
            <h2 class="font-semibold text-gray-900">{{ __('borrower.documents_page.uploaded_title') }}</h2>
            <p class="text-xs text-gray-500 mt-0.5">{{ __('borrower.documents_page.uploaded_count', ['count' => $documents->count()]) }}</p>
        </div>

        @php
            $docService = app(\App\Services\ProfileDocumentService::class);
        @endphp
        @forelse ($types as $type)
            @php
                $typeDocs = $documents->where('document_type_id', $type->id)
                    ->filter(fn ($doc) => ! in_array($doc->status, ['replaced', 'archived'], true))
                    ->values();
                $latest = $typeDocs->first();
                $hasUpload = $typeDocs->isNotEmpty();
                $code = (string) ($type->code ?? '');
                $needsUpdate = $latest ? $docService->isExpired($latest) : false;
            @endphp
            <x-site.profile-section-card
                :section-id="'doc-type-'.$type->id"
                :title="$type->localizedName()"
                :complete="$hasUpload && in_array($latest?->status, ['verified', 'approved', 'pending', 'pending_review'], true) && ! $needsUpdate"
                :stale="$needsUpdate"
                :empty="! $hasUpload"
                :add-label="__('borrower.documents_page.add_document')"
                :default-open="$errors->has('file') && (int) old('document_type_id') === (int) $type->id"
                :default-edit="$errors->has('file') && (int) old('document_type_id') === (int) $type->id">
                <x-slot:view>
                    @if ($hasUpload)
                        <div class="space-y-3">
                            @foreach ($typeDocs as $doc)
                                <x-site.profile-document-field
                                    :document="$doc"
                                    :field-name="'document_'.$doc->id"
                                    mode="single"
                                    :label="$type->localizedName()"
                                    :input-host-id="'doc-holder-'.$doc->id"
                                    :document-code="$code ?: null"
                                    :read-only="true"
                                    :replace-opens-edit="true"
                                    :allow-remove="true"
                                />
                            @endforeach
                            @if ($needsUpdate)
                                <p class="text-xs font-bold text-amber-900">{{ __('borrower.documents_page.status_expired') }}</p>
                            @endif
                        </div>
                    @else
                        <p class="text-sm text-gray-600">{{ __('borrower.documents_page.empty_type') }}</p>
                        <button type="button" @click="open = true"
                                class="mt-3 inline-flex items-center justify-center rounded-xl bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-4 py-2.5 text-sm shadow-sm">
                            {{ __('borrower.documents_page.add_document') }}
                        </button>
                    @endif
                </x-slot:view>
                <x-slot:form>
                    <p class="text-xs text-gray-500 mb-4">{{ __('borrower.documents_page.general_upload_hint') }}</p>
                    @error('file')
                        <p class="mb-3 text-sm text-red-800 bg-red-50 ring-1 ring-red-200 rounded-lg px-3 py-2" role="alert">{{ $message }}</p>
                    @enderror
                    @error('expires_at')
                        <p class="mb-3 text-sm text-red-800 bg-red-50 ring-1 ring-red-200 rounded-lg px-3 py-2" role="alert">{{ $message }}</p>
                    @enderror
                    <x-site.document-upload :action="route('site.borrower.documents.store')" :multiple="false">
                        <input type="hidden" name="document_type_id" value="{{ $type->id }}">
                        @if ($type->expires)
                            <div class="mb-3">
                                <x-site.date-input
                                    name="expires_at"
                                    :label="__('borrower.profile.expiry_date')"
                                    :value="old('expires_at')"
                                    :required="true"
                                    :min="now()->toDateString()"
                                    :max="now()->addYears(20)->format('Y-m-d')"
                                    :default="now()->addYear()->format('Y-m-d')"
                                    input-class="kf-field max-w-xs inline-flex items-center justify-between gap-3 text-left"
                                />
                            </div>
                        @endif
                    </x-site.document-upload>
                </x-slot:form>
            </x-site.profile-section-card>
        @empty
            <div class="glass-card p-10 text-center text-sm text-gray-500">{{ __('borrower.documents_page.empty_general') }}</div>
        @endforelse
    </div>

</x-site.borrower-layout>
