@php
    $legalName = brand('legal_name');
    $phones = support_phones();
    $emails = support_emails();
@endphp
<div class="mt-6 rounded-2xl bg-white/80 ring-1 ring-brand/10 px-4 py-3.5 text-left space-y-2 {{ $centered ?? false ? 'mx-auto max-w-xl text-center sm:text-left' : 'max-w-lg' }}">
    <p class="text-xs sm:text-sm font-semibold text-brand leading-snug">
        {{ __('site.footer.ownership', ['legal_name' => $legalName]) }}
    </p>
    <p class="text-[11px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('site.footer.complaints_heading') }}</p>
    <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-700">
        @foreach ($phones as $phone)
            <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="font-semibold text-brand hover:underline">{{ $phone }}</a>
        @endforeach
        @foreach ($emails as $email)
            <a href="mailto:{{ $email }}" class="font-semibold text-brand hover:underline">{{ $email }}</a>
        @endforeach
    </div>
</div>
