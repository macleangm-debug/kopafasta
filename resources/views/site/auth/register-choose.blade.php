<x-site.layout :title="brand_title(__('site.auth.create_account'))">
    <section class="max-w-5xl mx-auto px-4 py-16 sm:py-20">
        <div class="text-center">
            <p class="text-xs uppercase tracking-widest text-brand font-semibold">{{ __('site.auth.get_started') ?? 'Get started' }}</p>
            <h1 class="mt-3 text-3xl sm:text-4xl font-bold tracking-tight text-gray-900">{{ __('site.auth.create_account') }}</h1>
            <p class="mt-3 text-gray-600 max-w-xl mx-auto">{{ __('site.auth.choose_path') ?? __('site.auth.sign_in_subtitle') }}</p>
        </div>

        <div class="mt-12 grid sm:grid-cols-2 gap-5">
            <a href="{{ route('site.register.borrower') }}"
               class="group relative overflow-hidden rounded-3xl ring-1 ring-brand/15 hover:ring-brand/40 hover:-translate-y-0.5 hover:shadow-lg transition-all duration-200 p-8 bg-white">
                <h3 class="text-2xl font-bold text-gray-900">{{ __('site.auth.account_type_borrower') }}</h3>
                <p class="mt-2 text-sm text-gray-600 leading-relaxed">{{ __('site.auth.borrower_path_body') ?? 'Apply for a loan, manage repayments, and track your applications.' }}</p>
                <div class="mt-6 inline-flex items-center gap-1 text-sm font-bold text-brand">
                    {{ __('site.auth.continue_borrower') ?? 'Continue as borrower' }} →
                </div>
            </a>

            <a href="{{ route('site.register.partner') }}"
               class="group relative overflow-hidden rounded-3xl ring-1 ring-brand/15 hover:ring-brand/40 hover:-translate-y-0.5 hover:shadow-lg transition-all duration-200 p-8 bg-white">
                <h3 class="text-2xl font-bold text-gray-900">{{ __('site.auth.account_type_partner') }}</h3>
                <p class="mt-2 text-sm text-gray-600 leading-relaxed">{{ __('site.auth.partner_path_body') ?? 'Partner with Kopafasta as a service partner or affiliate (Mauzo).' }}</p>
                <div class="mt-6 inline-flex items-center gap-1 text-sm font-bold text-brand">
                    {{ __('site.auth.continue_partner') ?? 'Continue as partner' }} →
                </div>
            </a>
        </div>

        <p class="mt-10 text-center text-sm text-gray-600">
            {{ __('site.auth.already_have_account') ?? 'Already have an account?' }}
            <a href="{{ route('site.login') }}" class="text-brand font-semibold hover:underline">{{ __('site.auth.sign_in') }}</a>
        </p>
    </section>
</x-site.layout>
