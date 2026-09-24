@php
    $partner = $partner ?? null;
    $isSupplier = (bool) $partner?->isSupplier();
    $heading = $isSupplier
        ? __('site.auth.partner_activate_heading_supplier')
        : __('site.auth.partner_activate_heading');
    $badge = $isSupplier
        ? __('site.auth.partner_activate_badge_supplier')
        : __('site.auth.partner_activate_badge');
    $keepTypedPhone = $errors->has('phone');
@endphp
<x-site.auth-shell
    :title="brand_title(__('site.auth.partner_activate_brand'))"
    :badge="$badge"
    :heading="$heading"
    :identity="$partner?->name"
    :support="__('site.auth.partner_activate_support')"
    :aside-eyebrow="__('site.auth.partner_activate_brand')"
    :aside-title="$heading"
    :aside-body="__('site.auth.partner_activate_support')"
    :auth="false"
>
    @if ($errors->any())
        <div class="mb-4 p-3 rounded-xl bg-red-50 border border-red-200 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('site.partner.start.lookup') }}"
          class="kf-auth-form" autocomplete="off" data-no-draft
          x-data
          x-init="
            const keep = {{ $keepTypedPhone ? 'true' : 'false' }};
            const wipe = () => {
                if (keep) return;
                $root.querySelectorAll('[data-phone-local]').forEach((el) => { el.value = ''; el.dispatchEvent(new Event('input')); });
                $root.querySelectorAll('[data-phone-hidden]').forEach((el) => { el.value = ''; el.setAttribute('value', ''); });
            };
            wipe();
            $nextTick(wipe);
            window.addEventListener('pageshow', wipe);
          ">
        @csrf
        <div>
            <label class="kf-auth-label">{{ __('site.auth.partner_account_number') }}</label>
            @if ($partner?->partner_number)
                <input type="text" name="partner_code" value="{{ $partner->partner_number }}" readonly
                       autocomplete="off" tabindex="-1"
                       class="kf-auth-input bg-gray-50 uppercase font-mono text-gray-700">
            @else
                <input type="text" name="partner_code" value="{{ old('partner_code', request('partner_code')) }}" required
                       autocomplete="off" autocapitalize="characters" spellcheck="false"
                       placeholder="PT-XX-TZ-XXXX"
                       class="kf-auth-input uppercase font-mono">
            @endif
        </div>
        <x-site.phone-input
            name="phone"
            :label="__('site.auth.partner_phone_label')"
            :value="$keepTypedPhone ? old('phone') : null"
            :help="false"
            :lock-empty="! $keepTypedPhone"
            :placeholder="__('site.auth.partner_phone_placeholder')"
            variant="rounded"
            :required="true"
        />
        @if (! empty($maskedPhone) && ! $errors->has('phone'))
            <p class="kf-auth-help -mt-2">{{ __('site.auth.partner_phone_ends_in', ['last' => $maskedPhone]) }}</p>
        @endif
        <x-site.turnstile action="partner-activate" />
        <button type="submit" class="kf-auth-btn" data-loading-label="{{ __('site.auth.activating') }}">
            {{ __('site.auth.partner_verify_continue') }}
        </button>
    </form>

    <div class="mt-5 pt-4 border-t border-gray-100 text-center text-sm text-gray-500">
        {{ __('site.auth.already_activated') }}
        <a href="{{ route('site.login.partner') }}" class="text-brand font-semibold hover:underline">{{ __('site.auth.sign_in') }}</a>
    </div>
</x-site.auth-shell>
