<x-site.borrower-layout :title="brand_title(__('borrower.profile.my_assets'))" active="profile" content-width="wide">
    @include('site.borrower.profile._tabs', ['active' => 'assets'])

    <div>
        @include('site.borrower.profile._heading', [
            'title' => __('borrower.profile.my_assets'),
            'subtitle' => __('borrower.profile.my_assets_hint'),
        ])

        @if (session('status'))
            <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('site.borrower.profile.assets.store') }}" enctype="multipart/form-data" class="glass-card p-6 mb-6 space-y-4">
            @csrf
            <h2 class="font-semibold text-gray-900">{{ __('borrower.profile.add_asset') }}</h2>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.asset_type') }}</label>
                    <select name="asset_type" required class="w-full rounded-lg border-gray-300 text-sm">
                        @foreach ($assetTypes as $key => $label)
                            <option value="{{ $key }}" @selected(old('asset_type') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.asset_label') }}</label>
                    <input type="text" name="label" value="{{ old('label') }}" required maxlength="150" class="w-full rounded-lg border-gray-300 text-sm" placeholder="{{ __('borrower.profile.asset_label_placeholder') }}">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.registration_number') }}</label>
                    <input type="text" name="registration_number" value="{{ old('registration_number') }}" maxlength="80" class="w-full rounded-lg border-gray-300 text-sm">
                </div>
                <div>
                    <x-site.numeric-input name="estimated_value" :label="__('borrower.profile.estimated_value')" :value="old('estimated_value')" min="0" step="1" :money="true" />
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.description') }}</label>
                    <textarea name="description" rows="2" class="w-full rounded-lg border-gray-300 text-sm">{{ old('description') }}</textarea>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.asset_photo') }}</label>
                    <input type="file" name="photo" accept="image/*" class="w-full text-sm">
                </div>
            </div>
            <button type="submit" class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">{{ __('borrower.profile.save_asset') }}</button>
        </form>

        <div class="space-y-3">
            @forelse ($assets as $asset)
                <div class="glass-card p-5 flex flex-wrap gap-4 justify-between items-start">
                    <div>
                        <p class="text-xs uppercase tracking-widest text-gray-500">{{ $assetTypes[$asset->asset_type] ?? $asset->asset_type }}</p>
                        <h3 class="font-semibold text-gray-900 mt-1">{{ $asset->label }}</h3>
                        @if ($asset->registration_number)
                            <p class="text-sm text-gray-600 mt-1 font-mono">{{ $asset->registration_number }}</p>
                        @endif
                        @if ($asset->estimated_value)
                            <p class="text-sm text-gray-600 mt-1">{{ format_money($asset->estimated_value) }}</p>
                        @endif
                        @if ($asset->description)
                            <p class="text-sm text-gray-500 mt-2">{{ $asset->description }}</p>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('site.borrower.profile.assets.destroy', $asset) }}"
                          @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.profile.remove_asset_confirm')), message: '', confirmLabel: @js(__('borrower.profile.remove_asset')), confirmClass: 'bg-red-600 hover:bg-red-700 text-white' })">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-xs font-semibold text-rose-700 hover:underline">{{ __('borrower.profile.remove_asset') }}</button>
                    </form>
                </div>
            @empty
                <p class="text-sm text-gray-500">{{ __('borrower.profile.no_assets_yet') }}</p>
            @endforelse
        </div>
    </div>
</x-site.borrower-layout>
