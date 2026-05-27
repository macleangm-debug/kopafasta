<x-site.layout title="FAQ — Kopafasta">
    <section class="max-w-3xl mx-auto px-4 py-16" x-data="{ open: 0 }">
        <h1 class="text-3xl sm:text-4xl font-bold tracking-tight">Frequently asked questions</h1>

        <div class="mt-8 space-y-3">
            @foreach ([
                ['How fast is disbursement?', 'Most approved loans are disbursed within a few hours of approval, during business hours.'],
                ['What documents do I need?', 'Your NIDA, a working phone, and proof of income or business. Larger loans may require collateral.'],
                ['What is the interest rate?', 'Rates start from 15% per month depending on product, tenure and your credit profile. All fees are disclosed before you sign.'],
                ['Can I repay early?', 'Yes. Early repayment is welcome and reduces your total interest.'],
                ['How do vendors get started?', 'Register as a vendor, pick a category, and our team will reach out to verify and activate your account.'],
            ] as $i => $row)
                <div class="bg-white rounded-xl border border-gray-200">
                    <button type="button" @click="open === {{ $i }} ? open = -1 : open = {{ $i }}"
                            class="w-full px-5 py-4 flex items-center justify-between text-left font-medium">
                        <span>{{ $row[0] }}</span>
                        <svg :class="open === {{ $i }} ? 'rotate-180' : ''" class="w-5 h-5 text-gray-400 transition" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                    <div x-show="open === {{ $i }}" x-collapse class="px-5 pb-4 text-sm text-gray-600">{{ $row[1] }}</div>
                </div>
            @endforeach
        </div>
    </section>
</x-site.layout>
