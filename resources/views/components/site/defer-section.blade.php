{{--
  Progressive/deferred below-fold section.
  Shows skeleton until near viewport, then reveals server-rendered slot (no fake timers).
  Use for heavy non-critical sections only — never for permissions/financial correctness.
--}}
@props([
    'skeleton' => 'rows',
    'lines' => 4,
    'rootMargin' => '240px 0px',
])

<div {{ $attributes->class(['kf-defer-section']) }}
     data-kf-defer
     x-data="{ shown: false }"
     x-init="
        const root = $el;
        const reveal = () => { if (!shown) { shown = true; } };
        if (!('IntersectionObserver' in window)) { reveal(); return; }
        const io = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    reveal();
                    io.disconnect();
                }
            });
        }, { rootMargin: @js($rootMargin), threshold: 0.01 });
        io.observe(root);
     ">
    <div x-show="!shown" x-cloak>
        <x-site.skeleton :variant="$skeleton" :lines="$lines" />
    </div>
    <div x-show="shown" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
        {{ $slot }}
    </div>
</div>
