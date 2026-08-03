{{-- Proof of income & additional docs now live under Activity. Keep this route for bookmarks/wizard. --}}
@php
    $params = array_filter([
        'section' => 'activity',
        'focus'   => request()->query('focus', 'income'),
        'wizard'  => ($wizardMode ?? false) || request()->boolean('wizard') ? 1 : null,
        'return'  => $returnUrl ?? request()->query('return'),
    ]);
@endphp
<script>window.location.replace(@js(route('site.borrower.profile', $params)));</script>
<meta http-equiv="refresh" content="0;url={{ route('site.borrower.profile', $params) }}">
<p class="p-6 text-sm text-gray-600">
    <a href="{{ route('site.borrower.profile', $params) }}" class="font-semibold text-brand underline">{{ __('borrower.profile.activity') }}</a>
</p>
