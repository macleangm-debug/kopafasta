{{--
  Server-rendered pages are already complete — do not delay-reveal behind a skeleton.
  An artificial ready timeout made empty states flicker (skeleton “list” → real empty).
  Skeleton slot is accepted for backwards compatibility but ignored.
--}}
@props([])

<div {{ $attributes }}>
    {{ $slot }}
</div>
