@props(['title', 'subtitle' => null, 'share' => null])

<h1 class="text-2xl sm:text-3xl font-bold mb-1" @if ($share) style="view-transition-name: {{ $share }}" @endif>{{ $title }}</h1>
@if (filled($subtitle))
    <p class="text-sm text-gray-500 mb-6">{{ $subtitle }}</p>
@else
    <div class="mb-6"></div>
@endif
