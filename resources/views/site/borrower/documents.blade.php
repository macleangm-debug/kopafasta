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
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-gray-900">{{ __('borrower.documents_page.uploaded_title') }}</h2>
                <p class="text-xs text-gray-500 mt-0.5">{{ __('borrower.documents_page.uploaded_count', ['count' => $documents->count()]) }}</p>
            </div>
        </div>

        @php
            $documentsStale = in_array('documents', app(\App\Services\KycFreshnessService::class)->sectionsDueForRefresh($customer), true);
            $docService = app(\App\Services\ProfileDocumentService::class);
            $expirableCodes = ['passport', 'driving_license', 'voter_id'];
        @endphp
        @forelse ($types as $type)
            @php
                $typeDocs = $documents->where('document_type_id', $type->id)->values();
                $latest = $typeDocs->first();
                $hasUpload = $typeDocs->isNotEmpty();
                $code = (string) ($type->code ?? '');
                $meta = $latest ? $docService->metadata($latest) : [];
                $expiresRaw = $meta['expires_at'] ?? $meta['expires_on'] ?? $meta['valid_until'] ?? null;
                $expiresAt = filled($expiresRaw) ? \Illuminate\Support\Carbon::parse($expiresRaw) : null;
                $isExpired = $expiresAt && $expiresAt->isPast();
                $needsUpdate = $isExpired || ($documentsStale && in_array($code, $expirableCodes, true));
            @endphp
            <x-site.profile-section-card
                :section-id="'doc-type-'.$type->id"
                :title="$type->localizedName()"
                :complete="$hasUpload && in_array($latest?->status, ['verified', 'approved'], true) && ! $needsUpdate"
                :stale="$needsUpdate"
                :empty="! $hasUpload"
                :add-label="__('borrower.documents_page.upload_button')"
                :default-open="false">
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
                                />
                            @endforeach
                            @if ($needsUpdate)
                                <p class="text-xs font-bold text-amber-900">{{ __('borrower.documents_page.status_expired') }}</p>
                            @endif
                            <button type="button" @click="open = true"
                                    class="inline-flex items-center justify-center rounded-xl bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-4 py-2.5 text-sm shadow-sm">
                                {{ __('borrower.documents_page.replace') }}
                            </button>
                        </div>
                    @else
                        <p class="text-sm text-gray-600">{{ __('borrower.documents_page.empty_type') }}</p>
                        <button type="button" @click="open = true"
                                class="mt-3 inline-flex items-center justify-center rounded-xl bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-4 py-2.5 text-sm shadow-sm">
                            {{ __('borrower.documents_page.upload_button') }}
                        </button>
                    @endif
                </x-slot:view>
                <x-slot:form>
                    <p class="text-xs text-gray-500 mb-4">{{ __('borrower.documents_page.general_upload_hint') }}</p>
                    @error('file')
                        <p class="mb-3 text-sm text-red-800 bg-red-50 ring-1 ring-red-200 rounded-lg px-3 py-2" role="alert">{{ $message }}</p>
                    @enderror
                    @error('document_type_id')
                        <p class="mb-3 text-sm text-red-800 bg-red-50 ring-1 ring-red-200 rounded-lg px-3 py-2" role="alert">{{ $message }}</p>
                    @enderror
                    <x-site.document-upload :action="route('site.borrower.documents.store')" :multiple="false">
                        <input type="hidden" name="document_type_id" value="{{ $type->id }}">
                    </x-site.document-upload>
                </x-slot:form>
            </x-site.profile-section-card>
        @empty
            <div class="glass-card p-10 text-center text-sm text-gray-500">{{ __('borrower.documents_page.empty_general') }}</div>
        @endforelse
    </div>

</x-site.borrower-layout>
