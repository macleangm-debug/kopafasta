<x-admin.layout title="Signatories & Company Seal" heading="Signatories & Company Seal" subheading="Who authorizes our documents — signatories and company stamp">
    @include('admin.settings._tabs', ['active' => 'signatories'])

    @php
        $stampPath = $stampPath ?? app(\App\Services\LegalSettingsService::class)->get('stamp_path');
    @endphp

    <div class="mb-5 rounded-2xl bg-white ring-1 ring-brand/15 p-5"
         x-data="{
            replacing: false,
            preview: null,
            removeBg: true,
         }">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-brand font-bold">Company stamp / seal</p>
                <p class="text-sm text-gray-600 mt-1">Canonical stamp for Offer, Decision, and Contract PDFs. Previously issued documents keep their historical snapshot.</p>
            </div>
            <button type="button"
                    @click="replacing = !replacing; if (!replacing) { preview = null; confirmReplace = false; }"
                    class="inline-flex text-sm font-bold rounded-lg bg-brand-gold text-brand px-4 py-2 hover:brightness-95">
                <span x-text="replacing ? 'Cancel' : 'Replace stamp'"></span>
            </button>
        </div>

        <div class="mt-4" x-show="!replacing">
            @if ($stampPath)
                <img src="{{ asset('storage/'.$stampPath) }}" alt="Company stamp" class="h-24 w-24 object-contain bg-transparent">
            @else
                <p class="text-sm text-amber-800 bg-amber-50 ring-1 ring-amber-100 rounded-lg px-3 py-2">No company stamp on file yet.</p>
            @endif
        </div>

        <form method="POST" action="{{ route('admin.settings.signatories.stamp') }}" enctype="multipart/form-data"
              class="mt-4 space-y-4" x-show="replacing" x-cloak
              @submit.prevent="window.confirmForm($el, {
                  title: 'Replace company stamp?',
                  message: 'The new stamp will be used for future documents. Previously issued documents keep their historical snapshot.',
                  confirmLabel: 'Replace stamp',
                  confirmClass: 'bg-brand hover:bg-brand-light text-white',
                  tone: 'confirm',
              })">
            @csrf
            <input type="hidden" name="confirm_replace_stamp" value="1">

            <div>
                <x-admin.styled-file-upload
                    name="stamp_image"
                    label="Upload stamp"
                    remove-background-name="remove_stamp_background"
                    :remove-background-default="true"
                    :required="true"
                />
            </div>

            <template x-if="true">
                <div class="pt-1" x-show="true">
                    <p class="text-xs text-gray-500 mb-3">Review the preview above, then confirm. Future documents will use this stamp; previously issued documents keep their historical snapshot.</p>
                    <button type="submit" class="inline-flex rounded-lg bg-brand text-white text-sm font-semibold px-4 py-2 hover:bg-brand-light">
                        Review & replace
                    </button>
                </div>
            </template>
        </form>
    </div>

    <div class="mb-4 flex justify-end">
        <a href="{{ route('admin.settings.signatories.create') }}"
           class="inline-flex bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-4 py-2 rounded-lg">
            + Add signatory
        </a>
    </div>

    <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-5 py-3">Name</th>
                    <th class="px-5 py-3">Type</th>
                    <th class="px-5 py-3">Position</th>
                    <th class="px-5 py-3">Email</th>
                    <th class="px-5 py-3">Signature</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($signatories as $signatory)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 font-medium">{{ $signatory->name }}</td>
                        <td class="px-5 py-3 text-gray-600">{{ ucfirst(str_replace('_', ' ', $signatory->signatory_type ?? 'company')) }}</td>
                        <td class="px-5 py-3 text-gray-600">{{ $signatory->position ?? '—' }}</td>
                        <td class="px-5 py-3 text-gray-600">{{ $signatory->email ?? '—' }}</td>
                        <td class="px-5 py-3">
                            @if ($signatory->signature_path)
                                <img src="{{ $signatory->signaturePublicUrl() }}" alt=""
                                     class="h-10 max-w-[140px] object-contain bg-transparent">
                            @else
                                <a href="{{ route('admin.settings.signatories.edit', $signatory) }}" class="text-xs font-semibold text-brand hover:underline">Add signature</a>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium {{ $signatory->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600' }}">
                                {{ $signatory->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-right space-x-2">
                            <a href="{{ route('admin.settings.signatories.edit', $signatory) }}" class="text-brand hover:underline text-xs">Edit</a>
                            <form method="POST" action="{{ route('admin.settings.signatories.destroy', $signatory) }}" class="inline"
                                  @submit.prevent="window.confirmForm($el, {
                                      title: @js('Delete signatory?'),
                                      message: @js('This will remove '.$signatory->name.' from available signatories. Existing issued documents must remain unchanged.'),
                                      confirmLabel: @js('Delete signatory'),
                                      confirmClass: 'bg-red-600 hover:bg-red-700 text-white',
                                      tone: 'warning',
                                  })">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline text-xs">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-10 text-center text-gray-500">No signatories configured yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin.layout>
