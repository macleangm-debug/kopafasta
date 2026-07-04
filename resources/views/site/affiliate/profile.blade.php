<x-site.affiliate-layout :title="brand_title(__('site.affiliate_portal.profile_title'))" active="profile">

    <div class="mb-6">
        <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">{{ __('site.affiliate_portal.nav_profile') }}</p>
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mt-1">{{ __('site.affiliate_portal.profile_title') }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ __('site.affiliate_portal.profile_subtitle') }}</p>
    </div>

    @include('site.affiliate._kyc_overview', ['vendor' => $vendor])

    <form method="POST" action="{{ route('site.affiliate.profile.update') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        <div id="section-contact" class="glass-card p-6 space-y-4 scroll-mt-24">
            <h2 class="text-sm font-bold uppercase tracking-widest text-gray-500">{{ __('site.affiliate_portal.contact_details') }}</h2>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_apply.full_name') }}</label>
                    <input name="name" value="{{ old('name', $vendor->name) }}" required
                           class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:border-brand focus:ring-brand/10 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_apply.email') }}</label>
                    <input type="email" name="email" value="{{ old('email', $vendor->email) }}"
                           class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:border-brand focus:ring-brand/10 outline-none">
                </div>
                <div>
                    <x-site.phone-input name="phone" :label="__('site.affiliate_apply.phone')" :value="old('phone', $vendor->phone)" variant="rounded" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_portal.address') }}</label>
                    <input name="address" value="{{ old('address', $vendor->address) }}"
                           class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:border-brand focus:ring-brand/10 outline-none">
                </div>
            </div>
        </div>

        <div class="glass-card p-6 space-y-5">
            <h2 class="text-sm font-bold uppercase tracking-widest text-gray-500">{{ __('site.affiliate_portal.personalize_code') }}</h2>
            <p class="text-sm text-gray-600">{{ __('site.affiliate_portal.code_rules') }}</p>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_portal.promo_code') }}</label>
                    <input name="affiliate_code" value="{{ old('affiliate_code', $vendor->affiliate_code) }}"
                           pattern="[A-Za-z0-9_-]{3,24}" maxlength="24"
                           class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm font-mono uppercase focus:border-brand focus:ring-brand/10 outline-none">
                    @error('affiliate_code')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                @if ($vendor->affiliate_code)
                    @php $verifyUrl = $links['verify_link'] ?? route('site.affiliate.verify', $vendor->affiliate_code); @endphp
                    <div class="flex items-center gap-4 rounded-xl bg-gray-50 p-3 ring-1 ring-gray-100">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data={{ urlencode($verifyUrl) }}"
                             alt="QR" class="size-16 rounded-lg bg-white p-1 shrink-0">
                        <div class="text-xs text-gray-600 min-w-0">
                            <p class="font-semibold text-gray-800">{{ __('site.affiliate_portal.verify_link') }}</p>
                            <a href="{{ $verifyUrl }}" target="_blank" class="text-brand font-semibold hover:underline break-all">{{ $verifyUrl }}</a>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div id="section-selfie" class="glass-card p-6 space-y-4 scroll-mt-24">
            <h2 class="text-sm font-bold uppercase tracking-widest text-gray-500">{{ __('site.affiliate_portal.kyc_documents') }}</h2>
            <p class="text-sm text-gray-600">{{ __('site.affiliate_portal.kyc_status', ['status' => ucfirst($vendor->affiliate_kyc_status ?? 'pending')]) }}</p>
            <div class="grid sm:grid-cols-3 gap-4">
                @foreach ([
                    'affiliate_selfie' => ['label' => __('site.affiliate_portal.selfie'), 'path' => $vendor->affiliate_selfie_path, 'id' => 'selfie'],
                    'affiliate_id'     => ['label' => __('site.affiliate_portal.national_id'), 'path' => $vendor->affiliate_id_path, 'id' => 'id'],
                    'affiliate_photo'  => ['label' => __('site.affiliate_portal.profile_photo'), 'path' => $vendor->affiliate_photo_path, 'id' => 'photo'],
                ] as $field => $meta)
                    <div id="section-{{ $meta['id'] }}" class="rounded-xl ring-1 ring-gray-200 p-4 bg-white scroll-mt-24">
                        <label class="block text-xs font-semibold text-gray-700 mb-2">{{ $meta['label'] }}</label>
                        @if ($meta['path'])
                            <img src="{{ asset('storage/'.$meta['path']) }}" alt="" class="w-full h-28 object-cover rounded-lg mb-2 ring-1 ring-gray-100">
                        @else
                            <div class="w-full h-28 rounded-lg bg-gray-50 ring-1 ring-gray-100 grid place-items-center text-gray-400 text-xs mb-2">{{ __('site.affiliate_portal.no_upload') }}</div>
                        @endif
                        <input type="file" name="{{ $field }}" accept="image/*"
                               class="w-full text-xs file:mr-2 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-brand-muted file:text-brand file:font-semibold">
                    </div>
                @endforeach
            </div>
        </div>

        <button type="submit" class="bg-brand hover:bg-brand-light text-white font-semibold px-8 py-3 rounded-xl text-sm shadow-md">
            {{ __('site.affiliate_portal.save_profile') }}
        </button>
    </form>

</x-site.affiliate-layout>
