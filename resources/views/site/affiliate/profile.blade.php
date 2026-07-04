<x-site.affiliate-layout :title="brand_title(__('site.affiliate_portal.profile_title'))" active="profile">

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">{{ __('site.affiliate_portal.profile_title') }}</h1>
        <p class="text-sm text-gray-600 mt-1">{{ __('site.affiliate_portal.profile_subtitle') }}</p>
    </div>

    <form method="POST" action="{{ route('site.affiliate.profile.update') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="glass-card p-6 space-y-5">
            <h2 class="text-sm font-bold uppercase tracking-widest text-gray-500">{{ __('site.affiliate_portal.personalize_code') }}</h2>
            <p class="text-sm text-gray-600">{{ __('site.affiliate_portal.code_rules') }}</p>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_portal.promo_code') }}</label>
                    <input name="affiliate_code" value="{{ old('affiliate_code', $vendor->affiliate_code) }}"
                           pattern="[A-Za-z0-9_-]{3,24}" maxlength="24"
                           class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm font-mono uppercase">
                    @error('affiliate_code')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="flex items-end">
                    <p class="text-xs text-gray-500 pb-3">{{ __('site.affiliate_portal.verify_link') }}:
                        <a href="{{ $links['verify_link'] ?? '#' }}" target="_blank" class="text-brand font-semibold hover:underline">{{ $links['affiliate_code'] ?? '' }}</a>
                    </p>
                </div>
            </div>
        </div>

        <div class="glass-card p-6 space-y-4">
            <h2 class="text-sm font-bold uppercase tracking-widest text-gray-500">{{ __('site.affiliate_portal.contact_details') }}</h2>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_apply.full_name') }}</label>
                    <input name="name" value="{{ old('name', $vendor->name) }}" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_apply.email') }}</label>
                    <input type="email" name="email" value="{{ old('email', $vendor->email) }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                </div>
                <div>
                    <x-site.phone-input name="phone" :label="__('site.affiliate_apply.phone')" :value="old('phone', $vendor->phone)" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_portal.address') }}</label>
                    <input name="address" value="{{ old('address', $vendor->address) }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                </div>
            </div>
        </div>

        <div class="glass-card p-6 space-y-4">
            <h2 class="text-sm font-bold uppercase tracking-widest text-gray-500">{{ __('site.affiliate_portal.kyc_documents') }}</h2>
            <p class="text-sm text-gray-600">{{ __('site.affiliate_portal.kyc_status', ['status' => ucfirst($vendor->affiliate_kyc_status ?? 'pending')]) }}</p>
            <div class="grid sm:grid-cols-3 gap-4">
                @foreach ([
                    'affiliate_selfie' => __('site.affiliate_portal.selfie'),
                    'affiliate_id'     => __('site.affiliate_portal.national_id'),
                    'affiliate_photo'  => __('site.affiliate_portal.profile_photo'),
                ] as $field => $label)
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ $label }}</label>
                        <input type="file" name="{{ $field }}" accept="image/*" class="w-full text-xs file:mr-2 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-brand-muted file:text-brand file:font-semibold">
                    </div>
                @endforeach
            </div>
        </div>

        <button type="submit" class="bg-brand hover:bg-brand-light text-white font-semibold px-8 py-3 rounded-full text-sm">{{ __('site.affiliate_portal.save_profile') }}</button>
    </form>

</x-site.affiliate-layout>
