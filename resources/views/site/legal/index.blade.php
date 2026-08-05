<x-site.layout title="Legal documents — Kopafasta">
    <section class="bg-brand text-white py-14 sm:py-20">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <p class="text-xs uppercase tracking-widest text-brand-gold font-semibold">Legal centre</p>
            <h1 class="mt-2 text-3xl sm:text-4xl font-bold tracking-tight">Documents &amp; policies</h1>
            <p class="mt-3 text-white/75 max-w-2xl">Standard documents that govern your relationship with {{ brand('legal_name') }} as a microfinance borrower, member, or visitor.</p>
        </div>
    </section>

    <section class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-4">
        @foreach ([
            ['Terms of Service', 'site.legal.terms', 'Membership, loan applications, repayments, and platform use.'],
            ['Privacy Policy', 'site.legal.privacy', 'How we collect, use, share, and protect personal data.'],
            ['Loan agreement & offer letters', null, 'Issued per product after approval — signed in your portal.'],
            ['Credit disclosure / key facts statement', null, 'Provided with each loan offer (APR, fees, schedule).'],
            ['Data processing / consent notice', null, 'NIDA, CRB, and face verification consents at profile time.'],
            ['Complaint & dispute handling policy', null, 'How to escalate service or repayment concerns.'],
            ['AML / KYC notice', null, 'Identity and source-of-funds checks required by law.'],
            ['Cookie & electronic communications notice', null, 'Website cookies and transactional SMS / email.'],
        ] as [$title, $route, $hint])
            <div class="rounded-2xl bg-white ring-1 ring-gray-200 px-5 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <p class="font-semibold text-gray-900">{{ $title }}</p>
                    <p class="text-sm text-gray-500 mt-0.5">{{ $hint }}</p>
                </div>
                @if ($route)
                    <a href="{{ route($route) }}" class="shrink-0 rounded-xl bg-brand text-white text-sm font-semibold px-4 py-2.5 hover:bg-brand-light">Read</a>
                @else
                    <span class="shrink-0 text-xs font-semibold uppercase tracking-wide text-gray-400">In product</span>
                @endif
            </div>
        @endforeach
    </section>
</x-site.layout>
