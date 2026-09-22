<x-admin.layout title="Document Templates" heading="Document Templates" subheading="What official documents do we issue?">
    @include('admin.settings._tabs', ['active' => 'document-templates'])

    <div class="mb-5 grid sm:grid-cols-3 gap-3">
        <a href="{{ route('admin.settings.signatories.index') }}" class="rounded-xl bg-white ring-1 ring-brand/15 px-4 py-3 hover:bg-brand-muted/30">
            <p class="text-[10px] uppercase tracking-widest text-brand font-bold">1 · Who authorizes</p>
            <p class="text-sm font-semibold text-gray-900 mt-1">Signatories & Company Seal</p>
        </a>
        <div class="rounded-xl bg-brand-muted/40 ring-1 ring-brand/20 px-4 py-3">
            <p class="text-[10px] uppercase tracking-widest text-brand font-bold">2 · What we issue</p>
            <p class="text-sm font-semibold text-gray-900 mt-1">You are here — Document Templates</p>
        </div>
        <a href="{{ route('admin.settings.legal') }}" class="rounded-xl bg-white ring-1 ring-brand/15 px-4 py-3 hover:bg-brand-muted/30">
            <p class="text-[10px] uppercase tracking-widest text-brand font-bold">3 · Contract makeup</p>
            <p class="text-sm font-semibold text-gray-900 mt-1">Contracts → Sections → Clauses</p>
        </a>
    </div>

    <div class="mb-6 grid md:grid-cols-3 gap-4">
        @foreach ($runtimeDocuments as $card)
            <div class="rounded-2xl bg-white ring-1 ring-brand/15 p-5 flex flex-col">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-bold">{{ $card['status'] }}</p>
                    <span class="text-[10px] font-semibold text-gray-400">{{ $card['languages'] }}</span>
                </div>
                <h3 class="text-base font-bold text-gray-900 mt-1">{{ $card['title'] }}</h3>
                <p class="text-sm text-gray-600 mt-2 flex-1">{{ $card['detail'] }}</p>
                <dl class="mt-3 space-y-1 text-xs text-gray-500">
                    <div><span class="font-semibold text-gray-700">Signatory:</span> {{ $card['signatory'] }}@if($signatoryName) · {{ $signatoryName }}@endif</div>
                    <div><span class="font-semibold text-gray-700">Seal/stamp:</span> {{ $hasStamp ? 'Configured' : 'Missing' }} · {{ $card['stamp'] }}</div>
                </dl>
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <div class="inline-flex rounded-lg ring-1 ring-gray-200 overflow-hidden text-[11px] font-semibold">
                        <button type="button"
                                onclick="window.kfOpenDocumentPreview(@js(route('admin.document-templates.preview', ['type' => $card['key'], 'lang' => 'sw'])), @js($card['title'].' · Kiswahili'), 'pdf')"
                                class="px-2.5 py-2 bg-white text-gray-700 hover:bg-gray-50">Kiswahili</button>
                        <button type="button"
                                onclick="window.kfOpenDocumentPreview(@js(route('admin.document-templates.preview', ['type' => $card['key'], 'lang' => 'en'])), @js($card['title'].' · English'), 'pdf')"
                                class="px-2.5 py-2 bg-white text-gray-700 hover:bg-gray-50 border-l border-gray-200">English</button>
                    </div>
                    <button type="button"
                            onclick="window.kfOpenDocumentPreview(@js(route('admin.document-templates.preview', $card['key'])), @js($card['title']), 'pdf')"
                            class="inline-flex rounded-lg bg-brand text-white text-xs font-semibold px-3 py-2 hover:bg-brand-light">
                        Preview
                    </button>
                    <a href="{{ $card['configure'] }}"
                       class="inline-flex rounded-lg bg-white ring-1 ring-gray-200 text-xs font-semibold px-3 py-2 text-gray-800 hover:bg-gray-50">
                        Configure
                    </a>
                </div>
            </div>
        @endforeach
    </div>

    <div id="offer-config" class="mb-8 rounded-2xl bg-white ring-1 ring-brand/15 p-5 scroll-mt-24">
        <p class="text-[10px] uppercase tracking-widest text-brand font-bold">Offer Letter · Configure</p>
        <h3 class="text-sm font-bold text-gray-900 mt-1">Offer validity</h3>
        <p class="text-xs text-gray-500 mt-1 mb-4">Canonical setting for how many days an Offer Letter remains valid. Not duplicated under Contracts & Clauses.</p>
        <form method="POST" action="{{ route('admin.document-templates.offer-validity') }}" class="flex flex-wrap items-end gap-3"
              @submit="if (typeof window.kfShowInlineSaving === 'function') { window.kfShowInlineSaving('Saving…') }">
            @csrf
            @method('PUT')
            <div class="w-40">
                <x-admin.input name="offer_validity_days" label="Validity (days)" type="number" min="1" max="90"
                               :value="old('offer_validity_days', $offerValidityDays)" required />
            </div>
            <button type="submit" class="inline-flex rounded-lg bg-brand-gold text-brand text-sm font-semibold px-4 py-2 hover:brightness-95">
                Save validity
            </button>
        </form>
    </div>

    <div class="rounded-xl bg-slate-50 ring-1 ring-slate-200 px-4 py-3 text-sm text-slate-700">
        <p class="font-semibold">Add template</p>
        <p class="mt-1 text-xs text-slate-600">
            Runtime documents above are generated by the platform engine (Offer, Decision, Loan Contract).
            Arbitrary new document types are <span class="font-semibold">not</span> supported yet — so there is no misleading Add Template button.
            Edit contract content under <a href="{{ route('admin.settings.legal') }}" class="text-brand font-semibold hover:underline">Contracts & Clauses</a>.
        </p>
    </div>
</x-admin.layout>
