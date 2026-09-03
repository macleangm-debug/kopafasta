<x-site.layout :auth="true" :minimal="true" :title="brand_title(__('account_welcome.kicker'))">
    <section class="min-h-full premium-gradient flex items-center justify-center px-4 py-8 sm:px-8">
        <div class="w-full max-w-xl">
            <x-site.account-welcome :welcome="$welcome" :standalone="true" />
        </div>
    </section>
</x-site.layout>
