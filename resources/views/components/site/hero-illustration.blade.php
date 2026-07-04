<div {{ $attributes->merge(['class' => 'relative w-full max-w-lg mx-auto']) }}>
    <div class="relative aspect-[4/3] sm:aspect-square rounded-3xl overflow-hidden bg-gradient-to-br from-brand via-brand-light to-emerald-700 shadow-[0_24px_80px_rgba(0,77,64,0.25)] ring-1 ring-white/20">
        <div class="absolute inset-0 opacity-30 bg-[radial-gradient(circle_at_30%_20%,_#f5c842,_transparent_45%)]"></div>
        <svg class="absolute inset-0 w-full h-full" viewBox="0 0 400 400" fill="none" aria-hidden="true">
            <circle cx="320" cy="80" r="48" stroke="rgba(255,255,255,0.25)" stroke-width="2" stroke-dasharray="6 8"/>
            <circle cx="70" cy="310" r="32" stroke="rgba(255,255,255,0.2)" stroke-width="2"/>
            <path d="M60 180 Q200 80 340 220" stroke="rgba(245,200,66,0.5)" stroke-width="3" stroke-linecap="round"/>
        </svg>
        <div class="absolute inset-0 flex items-center justify-center p-8">
            <div class="relative">
                <div class="w-36 sm:w-44 rounded-[2rem] bg-white/95 shadow-2xl p-4 ring-1 ring-white/50">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="size-8 rounded-xl bg-brand grid place-items-center">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                        </div>
                        <div class="flex-1 space-y-1.5">
                            <div class="h-2 rounded-full bg-brand/20 w-full"></div>
                            <div class="h-2 rounded-full bg-brand/10 w-2/3"></div>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <div class="h-8 rounded-xl bg-brand-muted flex items-center px-3">
                            <span class="text-[10px] font-bold text-brand">TZS 2.5M</span>
                        </div>
                        <div class="h-8 rounded-xl bg-brand-gold/30 flex items-center justify-center">
                            <span class="text-[10px] font-bold text-brand">✓ Approved</span>
                        </div>
                    </div>
                </div>
                <div class="absolute -right-6 -bottom-4 size-16 rounded-2xl bg-brand-gold shadow-lg grid place-items-center text-2xl ring-4 ring-white/30">📱</div>
            </div>
        </div>
    </div>
    <div class="absolute -bottom-4 left-4 right-4 sm:left-auto sm:right-8 sm:w-48 glass-card px-4 py-3 flex items-center gap-3">
        <span class="size-10 rounded-full bg-emerald-100 text-emerald-700 grid place-items-center text-lg">✓</span>
        <div class="min-w-0 text-left">
            <p class="text-xs font-bold text-gray-900">{{ __('site.hero.trust_badge') }}</p>
            <p class="text-[10px] text-gray-500 truncate">{{ __('site.hero.trust_sub') }}</p>
        </div>
    </div>
</div>
