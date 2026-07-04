@props(['lines' => 3])

<article class="glass-card overflow-hidden animate-pulse">
    <div class="aspect-[4/3] skeleton"></div>
    <div class="p-4 space-y-3">
        <div class="skeleton h-4 w-3/4"></div>
        <div class="skeleton h-3 w-1/2"></div>
        @for ($i = 0; $i < $lines; $i++)
            <div class="skeleton h-3 w-full"></div>
        @endfor
        <div class="skeleton h-10 w-full rounded-xl mt-4"></div>
    </div>
</article>
