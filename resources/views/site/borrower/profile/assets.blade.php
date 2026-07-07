<x-site.borrower-layout :title="brand_title(__('borrower.profile.my_assets'))" active="profile" content-width="wide">

    @php
        $adding = request()->boolean('add') || filled(old('asset_type'));
        $selectedType = old('asset_type', request('type'));
        $typeIcons = ['vehicle' => '🚗', 'house' => '🏠', 'land' => '🌍', 'equipment' => '⚙️'];
    @endphp

    <div>
        @include('site.borrower.profile._profile_shell', [
            'title' => __('borrower.profile.my_assets'),
            'subtitle' => __('borrower.profile.my_assets_hint'),
            'customer' => $customer,
            'active' => 'assets',
        ])

        @if (session('status'))
            <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        @if ($adding && $selectedType)
            <x-site.profile-section-card :title="__('borrower.profile.add_asset').': '.($assetTypes[$selectedType] ?? $selectedType)">
                <form method="POST" action="{{ route('site.borrower.profile.assets.store') }}" enctype="multipart/form-data" class="space-y-5">
                    @csrf
                    <input type="hidden" name="asset_type" value="{{ $selectedType }}">
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.asset_label') }}</label>
                            <input type="text" name="label" value="{{ old('label') }}" required maxlength="150"
                                   class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.registration_number') }}</label>
                            <input type="text" name="registration_number" value="{{ old('registration_number') }}" maxlength="80"
                                   class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.description') }}</label>
                            <textarea name="description" rows="2" class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm">{{ old('description') }}</textarea>
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4 pt-2 border-t border-gray-100">
                        @php
                            $photoSlots = [
                                ['key' => 0, 'icon' => '⬅️', 'label' => __('borrower.profile.asset_photo_front')],
                                ['key' => 1, 'icon' => '➡️', 'label' => __('borrower.profile.asset_photo_back')],
                                ['key' => 2, 'icon' => '↗️', 'label' => __('borrower.profile.asset_photo_side')],
                                ['key' => 3, 'icon' => '↘️', 'label' => __('borrower.profile.asset_photo_angle')],
                            ];
                        @endphp
                        @foreach ($photoSlots as $slot)
                            <div class="rounded-xl ring-1 ring-gray-200 p-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <span aria-hidden="true">{{ $slot['icon'] }}</span>
                                    <label class="text-xs font-semibold text-gray-700">{{ $slot['label'] }}</label>
                                </div>
                                <input type="file" name="photos[]" accept="image/*" capture="environment"
                                       class="w-full text-xs file:mr-2 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-brand-muted file:text-brand file:font-semibold"
                                       @if ($slot['key'] < 2) required @endif>
                            </div>
                        @endforeach
                        <div class="rounded-xl ring-1 ring-gray-200 p-4">
                            <div class="flex items-center gap-2 mb-2">
                                <span aria-hidden="true">🤳</span>
                                <label class="text-xs font-semibold text-gray-700">{{ __('borrower.profile.person_with_asset') }}</label>
                            </div>
                            <input type="file" name="person_photo" accept="image/*" capture="user" required
                                   class="w-full text-xs file:mr-2 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-brand-muted file:text-brand file:font-semibold">
                        </div>
                        <div class="rounded-xl ring-1 ring-gray-200 p-4 sm:col-span-2">
                            <div class="flex items-center gap-2 mb-2">
                                <span aria-hidden="true">📄</span>
                                <label class="text-xs font-semibold text-gray-700">{{ __('borrower.profile.ownership_document') }}</label>
                            </div>
                            <input type="file" name="ownership_document" accept="image/*,application/pdf" required
                                   class="w-full text-xs file:mr-2 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-brand-muted file:text-brand file:font-semibold">
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button type="submit" class="bg-brand hover:bg-brand-light text-white font-semibold px-6 py-2.5 rounded-xl text-sm">{{ __('borrower.profile.save_asset') }}</button>
                        <a href="{{ route('site.borrower.profile', ['section' => 'assets']) }}" class="inline-flex items-center px-5 py-2.5 rounded-xl text-sm font-semibold text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50">{{ __('borrower.profile.cancel') }}</a>
                    </div>
                </form>
            </x-site.profile-section-card>
        @elseif (! $adding)
            @if ($assets->isNotEmpty())
                <div class="space-y-4 mb-8">
                    @foreach ($assets as $asset)
                        @php $meta = $asset->metadata ?? []; @endphp
                        <x-site.profile-section-card :title="$asset->label" :complete="true">
                            <p class="text-xs uppercase tracking-widest text-gray-500 mb-2">{{ $assetTypes[$asset->asset_type] ?? $asset->asset_type }}</p>
                            @if ($asset->registration_number)
                                <p class="text-sm font-mono text-gray-700">{{ $asset->registration_number }}</p>
                            @endif
                            @if ($asset->estimated_value)
                                <p class="text-sm text-gray-600 mt-1">{{ format_money($asset->estimated_value) }}</p>
                            @endif
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-4">
                                @foreach ($asset->photo_paths ?? [] as $i => $path)
                                    <div>
                                        <p class="text-[10px] text-gray-500 mb-1">{{ __('borrower.profile.asset_photo') }} {{ $i + 1 }}</p>
                                        <img src="{{ asset('storage/'.$path) }}" alt="" class="w-full h-20 object-cover rounded-lg ring-1 ring-gray-100">
                                    </div>
                                @endforeach
                                @if ($meta['person_with_asset_path'] ?? null)
                                    <div>
                                        <p class="text-[10px] text-gray-500 mb-1">{{ __('borrower.profile.person_with_asset') }}</p>
                                        <img src="{{ asset('storage/'.$meta['person_with_asset_path']) }}" alt="" class="w-full h-20 object-cover rounded-lg ring-1 ring-gray-100">
                                    </div>
                                @endif
                                @if ($meta['ownership_document_path'] ?? null)
                                    <div>
                                        <p class="text-[10px] text-gray-500 mb-1">{{ __('borrower.profile.ownership_document') }}</p>
                                        @if (str_ends_with(strtolower($meta['ownership_document_path']), '.pdf'))
                                            <a href="{{ asset('storage/'.$meta['ownership_document_path']) }}" target="_blank" class="text-xs text-brand font-semibold">{{ __('borrower.profile.view_document') }}</a>
                                        @else
                                            <img src="{{ asset('storage/'.$meta['ownership_document_path']) }}" alt="" class="w-full h-20 object-cover rounded-lg ring-1 ring-gray-100">
                                        @endif
                                    </div>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('site.borrower.profile.assets.destroy', $asset) }}" class="mt-4"
                                  @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.profile.remove_asset_confirm')), message: '', confirmLabel: @js(__('borrower.profile.remove_asset')), confirmClass: 'bg-red-600 hover:bg-red-700 text-white' })">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs font-semibold text-red-600 hover:underline">{{ __('borrower.profile.remove_asset') }}</button>
                            </form>
                        </x-site.profile-section-card>
                    @endforeach
                </div>
            @endif

            <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold mb-3">{{ __('borrower.profile.choose_asset_type') }}</p>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                @foreach ($assetTypes as $key => $label)
                    <a href="{{ route('site.borrower.profile', ['section' => 'assets', 'add' => 1, 'type' => $key]) }}"
                       class="group rounded-2xl ring-1 ring-gray-200/80 p-5 hover:ring-brand/40 hover:shadow-md transition bg-white text-center">
                        <span class="text-3xl block mb-3" aria-hidden="true">{{ $typeIcons[$key] ?? '📦' }}</span>
                        <h3 class="font-bold text-gray-900 group-hover:text-brand">{{ $label }}</h3>
                        <p class="mt-2 text-xs font-semibold text-brand">{{ __('borrower.profile.add_asset') }} →</p>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-site.borrower-layout>
