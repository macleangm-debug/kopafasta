<x-admin.edit-page :title="'Edit '.$record->title" :heading="$record->title" subheading="Update marketplace asset"
    :action="route('admin.marketplace-assets.update', $record)" :destroyAction="route('admin.marketplace-assets.destroy', $record)" :cancelUrl="route('admin.marketplace-assets.show', $record)" submitLabel="Save changes"
    enctype="multipart/form-data">
    @if (! empty($record->photos))
        <div class="mb-6 rounded-xl bg-slate-50 ring-1 ring-slate-200 p-4">
            <div class="flex items-center justify-between gap-3 mb-3">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-600">Current photos ({{ count($record->photos) }})</p>
                <p class="text-xs text-slate-500">Manage or add more on the Photos step</p>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                @foreach ($record->photos as $index => $photo)
                    <div class="relative rounded-lg overflow-hidden ring-1 ring-gray-200 bg-white aspect-square">
                        <img src="{{ marketplace_photo_url($photo) }}" alt="Photo {{ $index + 1 }}"
                             class="w-full h-full object-cover" loading="lazy" referrerpolicy="no-referrer">
                        @if ($index === 0)
                            <span class="absolute top-2 left-2 rounded-full bg-amber-500 text-gray-900 text-[10px] font-semibold px-2 py-0.5">Cover</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif
    @include('admin.marketplace-assets._form', ['record' => $record])
</x-admin.edit-page>
