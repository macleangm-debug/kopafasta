@php require resource_path('views/pdf/loan-agreement/locale.php'); @endphp
@php
    $company = $snapshot['company_legal_name'] ?? brand('legal_name');
    $cadences = __('borrower.agreement.repayment_cadences', [], $locale);
    $cadenceKey = $snapshot['repayment_cadence'] ?? 'weekly';
    $cadenceLabel = $cadences[$cadenceKey] ?? ucfirst((string) $cadenceKey);
    $recovery = $snapshot['recovery_schedule'] ?? [];
    $stages = $recovery['stages'] ?? [];
    $isFinal = ($includeScheduleAnnex ?? false) || (($snapshot['schedule_is_estimate'] ?? true) === false);
    $graceDays = (int) ($snapshot['grace_days'] ?? $snapshot['legal_clauses']['grace_days'] ?? 7);
    $penaltyCap = format_number($snapshot['penalty_cap_percent'] ?? $snapshot['legal_clauses']['penalty_cap_percent'] ?? 30, 0);
    $showGuarantor = filled($snapshot['guarantor_name'] ?? null);
    $showGroup = ! empty($snapshot['is_group_loan']);
    $showCollateral = ! empty($snapshot['is_asset_loan']) || ! empty($snapshot['contract_modules']['show_collateral']);
    $showGpsFee = ! empty($snapshot['gps_fee']);
    $showSalaryAdvance = ! empty($snapshot['is_salary_advance']);
    $percentStages = collect($stages)->contains(fn ($stage) => in_array($stage['fee_type'] ?? 'percentage', ['percentage', 'hybrid'], true));
    $sections = $snapshot['contract_sections'] ?? [];
    $on = static fn (string $key): bool => filter_var($sections[$key] ?? true, FILTER_VALIDATE_BOOLEAN);
    $examples = $snapshot['worked_examples'] ?? [];
    $clauses = $snapshot['legal_clauses'] ?? [];
    $facilityCharges = $snapshot['facility_charges'] ?? [];
    $tenureMax = (int) ($snapshot['tenure_max_months'] ?? $snapshot['tenure_months'] ?? 0);
    $jurisdiction = $jurisdictionLabel($snapshot['jurisdiction'] ?? ($clauses['jurisdiction'] ?? null));
@endphp

<div class="notice">
    <strong>{{ $t('PLEASE READ THIS AGREEMENT CAREFULLY BEFORE SIGNING.', 'TAFADHALI SOMA MKATABA HUU KWA MAKINI KABLA YA KUSAINI.') }}</strong>
    @if ($isSw)
        <p>Mkataba huu una masharti muhimu kuhusu kiasi cha mkopo, riba, marejesho, muda wa msamaha, adhabu, gharama za ufuatiliaji na urejeshaji, wajibu wa mdhamini, uwajibikaji wa kikundi, dhamana, taarifa za kumbukumbu za mikopo, na madhara ya kisheria ya kutolipa. Kwa kusaini au kukubali kwa njia ya kielektroniki, unathibitisha umepata fursa ya kuusoma na kuuelewa.</p>
    @else
        <p>This Agreement contains important provisions concerning the amount borrowed, interest, repayment, grace periods, penalties, collection and recovery charges, guarantor obligations, group liability, collateral, credit-reference reporting, and the legal consequences of default. By signing or electronically accepting, you confirm that you have had an opportunity to read and understand these terms.</p>
    @endif
</div>

<h2>{{ $t('1. Parties', '1. Wahusika') }}</h2>
<h3>{{ $t('1.1 Lender', '1.1 Mkopeshaji') }}</h3>
@if ($isSw)
    <p>Mkopeshaji ni <strong>{{ $company }}</strong>, yenye leseni/usajili wa kutoa huduma za microfinance nchini {{ $jurisdiction }} (“Mkopeshaji”, “Kopafasta”, “sisi”).</p>
@else
    <p>The lender is <strong>{{ $company }}</strong>, licensed/registered to provide microfinance services in {{ $jurisdiction }} (“Lender”, “Kopafasta”, “we”).</p>
@endif
<table class="kv">
    <tr><td class="label">{{ $t('Legal name', 'Jina la kampuni') }}</td><td class="value">{{ $company }}</td></tr>
    <tr><td class="label">{{ $t('Address', 'Anwani') }}</td><td class="value">{{ $snapshot['company_address'] ?? '—' }}</td></tr>
    <tr><td class="label">{{ $t('Licence (BOT)', 'Leseni (BOT)') }}</td><td class="value">{{ $snapshot['licence_number'] ?? '—' }}</td></tr>
    <tr><td class="label">{{ $t('Registration', 'Usajili') }}</td><td class="value">{{ $snapshot['registration_no'] ?? '—' }}</td></tr>
    <tr><td class="label">TIN</td><td class="value">{{ $snapshot['company_tin'] ?? '—' }}</td></tr>
</table>

<h3>{{ $t('1.2 Borrower', '1.2 Mkopaji') }}</h3>
<table class="kv">
    <tr><td class="label">{{ $t('Full name', 'Jina kamili') }}</td><td class="value">{{ $snapshot['customer_name'] ?? '—' }}</td></tr>
    <tr><td class="label">{{ $t('NIDA / ID number', 'NIDA / Namba ya kitambulisho') }}</td><td class="value">{{ $snapshot['customer_id'] ?? '—' }}</td></tr>
    <tr><td class="label">{{ $t('Residential address', 'Anwani ya makazi') }}</td><td class="value">{{ $snapshot['customer_address'] ?? '—' }}</td></tr>
    <tr><td class="label">{{ $t('Phone', 'Simu') }}</td><td class="value">{{ $snapshot['customer_phone'] ?? '—' }}</td></tr>
    <tr><td class="label">{{ $t('Activity', 'Shughuli') }}</td><td class="value">{{ pdf_text($activityLabel($snapshot['customer_activity'] ?? null)) }}</td></tr>
    <tr><td class="label">{{ $t('Declared income', 'Kipato') }}</td><td class="value">{{ $snapshot['customer_income'] ?? '—' }}</td></tr>
</table>

@if ($showGuarantor)
<h3>{{ $t('1.3 Guarantor', '1.3 Mdhamini') }}</h3>
<table class="kv">
    <tr><td class="label">{{ $t('Name', 'Jina') }}</td><td class="value">{{ $snapshot['guarantor_name'] }}</td></tr>
    <tr><td class="label">NIDA / ID</td><td class="value">{{ $snapshot['guarantor_nida'] ?? '—' }}</td></tr>
    <tr><td class="label">{{ $t('Address', 'Anwani') }}</td><td class="value">{{ $snapshot['guarantor_address'] ?? '—' }}</td></tr>
    <tr><td class="label">{{ $t('Phone', 'Simu') }}</td><td class="value">{{ $snapshot['guarantor_phone'] ?? '—' }}</td></tr>
    <tr><td class="label">{{ $t('Relationship', 'Uhusiano') }}</td><td class="value">{{ pdf_text($relationshipLabel($snapshot['guarantor_relationship'] ?? null)) }}</td></tr>
</table>
@endif

@if ($on('definitions'))
<h2>{{ $t('2. Definitions', '2. Ufafanuzi') }}</h2>
@if ($isSw)
    <p><strong>Msingi wa mkopo</strong> ni kiasi kilichotolewa, bila riba, adhabu na gharama za urejeshaji. <strong>Riba</strong> ni ada ya kisheria ya ufadhili. <strong>Muda wa Msamaha</strong> ni siku {{ $graceDays }} za kalenda baada ya tarehe ya malipo iliyokosa, ambapo adhabu haiingii. <strong>Adhabu</strong> ni gharama baada ya msamaha. <strong>Gharama ya Urejeshaji</strong> inadaiwa pale tu mkopo unapopelekwa hatua husika na gharama inarekodiwa. <strong>Kiasi kinachodaiwa</strong> ni kiasi kilichokwishaingia, kurekodiwa na kuwa kinacholipwa tu: msingi ambao haujalipwa, riba iliyokwishaingia, adhabu iliyokwishaingia, na gharama za urejeshaji zilizorekodiwa.</p>
@else
    <p><strong>Principal</strong> is the amount actually advanced, excluding interest, penalties and recovery charges. <strong>Interest</strong> is the contractual financing charge. <strong>Grace Period</strong> is {{ $graceDays }} calendar days after a missed due date during which overdue penalty does not accrue. <strong>Penalty</strong> is the overdue charge after grace. <strong>Recovery Charge</strong> is a charge that becomes payable only when the loan is actually assigned to a recovery stage and the charge is posted. <strong>Amount Owed</strong> is only amounts that have actually accrued, been posted and become payable: unpaid principal, accrued interest, accrued penalty, and posted recovery charges.</p>
@endif
@endif

@if ($on('loan_terms'))
<h2>{{ $t('3. Loan facility', '3. Huduma ya mkopo') }}</h2>
<table class="kv">
    <tr><td class="label">{{ $t('Contract / Offer reference', 'Rejea ya mkataba / ofa') }}</td><td class="value">{{ $agreement->reference }} · {{ $snapshot['application_number'] ?? '—' }}</td></tr>
    <tr><td class="label">{{ $t('Product', 'Bidhaa') }}</td><td class="value">{{ $snapshot['product_name'] ?? '—' }} ({{ $snapshot['product_code'] ?? '—' }})</td></tr>
    <tr><td class="label">{{ $t('Principal', 'Msingi') }}</td><td class="value">{{ format_money($snapshot['principal'] ?? 0) }}</td></tr>
    <tr><td class="label">{{ $t('Monthly rate', 'Riba kwa mwezi') }}</td><td class="value">{{ format_number(($snapshot['displayed_monthly_rate'] ?? 0) * 100, 2) }}%</td></tr>
    <tr><td class="label">{{ $t('Tenure', 'Muda') }}</td><td class="value">{{ (int) ($snapshot['tenure_months'] ?? 0) }} {{ $t('months', 'miezi') }} · {{ $snapshot['installment_count'] ?? '—' }} {{ $t('instalments', 'awamu') }} · {{ $cadenceLabel }}</td></tr>
    <tr><td class="label">{{ $t('Instalment', 'Awamu') }}</td><td class="value">{{ format_money($snapshot['estimated_emi'] ?? 0) }}</td></tr>
    <tr><td class="label">{{ $t('Total repayable', 'Jumla') }}</td><td class="value">{{ format_money($snapshot['total_repayable'] ?? 0) }}</td></tr>
    @if (! empty($snapshot['first_due_date']))
        <tr><td class="label">{{ $t('First / final due date', 'Tarehe ya kwanza / ya mwisho') }}</td><td class="value">{{ $snapshot['first_due_date'] }} · {{ $snapshot['last_due_date'] ?? '—' }}</td></tr>
    @endif
    <tr><td class="label">{{ $t('Purpose', 'Madhumuni') }}</td><td class="value">{{ pdf_text($purposeLabel($snapshot['purpose'] ?? null, $snapshot['purpose_other'] ?? null)) }}</td></tr>
</table>

@if ($showSalaryAdvance)
<h2>{{ $t('3A. Salary advance facility', '3A. Huduma ya malipo ya mshahara mapema') }}</h2>
@if ($isSw)
    <p>Hii ni huduma inayoendelea ya malipo ya mshahara mapema. Muda uliokubaliwa ni miezi {{ (int) ($snapshot['tenure_months'] ?? 0) }}{{ $tenureMax > 0 ? ' (kiwango cha juu cha bidhaa miezi '.$tenureMax.')' : '' }} kulingana na bidhaa na Mipangilio yaliyokuwa yanatumika wakati mkataba huu ulipotengenezwa. Kiasi kinachopatikana, ratiba na ada zinafuata Jedwali la Huduma. Mkopo mpya wa aina hii utatumia Mipangilio ya wakati huo, si marekebisho ya baadaye ya Mkataba huu uliosainiwa.</p>
@else
    <p>This is an ongoing salary-advance facility. The agreed term is {{ (int) ($snapshot['tenure_months'] ?? 0) }} months{{ $tenureMax > 0 ? ' (product maximum '.$tenureMax.' months)' : '' }} according to the product and the Settings in force when this Agreement was generated. Available amount, schedule and charges follow the Facility Schedule. A later facility of this type will use Settings then in force; they do not rewrite this signed Agreement.</p>
@endif
@endif
@endif

@if ($on('repayment_obligations'))
<h2>{{ $t('4–6. Disbursement, use of funds, repayment', '4–6. Utoaji, matumizi ya fedha, marejesho') }}</h2>
@if ($isSw)
    <p>Mkopo utatolewa kupitia njia iliyoidhinishwa. Ada zilizofichuliwa zinaweza kukatwa kutoka kwenye utoaji. Mkopaji atatumia mkopo kwa madhumuni yaliyoidhinishwa tu, si kwa madhumuni haramu. Kila awamu italipwa ifikapo tarehe yake kupitia njia zilizoidhinishwa. Muamala ulioshindwa hauhesabiwi kuwa malipo hadi fedha zipokelewe na kurekodiwa kwenye Akaunti ya Mkopo.</p>
@else
    <p>The Loan is disbursed through the payment channel approved by Kopafasta. Permitted disclosed fees may be deducted from disbursement. The Borrower shall use the Loan for the stated or approved purpose and not for an unlawful purpose. Each instalment is payable on or before its Due Date through approved channels. A payment instruction that fails is not payment until the amount is received and credited to the Loan Account.</p>
@endif

<h2>{{ $t('7. Payment allocation', '7. Mgawanyo wa malipo') }}</h2>
<p>{{ $isSw ? ($recovery['payment_allocation_sw'] ?? '') : ($recovery['payment_allocation_en'] ?? 'Payments: penalties, then interest, then principal, oldest unpaid first.') }}</p>
@if (! empty($examples['allocation_sw']) || ! empty($examples['allocation_en']))
    <p>{{ $isSw ? ($examples['allocation_sw'] ?? '') : ($examples['allocation_en'] ?? '') }}</p>
@endif

<h2>{{ $t('8. Early settlement', '8. Malipo kabla ya muda') }}</h2>
<p>{{ $isSw ? ($recovery['early_settlement_sw'] ?? '') : ($recovery['early_settlement_en'] ?? 'Any early-settlement charge is as stated in Annex B at signing.') }}</p>
@endif

@if ($on('penalty_clauses'))
<h2>{{ $t('9–10. Grace, arrears and penalty', '9–10. Msamaha, deni lililochelewa na adhabu') }}</h2>
@if ($isSw)
    <p>Awamu isiyolipwa kufikia tarehe yake inakuwa imechelewa kuanzia siku inayofuata ya kalenda. Muda wa Msamaha: <strong>siku {{ $graceDays }} za kalenda</strong>. Hakuna adhabu ya kimkataba inayoingia wakati wa msamaha. Baada ya msamaha, adhabu inaingia kama ifuatavyo:</p>
    <p><strong>{{ $snapshot['penalty_formula_sw'] ?? '' }}</strong></p>
    @if (! empty($examples['penalty_sw']))
        <p>Adhabu ni tofauti na gharama yoyote ya urejeshaji. {{ $examples['penalty_sw'] }}</p>
    @endif
@else
    <p>An instalment unpaid by its Due Date is overdue from the next calendar day. Grace Period: <strong>{{ $graceDays }} calendar days</strong>. No contractual overdue penalty accrues during grace. After grace, penalty accrues as follows:</p>
    <p><strong>{{ $snapshot['penalty_formula_en'] ?? '' }}</strong></p>
    @if (! empty($examples['penalty_en']))
        <p>Penalty is separate from any recovery charge. {{ $examples['penalty_en'] }}</p>
    @endif
@endif
@if (filled($clauses['penalty_clause'] ?? null))
    <p>{{ $clauses['penalty_clause'] }}</p>
@endif
@endif

@if ($on('default_events') || $on('recovery_clauses'))
<h2>{{ $t('11. Amount owed', '11. Kiasi kinachodaiwa') }}</h2>
@if ($isSw)
    <p>Kiasi kinachodaiwa ni kiasi kilichokwishaingia na kurekodiwa tu. Gharama ya urejeshaji ambayo bado haijapelekwa si deni lililopo.</p>
@else
    <p>Amount Owed at any time consists only of amounts that have actually accrued, been posted to the Loan Account and become payable. A recovery charge that has not yet been triggered or posted is not already owed.</p>
@endif

<h2>{{ $t('12–14. Default, collection and recovery charges', '12–14. Ukiukaji, ufuatiliaji na gharama za urejeshaji') }}</h2>
@if ($isSw)
    <p>Tukio la Ukiukaji linajumuisha kushindwa kulipa baada ya msamaha; taarifa za uongo kwa kiasi kikubwa; uvunjaji mkubwa; kushughulikia dhamana kinyume cha sheria; au kuzuia urejeshaji halali. Kopafasta inaweza kuchukua hatua za urejeshaji zinazoruhusiwa na Mkataba huu na sheria.</p>
    <p><strong>Ikiwa mkopo wako utapelekwa kwenye kituo cha huduma kwa wateja, gharama ya ufuatiliaji ya hatua hiyo itaongezwa kwenye kiasi unachodaiwa. Ikiwa baadaye utapelekwa kwa mtoza madeni wa eneo, gharama ya hatua hiyo inaweza kuongezwa tena. Kanuni hiyo hiyo inatumika kwa hatua nyingine zilizoanzishwa na kurekodiwa.</strong></p>
@else
    <p>An Event of Default includes failure to pay beyond grace; materially false information; material breach; unlawful dealing with collateral; or obstructing lawful recovery. Kopafasta may then take the recovery actions permitted by this Agreement and applicable law.</p>
    <p><strong>If your loan is sent to the call centre, a call-centre recovery charge will be added to what you already owe. If it is later sent to a field collector, the applicable field-collection charge may be added again. The same principle applies to later recovery stages that are actually initiated and charged.</strong></p>
@endif
<table class="grid">
    <thead>
        <tr>
            <th>{{ $t('Stage', 'Hatua') }}</th>
            <th>{{ $t('Trigger', 'Kichocheo') }}</th>
            <th>{{ $t('Charge (posted only when assigned)', 'Gharama (inarekodiwa pale tu inapopelekwa)') }}</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>{{ $t('Due date', 'Tarehe ya malipo') }}</td>
            <td>{{ $t('Instalment not paid', 'Awamu haijalipwa') }}</td>
            <td>{{ $t('Instalment remains due. No recovery charge yet.', 'Awamu inaendelea kudaiwa. Bado hakuna gharama ya urejeshaji.') }}</td>
        </tr>
        <tr>
            <td>{{ $t('Grace', 'Msamaha') }}</td>
            <td>{{ $graceDays }} {{ $t('calendar days', 'siku za kalenda') }}</td>
            <td>{{ $t('No penalty during grace.', 'Hakuna adhabu wakati wa msamaha.') }}</td>
        </tr>
        <tr>
            <td>{{ $t('Penalty', 'Adhabu') }}</td>
            <td>{{ $t('Day after grace expires', 'Siku baada ya msamaha kuisha') }}</td>
            <td>{{ $isSw ? ($snapshot['penalty_formula_sw'] ?? '') : ($snapshot['penalty_formula_en'] ?? '') }}</td>
        </tr>
        @foreach ($stages as $stage)
            <tr>
                <td>{{ $isSw ? ($stage['label_sw'] ?? $stage['label'] ?? '') : ($stage['label_en'] ?? $stage['label'] ?? '') }}</td>
                <td>{{ $isSw ? ($stage['trigger_sw'] ?? '') : ($stage['trigger_en'] ?? '') }}</td>
                <td>{{ $isSw ? ($stage['display_sw'] ?? '') : ($stage['display_en'] ?? '') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
@if ($percentStages)
<p class="muted">{{ $isSw
    ? ('Kila asilimia inatumika kwenye '.($recovery['fee_base_label_sw'] ?? 'msingi wa mkopo').' wakati wa kupelekwa: kamisheni ya mshirika na gharama ya kampuni ni asilimia tofauti za msingi huo huo na zinaongezwa pamoja. Hatua hazijumlishwi kwa kila nyingine; kila hatua inarekodi gharama yake inapopelekwa.')
    : ('Each percentage is applied to the '.($recovery['fee_base_label_en'] ?? 'principal').' at assignment: partner commission and company charge are separate percentages of that same base and are added together. Stages are not compounded with each other; each stage posts its own charge when assigned.')
}}</p>
@endif
@if (filled($clauses['default_clause'] ?? null))
    <p>{{ $clauses['default_clause'] }}</p>
@endif
@if (filled($clauses['collection_clause'] ?? null))
    <p>{{ $clauses['collection_clause'] }}</p>
@endif
@if (filled($clauses['recovery_clause'] ?? null))
    <p>{{ $clauses['recovery_clause'] }}</p>
@endif
@if ($on('legal_costs') && filled($clauses['legal_cost_clause'] ?? null))
    <p>{{ $clauses['legal_cost_clause'] }}</p>
@endif
@endif

@if ($showGuarantor && $on('guarantor_obligations'))
<h2>{{ $t('15. Guarantor', '15. Mdhamini') }}</h2>
    @if ($isSw)
        <p>Mdhamini amekagua wajibu wa Mkopaji na, kwa kuzingatia sheria, anakubali kuwajibika kwa pamoja na kwa kila mmoja. Hiyo inamaanisha Kopafasta inaweza kudai malipo kutoka kwa Mkopaji, Mdhamini, au wote wawili, bila kwanza kumaliza urejeshaji dhidi ya mmoja, kwa kiwango kinachoruhusiwa na sheria. Wajibu unaweza kujumuisha msingi, riba, adhabu na gharama za urejeshaji zilizoingia na kurekodiwa ipasavyo. Udhamini unaendelea hadi wajibu uliodhaminiwa utakapotimilika kabisa, kwa kuzingatia sheria.</p>
    @else
        <p>The Guarantor has reviewed the Borrower's obligations and, subject to applicable law, agrees to be jointly and severally liable. That means Kopafasta may seek payment from the Borrower, the Guarantor, or both, without first exhausting recovery against one of them, to the extent permitted by law. Liability may include principal, interest, penalties and recovery charges that have properly accrued and been posted. The guarantee remains until the guaranteed obligations are fully discharged, subject to applicable law.</p>
    @endif
    @if (filled($clauses['guarantor_clause'] ?? null))
        <p>{{ $clauses['guarantor_clause'] }}</p>
    @endif
@endif

@if ($showGroup)
<h2>{{ $t('16. Group loans', '16. Mikopo ya kikundi') }}</h2>
<p><strong>{{ $t('Group', 'Kikundi') }}:</strong> {{ $snapshot['group_name'] ?? '—' }} · {{ $t('Total allocation', 'Jumla ya mgao') }} {{ format_money($snapshot['total_group_liability'] ?? 0) }}</p>
@if (($snapshot['group_members'] ?? []) !== [])
<table class="grid">
    <thead>
        <tr>
            <th>{{ $t('Role', 'Wadhifa') }}</th>
            <th>{{ $t('Name', 'Jina') }}</th>
            <th>NIDA</th>
            <th>{{ $t('Phone', 'Simu') }}</th>
            <th>{{ $t('Allocation', 'Mgao') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($snapshot['group_members'] as $member)
            <tr>
                <td>{{ $roleLabel($member['role'] ?? null) }}</td>
                <td>{{ $member['name'] ?? '—' }}</td>
                <td>{{ $member['national_id'] ?? '—' }}</td>
                <td>{{ $member['phone'] ?? '—' }}</td>
                <td>{{ format_money($member['requested_amount'] ?? 0) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
@endif
<p>{{ $isSw ? ($recovery['group_liability_sw'] ?? '') : ($recovery['group_liability_en'] ?? '') }}</p>
<p>{{ $t('Every member of the group must sign this Agreement. The group leader’s signature is not a signature for the other members.', 'Kila mwanachama wa kikundi lazima asaini Mkataba huu. Saini ya kiongozi si saini ya wanachama wengine.') }}</p>
@endif

@if ($showCollateral)
<h2>{{ $t('17. Collateral and asset financing', '17. Dhamana na ufadhili wa mali') }}</h2>
<table class="kv">
    <tr><td class="label">{{ $t('Asset', 'Mali') }}</td><td class="value">{{ $snapshot['asset_title'] ?? $snapshot['collateral_description'] ?? '—' }}</td></tr>
    <tr><td class="label">{{ $t('Serial / registration', 'Namba ya serial / usajili') }}</td><td class="value">{{ $snapshot['asset_serial_number'] ?? '—' }}</td></tr>
    <tr><td class="label">{{ $t('Chassis / engine', 'Chasi / injini') }}</td><td class="value">{{ $snapshot['asset_chassis_number'] ?? '—' }} / {{ $snapshot['asset_engine_number'] ?? '—' }}</td></tr>
    <tr><td class="label">{{ $t('Market / forced sale value', 'Thamani ya soko / ya kuuza kwa lazima') }}</td><td class="value">{{ format_money($snapshot['collateral_market_value'] ?? 0) }} / {{ format_money($snapshot['collateral_forced_sale_value'] ?? 0) }}</td></tr>
    <tr><td class="label">{{ $t('Insurance / GPS', 'Bima / GPS') }}</td><td class="value">{{ $snapshot['asset_insurance_policy'] ?? '—' }} / {{ ! empty($snapshot['collateral_gps_required']) ? $t('Required', 'Inahitajika') : $t('Not required', 'Haihitajiki') }}</td></tr>
</table>
@if ($isSw)
    <p>Mkopaji hatauza, kuhamisha, kuficha, kubadilisha kwa kiasi kikubwa au kuondoa dhamana bila ridhaa halali inapohitajika. Pale haki halali ya kurejesha mali inapotokea, Kopafasta inaweza kuchukua hatua zinazoruhusiwa na sheria. Mapato ya mauzo yanatumika kulipa wajibu uliobaki; ziada au upungufu unashughulikiwa kwa mujibu wa sheria. Mkopaji anaendelea kuwajibika kwa upungufu wowote halali.</p>
@else
    <p>Where a lawful right of repossession arises after the applicable reminder, cure and recovery process has not resolved an Event of Default, Kopafasta may take steps permitted by law to realize the pledged collateral toward settlement of outstanding obligations. Collateral is security for repayment — not the business objective of the loan. Immediate repossession is not automatic upon a single missed payment. Sale proceeds are applied to outstanding obligations; surplus or shortfall is dealt with according to law. The Borrower remains liable for any lawful shortfall.</p>
@endif
@if (filled($clauses['asset_recovery_clause'] ?? null))
    <p>{{ $clauses['asset_recovery_clause'] }}</p>
@endif
@endif

@if ($on('data_privacy'))
<h2>{{ $t('18–19. Credit reference, data and communications', '18–19. Kumbukumbu za mikopo, data na mawasiliano') }}</h2>
@if ($isSw)
    <p>Kwa kuzingatia sheria, Kopafasta inaweza kuchakata na kutoa taarifa husika za mkopo kwa mifumo iliyoidhinishwa ya kumbukumbu za mikopo, wadhibiti, watoa huduma wenye leseni na wapokeaji wengine wanaoruhusiwa kisheria kwa ajili ya tathmini ya mkopo, usimamizi, kuzuia udanganyifu, uzingatiaji wa kanuni, urejeshaji na mawasiliano na mteja. Historia ya malipo na ukiukaji inaweza kuripotiwa pale inaporuhusiwa au inavyotakiwa kisheria. Kopafasta haina haki ya kuambia mtu yeyote kuhusu deni bila msingi wa kisheria.</p>
    <p>Kopafasta inaweza kuwasiliana na Mkopaji kupitia taarifa alizotoa: simu, SMS, ujumbe ndani ya programu, barua pepe pale ilipotolewa, na ziara halali. Pale Mdhamini au Mwanachama wa Kikundi anapostahili taarifa, Kopafasta inaweza kuwasiliana nao kwa usimamizi au urejeshaji halali, kwa kuzingatia sheria.</p>
@else
    <p>Subject to applicable law, Kopafasta may process and disclose relevant credit information to authorised credit-reference systems, regulators, licensed service providers and other lawful recipients for credit assessment, administration, fraud prevention, regulatory compliance, recovery and customer communication. Payment history and default may be reported where legally permitted or required. Kopafasta does not have a blanket right to tell anyone about the debt.</p>
    <p>Kopafasta may contact the Borrower through the details provided: phone, SMS, in-app messages, email where provided, and lawful visits. Where a Guarantor or Group Member is entitled to information, Kopafasta may contact them for legitimate administration or recovery, subject to law.</p>
@endif
@endif

<h2>{{ $t('20–22. Borrower duties, continued default, legal proceedings', '20–22. Wajibu wa mkopaji, ukiukaji unaoendelea, hatua za kisheria') }}</h2>
@if ($isSw)
    <p>Mkopaji atatoa taarifa sahihi, kulipa kwa wakati, kuweka taarifa za mawasiliano zikiwa za sasa, kushirikiana na uthibitishaji na urejeshaji halali, kutunza dhamana pale inapohusika, na kutoizuia urejeshaji halali. Ikiwa ukiukaji unaendelea, Kopafasta inaweza kukumbusha na kudai malipo, kupeleka hatua ya urejeshaji, kurekodi gharama zilizofichuliwa zinapochochewa, kuwasiliana na mdhamini, kuchukua hatua halali za dhamana, kushirikisha mtoa huduma wa urejeshaji aliyeidhinishwa, kufungua kesi, na kuripoti kwa mfumo ulioidhinishwa wa kumbukumbu za mikopo pale inaporuhusiwa. Kuchukua hatua moja hakuzuii nyingine halali. Mkataba huu ni halali na unatekelezwa mbele ya mahakama au mamlaka nyingine yenye uwezo ya {{ $jurisdiction }}. Hakuna kilicho hapa kinachoruhusu hatua iliyokatazwa na sheria.</p>
@else
    <p>The Borrower shall provide accurate information, pay on time, keep contact details current, cooperate with lawful verification and recovery, maintain collateral where applicable, and not obstruct lawful recovery. If default continues, Kopafasta may remind and demand payment, assign a recovery stage, post disclosed charges when triggered, contact a guarantor, take lawful collateral action, engage an authorised recovery provider, commence legal proceedings, and report to an authorised credit-reference system where permitted. Taking one action does not prevent another lawful action. This Agreement is valid and enforceable before a court or other competent authority of {{ $jurisdiction }}. Nothing here permits an action prohibited by law.</p>
@endif

<h2>{{ $t('23. Complaints', '23. Malalamiko') }}</h2>
<table class="kv">
    <tr><td class="label">{{ $t('Phone', 'Simu') }}</td><td class="value">{{ $snapshot['complaints_phone'] ?? '—' }}</td></tr>
    <tr><td class="label">Email</td><td class="value">{{ $snapshot['complaints_email'] ?? '—' }}</td></tr>
    <tr><td class="label">{{ $t('Physical address', 'Anwani ya ofisi') }}</td><td class="value">{{ $snapshot['complaints_address'] ?? '—' }}</td></tr>
</table>
<p>{{ $t('Complaints are handled under Kopafasta’s complaints procedure and applicable consumer-protection requirements.', 'Malalamiko yanashughulikiwa chini ya taratibu za malalamiko za Kopafasta na mahitaji ya ulinzi wa watumiaji.') }}</p>

<h2>{{ $t('24. Electronic execution', '24. Utiaji saini wa kielektroniki') }}</h2>
@if ($isSw)
    <p>Wahusika wanaweza kusaini Mkataba huu kwa njia ya kielektroniki. Saini ya kwenye kifaa, uthibitisho wa PIN, au njia nyingine iliyoidhinishwa na Kopafasta ni ushahidi wa nia ya kuingia Mkataba huu, kwa kuzingatia Sheria ya Miamala ya Kielektroniki na sheria nyingine. PDF iliyosainiwa kielektroniki ndiyo kumbukumbu rasmi iliyotekelezwa.</p>
@else
    <p>The parties may execute this Agreement electronically. A signature-pad signature, PIN confirmation, or other approved electronic method used by Kopafasta constitutes evidence of intention to enter into this Agreement, subject to the Electronic Transactions Act and other applicable law. The electronically executed PDF is the authoritative executed record.</p>
@endif

<h2>{{ $t('25. Settings snapshot', '25. Toleo la masharti') }}</h2>
@if ($isSw)
    <p>Masharti ya kifedha na kimkataba yaliyomo yanatokana na mipangilio iliyokuwa inatumika wakati wa kutengenezwa na kukubaliwa (toleo la waraka {{ $snapshot['document_version'] ?? '—' }}). Mabadiliko ya baadaye ya mipangilio ya ndani ya Kopafasta hayabadilishi kiotomatiki Mkataba uliosainiwa. Marekebisho yoyote yatafanywa kwa mujibu wa sheria.</p>
@else
    <p>The financial and contractual terms in this Agreement are those in force when it is generated and accepted (document version {{ $snapshot['document_version'] ?? '—' }}). Later changes to Kopafasta’s internal settings do not automatically amend an already executed Agreement. Any amendment must be made in accordance with applicable law.</p>
@endif

<h2>{{ $t('26. No waiver', '26. Kutotumia haki') }}</h2>
<p>{{ $t('Delay or failure to exercise a right is not a waiver.', 'Kuchelewa au kushindwa kutumia haki si kuiacha.') }}</p>

<h2>{{ $t('27. Severability', '27. Utenganishaji') }}</h2>
<p>{{ $t('If a provision is invalid, the rest of this Agreement continues.', 'Iwapo kifungu kimoja ni batili, vingine vinaendelea.') }}</p>

<h2>{{ $t('28. Entire agreement', '28. Mkataba mzima') }}</h2>
<p>{{ $t('This Agreement, the Offer Letter, Facility Schedule and annexes are the agreement concerning the Loan.', 'Mkataba huu, Barua ya Ofa, Jedwali la Huduma na viambatisho ndio makubaliano kuhusu Mkopo.') }}</p>

@if ($on('jurisdiction'))
<h2>{{ $t('29. Governing law', '29. Sheria inayotumika') }}</h2>
<p>{{ $t('This Agreement is governed by the laws of', 'Mkataba huu unatawaliwa na sheria za') }} <strong>{{ $jurisdiction }}</strong>.</p>
@endif

<h2>{{ $t('30. Language', '30. Lugha') }}</h2>
@if ($isSw)
    <p>Toleo hili limetolewa katika Kiswahili. Toleo la lugha nyingine lina maana ileile; iwapo kuna tofauti, <strong>toleo la Kiingereza ndilo litakalotawala</strong> kwa kiwango kinachoruhusiwa na sheria.</p>
@else
    <p>This version is issued in English. The other language version has the same meaning; if there is an inconsistency, the <strong>English version prevails</strong> to the extent permitted by law.</p>
@endif

<h2>{{ $t('31. Borrower’s key acknowledgements', '31. Uthibitisho wa mkopaji') }}</h2>
<p>{{ $t('Before signing, the Borrower confirms they have had the opportunity to review and understand:', 'Kabla ya kusaini, Mkopaji anathibitisha alipata fursa ya kuyasoma na kuyaelewa:') }}</p>
<ol class="acks">
    <li>{{ $t('the principal amount, interest rate, total repayable, frequency, instalment amount and due dates;', 'kiasi cha msingi, kiwango cha riba, jumla inayolipwa, mzunguko, kiasi cha awamu na tarehe za malipo;') }}</li>
    <li>{{ $t('the grace period, penalty after grace, and penalty cap;', 'muda wa msamaha, adhabu baada ya msamaha, na kizuizi cha adhabu;') }}</li>
    <li>{{ $t('that recovery charges may arise after escalation and are additional to amounts already owed, and are added only when a stage is actually assigned and posted;', 'kwamba gharama za urejeshaji zinaweza kutokea baada ya kupandishwa hatua na ni za ziada juu ya kiasi kinachodaiwa, na zinaongezwa pale tu hatua inapopelekwa na kurekodiwa;') }}</li>
    <li>{{ $t('the consequences of continued default;', 'madhara ya ukiukaji unaoendelea;') }}</li>
    <li>{{ $t('guarantor / group / collateral obligations where applicable;', 'wajibu wa mdhamini / kikundi / dhamana pale inapohusika;') }}</li>
    <li>{{ $t('credit-reference reporting where applicable; and', 'kuripoti kwenye kumbukumbu za mikopo pale inapohusika; na') }}</li>
    <li>{{ $t('the complaint channels in section 23.', 'njia za malalamiko katika kifungu cha 23.') }}</li>
</ol>
<p><strong>{{ $t('I confirm that I have read, understood and accepted the above terms.', 'Ninathibitisha nimesoma, nimeelewa na nimekubali masharti hayo.') }}</strong></p>

@if ($on('signatures'))
<h2>{{ $t('32. Acceptance', '32. Kukubali') }}</h2>
<p>{{ $t('By signing below or completing the approved electronic acceptance (signature pad and PIN where required), the parties confirm they have read, understood and accepted this Agreement.', 'Kwa kusaini hapa chini au kukamilisha kukubali kwa njia ya kielektroniki (saini kwenye kifaa na PIN pale inapohitajika), wahusika wanathibitisha wamesoma, wameelewa na wamekubali Mkataba huu.') }}</p>

@include('pdf.loan-agreement._signatories')
@endif

<div class="annex">
    <h2>{{ $t('Annex A — Facility and repayment schedule', 'Kiambatisho A — Jedwali la mkopo na marejesho') }}</h2>
    <table class="kv">
        <tr><td class="label">{{ $t('Principal', 'Msingi') }}</td><td class="value">{{ format_money($snapshot['principal'] ?? 0) }}</td></tr>
        <tr><td class="label">{{ $t('Rate / tenure / cadence', 'Riba / muda / mzunguko') }}</td><td class="value">{{ format_number(($snapshot['displayed_monthly_rate'] ?? 0) * 100, 2) }}% · {{ (int) ($snapshot['tenure_months'] ?? 0) }} {{ $t('months', 'miezi') }} · {{ $cadenceLabel }}</td></tr>
        <tr><td class="label">{{ $t('Instalment / total', 'Awamu / jumla') }}</td><td class="value">{{ format_money($snapshot['estimated_emi'] ?? 0) }} · {{ format_money($snapshot['total_repayable'] ?? 0) }}</td></tr>
    </table>
    @if (! empty($snapshot['repayment_schedule']))
        @if (! empty($snapshot['schedule_is_estimate']) && ! $isFinal)
            <p class="muted">{{ $t('Pre-disbursement estimate. Dated due dates are issued after disbursement.', 'Makadirio kabla ya utoaji. Tarehe kamili za malipo hutolewa baada ya utoaji.') }}</p>
        @endif
        <table class="grid">
            <thead>
                <tr>
                    <th>{{ $t('No.', 'Na.') }}</th>
                    <th>{{ $t('Due date', 'Tarehe') }}</th>
                    <th>{{ $t('Principal', 'Msingi') }}</th>
                    <th>{{ $t('Interest', 'Riba') }}</th>
                    <th>{{ $t('Instalment', 'Awamu') }}</th>
                    <th>{{ $t('Balance', 'Salio') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($snapshot['repayment_schedule'] as $row)
                    <tr>
                        <td>{{ $row['installment_no'] ?? $row['label'] ?? '' }}</td>
                        <td>{{ $row['due_date'] ?? '—' }}</td>
                        <td>{{ format_money($row['principal_due'] ?? 0) }}</td>
                        <td>{{ format_money($row['interest_due'] ?? 0) }}</td>
                        <td>{{ format_money($row['total_due'] ?? 0) }}</td>
                        <td>{{ format_money($row['outstanding_balance'] ?? 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="annex">
    <h2>{{ $t('Annex B — Charges and default summary', 'Kiambatisho B — Muhtasari wa ada na ukiukaji') }}</h2>
    <table class="charges">
        <tr><td>{{ $t('Interest rate', 'Kiwango cha riba') }}</td><td>{{ format_number(($snapshot['displayed_monthly_rate'] ?? 0) * 100, 2) }}% {{ $t('per month', 'kwa mwezi') }}</td></tr>
        <tr><td>{{ $t('Grace period', 'Muda wa msamaha') }}</td><td>{{ $graceDays }} {{ $t('calendar days', 'siku za kalenda') }}</td></tr>
        <tr><td>{{ $t('Penalty', 'Adhabu') }}</td><td>{{ format_number($snapshot['penalty_rate'] ?? 0, 2) }}% {{ $isSw ? ($snapshot['penalty_basis_label_sw'] ?? '') : ($snapshot['penalty_basis_label'] ?? 'per day') }} {{ $t('on the first overdue instalment remainder', 'kwenye salio la awamu ya kwanza iliyochelewa') }}</td></tr>
        <tr><td>{{ $t('Penalty cap', 'Kizuizi cha adhabu') }}</td><td>{{ $penaltyCap }}% {{ $t('of all overdue instalment remainders', 'ya salio zote za awamu zilizochelewa') }}</td></tr>
        @foreach ($stages as $stage)
            <tr><td>{{ $isSw ? ($stage['label_sw'] ?? $stage['label'] ?? '') : ($stage['label_en'] ?? $stage['label'] ?? '') }}</td><td>{{ $isSw ? ($stage['display_sw'] ?? '') : ($stage['display_en'] ?? '') }}</td></tr>
        @endforeach
        @foreach ($facilityCharges as $charge)
            <tr><td>{{ $charge['name'] ?? $charge['code'] ?? '' }}</td><td>{{ $isSw ? ($charge['display_sw'] ?? '') : ($charge['display_en'] ?? '') }}</td></tr>
        @endforeach
        @if ($showGpsFee)
            <tr>
                <td>{{ $t('GPS (post-approval)', 'GPS (baada ya kuidhinishwa)') }}</td>
                <td>{{ format_money($snapshot['gps_fee']['total'] ?? 0) }} · {{ $t('install + monthly × tenure', 'usakinishaji + kila mwezi × muda') }}</td>
            </tr>
        @endif
        @if (filled($clauses['collection_charge'] ?? null))
            <tr><td>{{ $t('Collection charge note', 'Maelezo ya gharama ya ufuatiliaji') }}</td><td>{{ $clauses['collection_charge'] }}</td></tr>
        @endif
        @if (filled($clauses['legal_recovery'] ?? null))
            <tr><td>{{ $t('Legal recovery note', 'Maelezo ya urejeshaji wa kisheria') }}</td><td>{{ $clauses['legal_recovery'] }}</td></tr>
        @endif
    </table>
    <p class="muted">{{ $t('A recovery charge becomes payable only when the relevant recovery stage is actually initiated and the charge is posted, subject to applicable law. Third-party costs that Kopafasta is permitted to pass through shall not exceed actual amounts paid to those third parties.', 'Gharama ya urejeshaji inadaiwa pale tu hatua husika inapoanzishwa na gharama inarekodiwa, kwa kuzingatia sheria. Gharama za wahusika wengine ambazo Kopafasta inaruhusiwa kuzipitisha hazitazidi kiasi halisi kilicholipwa kwa wahusika hao.') }}</p>
</div>

@if ($showGpsFee)
<div class="annex">
    <h2>{{ $t('Annex — GPS post-approval fee', 'Kiambatisho — Ada ya GPS baada ya kuidhinishwa') }}</h2>
    <table class="kv">
        <tr><td class="label">{{ $t('Installation', 'Usakinishaji') }}</td><td class="value">{{ format_money($snapshot['gps_fee']['install_amount'] ?? 0) }}</td></tr>
        <tr><td class="label">{{ $t('Monthly monitoring', 'Ufuatiliaji wa kila mwezi') }}</td><td class="value">{{ format_money($snapshot['gps_fee']['monthly_amount'] ?? 0) }} × {{ (int) ($snapshot['gps_fee']['months'] ?? 0) }} {{ $t('months', 'miezi') }}</td></tr>
        <tr><td class="label">{{ $t('Total before disbursement', 'Jumla kabla ya utoaji') }}</td><td class="value">{{ format_money($snapshot['gps_fee']['total'] ?? 0) }}</td></tr>
    </table>
    <p>{{ $isSw ? ($snapshot['gps_fee']['display_sw'] ?? '') : ($snapshot['gps_fee']['display_en'] ?? '') }}</p>
</div>
@endif

@if (! empty($snapshot['is_asset_loan']))
<div class="annex">
    <h2>{{ $t('Annex C — Collateral details', 'Kiambatisho C — Maelezo ya dhamana') }}</h2>
    <table class="kv">
        <tr><td class="label">{{ $t('Asset', 'Mali') }}</td><td class="value">{{ $snapshot['asset_title'] ?? $snapshot['collateral_description'] ?? '—' }}</td></tr>
        <tr><td class="label">{{ $t('Identifier', 'Kitambulisho') }}</td><td class="value">{{ $snapshot['asset_serial_number'] ?? '—' }}</td></tr>
        <tr><td class="label">{{ $t('Value', 'Thamani') }}</td><td class="value">{{ format_money($snapshot['collateral_market_value'] ?? 0) }}</td></tr>
        <tr><td class="label">{{ $t('GPS / insurance', 'GPS / bima') }}</td><td class="value">{{ ! empty($snapshot['collateral_gps_required']) ? $t('Required', 'Inahitajika') : $t('Not required', 'Haihitajiki') }} / {{ $snapshot['asset_insurance_policy'] ?? '—' }}</td></tr>
    </table>
</div>
@endif

<div class="annex">
    <h2>{{ $t('Electronic execution record', 'Kumbukumbu ya utekelezaji wa kielektroniki') }}</h2>
    <table class="kv">
        <tr><td class="label">{{ $t('Contract ID', 'Namba ya mkataba') }}</td><td class="value">{{ $agreement->reference }}</td></tr>
        <tr><td class="label">{{ $t('Document version', 'Toleo la waraka') }}</td><td class="value">{{ $snapshot['document_version'] ?? '—' }}</td></tr>
        <tr><td class="label">{{ $t('Generated at', 'Imetengenezwa') }}</td><td class="value">{{ $snapshot['generated_at'] ?? '—' }}</td></tr>
        <tr><td class="label">{{ $t('Accepted at', 'Imekubaliwa') }}</td><td class="value">{{ $agreement->signed_at?->toIso8601String() ?? ($snapshot['contract_signed_at'] ?? '—') }}</td></tr>
        <tr><td class="label">{{ $t('Borrower phone', 'Simu ya mkopaji') }}</td><td class="value">{{ $snapshot['customer_phone'] ?? '—' }}</td></tr>
        <tr><td class="label">{{ $t('PIN verification', 'Uthibitisho wa PIN') }}</td><td class="value">{{ ($snapshot['contract_execution_method'] ?? $agreement->signature_method ?? '') === 'pin' || ! empty($snapshot['pin_verified']) ? $t('PIN confirmed', 'PIN imethibitishwa') : (($agreement->isSigned() ? ucfirst((string) ($agreement->signature_method ?? 'direct')) : $t('Pending', 'Inasubiri'))) }}</td></tr>
        <tr><td class="label">{{ $t('Terms hash', 'Hash ya masharti') }}</td><td class="value" style="font-size:8px;word-break:break-all">{{ $snapshot['terms_hash'] ?? '—' }}</td></tr>
        <tr><td class="label">{{ $t('File hash', 'Hash ya faili') }}</td><td class="value" style="font-size:8px;word-break:break-all">{{ $snapshot['document_hash'] ?? $t('Recorded after generation', 'Inarekodiwa baada ya kutengenezwa') }}</td></tr>
        <tr><td class="label">{{ $t('IP (if collected)', 'IP (ikiwa imekusanywa)') }}</td><td class="value">{{ $snapshot['contract_signed_ip'] ?? $agreement->signed_ip ?? '—' }}</td></tr>
    </table>
    <p class="muted">{{ $t('This record forms part of the electronic execution record for this Agreement.', 'Kumbukumbu hii ni sehemu ya utekelezaji wa Mkataba huu kwa njia ya kielektroniki.') }}</p>
</div>
