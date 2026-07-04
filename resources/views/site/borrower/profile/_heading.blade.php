@props(['title', 'subtitle'])

<p class="text-xs uppercase tracking-widest text-brand font-semibold mb-1">{{ __('borrower.profile.section_label') }}</p>
<h1 class="text-2xl sm:text-3xl font-bold mb-1">{{ $title }}</h1>
<p class="text-sm text-gray-500 mb-6">{{ $subtitle }}</p>
