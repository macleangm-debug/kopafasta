<x-site.layout title="Terms of Service — Kopafasta">
    <article class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12 prose prose-slate max-w-none">
        <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-2"><a href="{{ route('site.legal') }}" class="hover:underline">Legal</a> · Terms</p>
        <h1 class="text-3xl font-bold text-gray-900 !mb-2">Terms of Service</h1>
        <p class="text-sm text-gray-500 !mt-0">Effective {{ date('d F Y') }}. These Terms govern use of the Kopafasta digital microfinance platform operated by {{ brand('legal_name') }} (“Kopafasta”, “we”, “us”). By registering, paying a membership fee, applying for credit, or using our website, app, USSD, SMS, or partner channels, you agree to these Terms and our Privacy Policy.</p>

        <h2>1. Parties and scope</h2>
        <p>These Terms form a binding agreement between you (an individual borrower, guarantor, group member, or visitor) and {{ brand('legal_name') }}. They cover membership, loan applications, repayments, payments, marketplace browsing, referrals/affiliates, and related digital services. Product-specific terms in offer letters, key facts statements, and signed loan agreements prevail for that loan where they conflict with these general Terms.</p>

        <h2>2. Eligibility and accurate information</h2>
        <p>You must be at least 18 years old, legally capable of contracting in your country of residence, and provide true, complete, and current information (including national ID / NIDA where required). You authorise us to verify identity, contact details, employment/business, collateral, and creditworthiness through internal checks and licensed third parties. We may refuse, suspend, or terminate accounts that fail KYC, AML, sanctions, fraud, or eligibility checks.</p>

        <h2>3. Account security</h2>
        <p>You are responsible for safeguarding your password, PIN, OTP codes, and devices. Do not share credentials. After repeated failed PIN attempts your account may lock temporarily. Notify Support immediately if you suspect unauthorised access. Actions taken with your credentials are treated as yours unless we caused the compromise through proven negligence.</p>

        <h2>4. Membership fee</h2>
        <p>Borrower membership is a platform access product. Paying the membership fee (or renewal fee) unlocks loan applications and related services for the membership period disclosed at checkout. Membership is not a deposit, savings product, investment, insurance policy, or guarantee of loan approval or disbursement. Fees are generally non-refundable once paid, except where required by applicable consumer or microfinance law, or where we cancel the service before activation through our fault.</p>

        <h2>5. Loan applications and credit decisions</h2>
        <p>Submitting an application does not create a binding loan. We may approve, decline, counter-offer, request documents, revalue collateral, or cancel incomplete applications. Decisions may use credit bureau (CRB) data, internal scoring, face/NIDA verification, guarantor information, group rules, and partner valuations. Interest, fees, APR/effective cost disclosures, and repayment schedules appear in your offer / key facts statement before acceptance. You should read those carefully before signing.</p>

        <h2>6. Collateral, insurance, and partners</h2>
        <p>Where a product requires collateral, GPS, insurance, yard storage, valuation, or recovery partners, you authorise engagement of those partners on the terms disclosed in the product flow. Partner fees may be payable separately. Loss, damage, or disputes involving third-party partners are handled under the applicable partner terms and your loan agreement.</p>

        <h2>7. Repayments, arrears, and default</h2>
        <p>You must repay according to the signed agreement and schedule. Late payment may trigger contractual charges, collection notices, field visits by authorised agents, restructuring discussions, and reporting to credit bureaus. Early settlement, top-ups, payment holidays, and write-offs follow product rules and any approvals we require. Persistent default may lead to enforcement of security and legal recovery as permitted by law.</p>

        <h2>8. Payments and mobile money</h2>
        <p>Mobile money and bank rails are provided via licensed payment service providers (PSPs). You authorise us and our PSPs to initiate collection requests (including USSD prompts) for amounts you confirm. Always verify merchant name and amount before entering your PIN. Failed, reversed, timed-out, or disputed payments may delay membership activation or disbursement. We are not liable for network outages or PSP failures outside our reasonable control, but we will help investigate with the PSP using your payment reference.</p>

        <h2>9. Promotions, referrals, and affiliates</h2>
        <p>Campaign discounts, promo codes, and affiliate offers apply only as stated, may be withdrawn or capped, and usually cannot be stacked unless we say so. Misuse (self-referral rings, fake identities, code farming) may void discounts and commissions and may lead to account action. Wallet credits from referrals are applied only under referral settings and eligible fee gates.</p>

        <h2>10. Acceptable use</h2>
        <p>You must not misuse the platform, circumvent controls, submit forged documents, harass staff or partners, scrape the service, or use Kopafasta for money laundering, terrorism financing, or other illegal activity. We may share information with competent authorities when legally required.</p>

        <h2>11. Intellectual property</h2>
        <p>Kopafasta branding, software, documentation, and content remain our property or that of our licensors. You receive a limited, non-exclusive, non-transferable licence to use the borrower portal for personal, lawful purposes.</p>

        <h2>12. Disclaimers and liability</h2>
        <p>The platform is provided on an “as available” basis. To the fullest extent permitted by applicable microfinance and consumer law, we are not liable for indirect, incidental, or consequential losses; decisions of independent valuers, insurers, guarantors, or courts; or outages of telecoms/PSPs. Nothing excludes liability that cannot lawfully be excluded (including fraud, or death/personal injury caused by negligence where such rules apply). Our aggregate liability for claims arising from membership access in any 12-month period is limited to the membership fees you paid in that period, except where a higher mandatory limit applies.</p>

        <h2>13. Changes</h2>
        <p>We may update these Terms. Material changes will be posted on this page with a new effective date. Where law requires notice or consent, we will provide it. Continued use after changes constitutes acceptance where permitted.</p>

        <h2>14. Governing law and disputes</h2>
        <p>These Terms are governed by the laws of the United Republic of Tanzania (or the country module under which your membership is issued). Raise issues first via in-app Support. Unresolved disputes may proceed under applicable mediation, arbitration, or court procedures.</p>

        <h2>15. Contact</h2>
        <p>Support: <a href="mailto:{{ brand('support_email') }}">{{ brand('support_email') }}</a>. Legal notices: {{ brand('legal_name') }}.</p>

        <p class="text-sm text-gray-500"><a href="{{ route('site.legal.privacy') }}" class="text-brand font-semibold hover:underline">Privacy Policy →</a>
            · <a href="{{ route('site.legal') }}" class="text-brand font-semibold hover:underline">All documents</a></p>
    </article>
</x-site.layout>
