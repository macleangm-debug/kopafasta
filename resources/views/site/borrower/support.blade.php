<x-site.borrower-layout :title="brand_title('Support')" active="support">

    <h1 class="text-2xl font-bold mb-1">Support center</h1>
    <p class="text-sm text-gray-500 mb-6">FAQ, AI assistant, and human support.</p>

    @if ($customer->nida_locked_until && now()->lt($customer->nida_locked_until))
        <div id="identity-appeal" class="mb-8 rounded-2xl border border-amber-200 bg-amber-50 p-6">
            <p class="text-xs uppercase tracking-widest text-amber-700 font-semibold">{{ __('borrower.nida.title') }}</p>
            <h2 class="text-lg font-bold text-amber-950 mt-1">Identity verification appeal</h2>
            <p class="text-sm text-amber-900 mt-2">{{ __('borrower.nida.verification_locked_appeal') }}</p>
            <p class="text-sm text-amber-900 mt-2">
                {{ __('borrower.nida.account_locked_until', ['time' => $customer->nida_locked_until->format('d M Y H:i')]) }}
            </p>
            <p class="text-sm text-amber-900 mt-3">
                When you contact us, include your registered phone number and a brief explanation (for example: legal name change, typo at registration, or bureau error). Our team will review and respond during business hours.
            </p>
            <div class="mt-4 flex flex-wrap gap-3">
                <a href="tel:{{ preg_replace('/\s+/', '', config('branding.support_phone')) }}"
                   class="inline-flex bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
                    Call {{ config('branding.support_phone') }}
                </a>
                <a href="mailto:{{ config('branding.support_email') }}?subject=Identity%20verification%20appeal"
                   class="inline-flex bg-white ring-1 ring-amber-200 hover:bg-amber-100 text-amber-950 font-semibold px-5 py-2.5 rounded-full text-sm">
                    Email {{ config('branding.support_email') }}
                </a>
            </div>
        </div>
    @else
        <div id="identity-appeal" class="mb-8 rounded-2xl border border-gray-200 bg-white p-6">
            <h2 class="font-semibold">Identity verification help</h2>
            <p class="text-sm text-gray-600 mt-2">
                If your NIDA name does not match your registration, or verification was paused, contact us with your phone number and NIDA details. We review appeals case by case — for example legal name changes or bureau errors.
            </p>
            <a href="mailto:{{ config('branding.support_email') }}?subject=Identity%20verification%20help"
               class="inline-flex mt-4 text-sm font-semibold text-amber-700 hover:underline">
                {{ config('branding.support_email') }}
            </a>
        </div>
    @endif

    {{-- AI Assistant --}}
    <div class="mb-8 bg-white rounded-2xl border border-gray-200 p-5" x-data="supportChat()">
        <div class="flex items-center gap-3 mb-4">
            <div class="size-10 rounded-xl bg-indigo-100 text-indigo-700 grid place-items-center font-bold text-sm">AI</div>
            <div>
                <p class="font-semibold">KopaFasta Assistant</p>
                <p class="text-xs text-gray-500">Ask about membership, loans, repayments, or guarantors</p>
            </div>
        </div>
        <div class="rounded-xl bg-gray-50 border border-gray-200 p-4 max-h-48 overflow-y-auto space-y-3 text-sm mb-3">
            <template x-for="(msg, i) in messages" :key="i">
                <div :class="msg.role === 'user' ? 'text-right' : ''">
                    <span class="inline-block px-3 py-2 rounded-xl max-w-[90%]"
                          :class="msg.role === 'user' ? 'bg-amber-100 text-gray-900' : 'bg-white ring-1 ring-gray-200 text-gray-700'"
                          x-text="msg.text"></span>
                </div>
            </template>
        </div>
        <form @submit.prevent="ask" class="flex gap-2">
            <input type="text" x-model="input" placeholder="Type your question…"
                   class="flex-1 rounded-xl border-gray-300 text-sm focus:border-amber-500 focus:ring-amber-500">
            <button type="submit" class="bg-gray-900 text-white text-sm font-semibold px-4 py-2 rounded-xl">Send</button>
        </form>
    </div>

    {{-- FAQ --}}
    <div class="mb-8">
        <h2 class="font-semibold mb-3">Frequently asked questions</h2>
        <div class="space-y-2" x-data="{ open: null }">
            @foreach ([
                ['q' => 'What is the membership fee?', 'a' => 'A one-time registration fee when you join, then annual renewal after your membership period expires (typically 1 year).'],
                ['q' => 'How do I apply for a loan?', 'a' => 'From your dashboard, choose a loan product, complete the in-portal application, and upload required documents.'],
                ['q' => 'Do I need guarantors?', 'a' => 'Some products require guarantors. You will be prompted during the loan application if your selected product needs them.'],
                ['q' => 'How do repayments work?', 'a' => 'View your schedule under Loans and make payments from the Payments section. Late payments may incur penalties per your loan terms.'],
                ['q' => 'What if my membership expires?', 'a' => 'Renew from Membership before applying for new loans. You can still view history while expired.'],
            ] as $i => $faq)
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <button type="button" @click="open = open === {{ $i }} ? null : {{ $i }}"
                            class="w-full text-left px-4 py-3 font-medium text-sm flex justify-between items-center">
                        {{ $faq['q'] }}
                        <span x-text="open === {{ $i }} ? '−' : '+'"></span>
                    </button>
                    <div x-show="open === {{ $i }}" x-cloak class="px-4 pb-3 text-sm text-gray-600">{{ $faq['a'] }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
        <a href="tel:+255700000000" class="bg-white rounded-2xl border border-gray-200 p-6 hover:border-amber-500 transition">
            <p class="font-semibold">Call us</p>
            <p class="text-sm text-gray-500">+255 700 000 000 · 8am–8pm</p>
        </a>
        <a href="https://wa.me/255700000000" class="bg-white rounded-2xl border border-gray-200 p-6 hover:border-amber-500 transition">
            <p class="font-semibold">WhatsApp</p>
            <p class="text-sm text-gray-500">Fast replies during business hours</p>
        </a>
    </div>

    <script>
        function supportChat() {
            const answers = {
                membership: 'Membership is valid for one year. Pay the registration fee once, then renew before expiry to keep applying for loans.',
                loan: 'Go to Dashboard → pick a product → Apply. You stay inside your borrower account the whole time.',
                guarantor: 'Guarantors are collected during loan application when your product requires them—not as a separate menu item.',
                repayment: 'Open Payments to record a repayment, or view your schedule under Loans.',
                penalty: 'Late repayments may attract penalty fees according to your loan agreement.',
                default: 'I can help with membership, loans, guarantors, repayments, and penalties. Try asking about one of those topics.',
            };
            return {
                input: '',
                messages: [{ role: 'bot', text: 'Hi! How can I help you today?' }],
                ask() {
                    const q = this.input.trim();
                    if (!q) return;
                    this.messages.push({ role: 'user', text: q });
                    this.input = '';
                    const lower = q.toLowerCase();
                    let reply = answers.default;
                    if (lower.includes('member')) reply = answers.membership;
                    else if (lower.includes('loan') || lower.includes('apply')) reply = answers.loan;
                    else if (lower.includes('guarantor')) reply = answers.guarantor;
                    else if (lower.includes('repay') || lower.includes('payment')) reply = answers.repayment;
                    else if (lower.includes('penalt')) reply = answers.penalty;
                    this.messages.push({ role: 'bot', text: reply });
                },
            };
        }
    </script>
</x-site.borrower-layout>
