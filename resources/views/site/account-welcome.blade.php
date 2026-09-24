<x-site.layout :auth="true" :minimal="true" :title="brand_title(__('account_welcome.kicker'))">
    <section class="min-h-full premium-gradient flex items-center justify-center px-4 py-4 sm:px-8 sm:py-8 pb-[max(1rem,env(safe-area-inset-bottom,0px))]">
        <div class="w-full max-w-lg sm:max-w-xl">
            <x-site.account-welcome :welcome="$welcome" :standalone="true" />
        </div>
    </section>
</x-site.layout>
