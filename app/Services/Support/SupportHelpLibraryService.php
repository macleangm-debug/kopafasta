<?php

namespace App\Services\Support;

use App\Models\Customer;
use App\Models\Setting;
use App\Services\PortalContextService;

/**
 * Self-service Help Centre library (Member + Partner).
 * Settings override: support.help_library
 */
class SupportHelpLibraryService
{
    public const SETTING_KEY = 'support.help_library';

    /**
     * @return list<array<string, mixed>>
     */
    public function groups(string $audience = 'member', ?string $workspace = null): array
    {
        $stored = Setting::get(self::SETTING_KEY);
        $groups = $this->defaults();
        if (is_array($stored) && $stored !== []) {
            $hasNewShape = collect($stored)->contains(
                fn ($g) => is_array($g) && (($g['key'] ?? '') === 'apply-loan' || isset($g['articles']))
            );
            if ($hasNewShape) {
                $groups = $stored;
                $keys = collect($groups)->pluck('key');
                if (! $keys->contains('getting-started')) {
                    $reg = collect($this->defaults())->firstWhere('key', 'getting-started');
                    if (is_array($reg)) {
                        array_unshift($groups, $reg);
                    }
                }
            }
        }

        $groups = collect($groups)
            ->map(fn (array $g) => $this->normalizeGroup($g))
            ->filter(function (array $g) use ($audience) {
                $aud = (string) ($g['audience'] ?? 'both');

                return $aud === 'both' || $aud === $audience || $aud === 'public';
            })
            ->values()
            ->all();

        // Always resolve Products from the live active catalogue (never a second hard-coded store).
        $groups = $this->injectProductsCategory($groups, $audience);

        if ($audience === 'partner' && filled($workspace)) {
            $aliases = match ((string) $workspace) {
                'service' => ['service', 'insurance', 'recovery', 'valuer'],
                'insurance', 'recovery', 'valuer' => [(string) $workspace, 'service'],
                default => [(string) $workspace],
            };
            $groups = collect($groups)
                ->filter(function (array $g) use ($aliases) {
                    $workspaces = $g['workspaces'] ?? null;
                    if (! is_array($workspaces) || $workspaces === []) {
                        // Shared/common partner topics (account, registration, etc.).
                        return in_array((string) ($g['audience'] ?? 'both'), ['both', 'partner', 'public'], true)
                            && ! in_array((string) ($g['key'] ?? ''), ['apply-loan', 'guarantors', 'group-loans', 'repayments', 'collateral', 'marketplace', 'plus', 'rewards', 'products'], true);
                    }

                    return array_intersect($workspaces, $aliases) !== [];
                })
                ->values()
                ->all();
        }

        return $groups;
    }

    /**
     * @return list<array{key:string,label:string,icon:string,topic_count:int,audience:string}>
     */
    public function categories(string $audience = 'member', ?string $locale = null, ?string $workspace = null): array
    {
        $isSw = $this->isSw($locale);

        return collect($this->groups($audience, $workspace))
            ->map(function (array $g) use ($isSw) {
                $articles = $g['articles'] ?? [];

                return [
                    'key' => (string) ($g['key'] ?? ''),
                    'label' => $isSw
                        ? (string) ($g['label_sw'] ?? $g['label_en'] ?? '')
                        : (string) ($g['label_en'] ?? $g['label_sw'] ?? ''),
                    'icon' => (string) ($g['icon'] ?? '📘'),
                    'topic_count' => count($articles),
                    'audience' => (string) ($g['audience'] ?? 'both'),
                ];
            })
            ->filter(fn (array $c) => $c['key'] !== '')
            ->values()
            ->all();
    }

    public function category(string $key, string $audience = 'member', ?string $workspace = null): ?array
    {
        return collect($this->groups($audience, $workspace))
            ->first(fn (array $g) => ($g['key'] ?? '') === $key);
    }

    public function article(string $categoryKey, string $slug, string $audience = 'member', ?string $workspace = null): ?array
    {
        $group = $this->category($categoryKey, $audience, $workspace);
        if (! $group) {
            return null;
        }

        $article = collect($group['articles'] ?? [])
            ->first(fn (array $a) => ($a['slug'] ?? '') === $slug);

        if (! $article) {
            return null;
        }

        return array_merge($article, [
            'category_key' => $categoryKey,
            'category_label_en' => $group['label_en'] ?? '',
            'category_label_sw' => $group['label_sw'] ?? '',
        ]);
    }

    /**
     * @return list<array{type:string,group:string,category:string,slug:string,title:string,body:string,steps?:list<string>,url:string}>
     */
    public function searchable(string $audience = 'member', ?string $locale = null): array
    {
        $isSw = $this->isSw($locale);
        $rows = [];

        foreach ($this->groups($audience) as $group) {
            $groupLabel = $isSw
                ? (string) ($group['label_sw'] ?? $group['label_en'] ?? '')
                : (string) ($group['label_en'] ?? $group['label_sw'] ?? '');
            $categoryKey = (string) ($group['key'] ?? '');

            foreach ($group['articles'] ?? [] as $article) {
                $slug = (string) ($article['slug'] ?? '');
                $kind = (string) ($article['kind'] ?? 'answer');
                $title = $isSw
                    ? (string) ($article['q_sw'] ?? $article['q_en'] ?? $article['title_sw'] ?? $article['title_en'] ?? '')
                    : (string) ($article['q_en'] ?? $article['q_sw'] ?? $article['title_en'] ?? $article['title_sw'] ?? '');
                $body = $isSw
                    ? (string) ($article['a_sw'] ?? $article['a_en'] ?? '')
                    : (string) ($article['a_en'] ?? $article['a_sw'] ?? '');
                $steps = $isSw
                    ? ($article['steps_sw'] ?? $article['steps_en'] ?? [])
                    : ($article['steps_en'] ?? $article['steps_sw'] ?? []);

                $rows[] = [
                    'type' => $kind === 'howto' ? 'howto' : 'faq',
                    'group' => $groupLabel,
                    'category' => $categoryKey,
                    'slug' => $slug,
                    'title' => $title,
                    'body' => $body,
                    'steps' => array_values(array_filter(array_map('strval', (array) $steps))),
                    'url' => $slug !== '' && $categoryKey !== ''
                        ? route('site.help.article', ['category' => $categoryKey, 'slug' => $slug])
                        : route('site.help.category', ['category' => $categoryKey]),
                ];
            }
        }

        return $rows;
    }

    /**
     * @return list<array{type:string,group:string,category:string,slug:string,title:string,body:string,steps?:list<string>,url:string}>
     */
    public function search(string $query, string $audience = 'member', ?string $locale = null): array
    {
        $q = mb_strtolower(trim($query));
        if ($q === '') {
            return [];
        }

        return collect($this->searchable($audience, $locale))
            ->filter(function (array $row) use ($q) {
                $hay = mb_strtolower(implode(' ', [
                    $row['type'] ?? '',
                    $row['group'] ?? '',
                    $row['title'] ?? '',
                    $row['body'] ?? '',
                    implode(' ', $row['steps'] ?? []),
                ]));

                return str_contains($hay, $q);
            })
            ->take(24)
            ->values()
            ->all();
    }

    /**
     * Soft prioritisation — recommendation only.
     *
     * @return list<array{category:string,slug:string,title:string,url:string}>
     */
    public function recommended(?Customer $customer, string $audience = 'member', ?string $locale = null): array
    {
        $isSw = $this->isSw($locale);
        $keys = [];

        if ($customer && $audience === 'member') {
            $portal = app(PortalContextService::class);
            if ($portal->pendingGuarantorLinks($customer)->isNotEmpty()) {
                $keys[] = ['guarantors', 'accept-request'];
                $keys[] = ['guarantors', 'complete-guarantor-profile'];
            }

            $inScreening = \App\Models\LoanApplication::query()
                ->where('customer_id', $customer->id)
                ->where(function ($q) {
                    $q->where('current_stage', 'screening')
                        ->orWhereIn('status', ['submitted', 'screening']);
                })
                ->exists();

            if ($inScreening) {
                $keys[] = ['apply-loan', 'what-is-screening'];
                $keys[] = ['apply-loan', 'submit-requested-documents'];
            }

            $awaitingFee = \App\Models\LoanApplication::query()
                ->where('customer_id', $customer->id)
                ->whereIn('status', ['awaiting_application_fee', 'awaiting_valuation_fee', 'awaiting_payment'])
                ->exists();
            if ($awaitingFee) {
                $keys[] = ['fees-payments', 'application-fee'];
                $keys[] = ['fees-payments', 'payment-pending'];
            }
        }

        if ($keys === []) {
            $keys = [
                ['apply-loan', 'how-to-apply'],
                ['profile-verification', 'complete-profile'],
                ['fees-payments', 'how-to-pay'],
            ];
        }

        $out = [];
        foreach ($keys as [$cat, $slug]) {
            $article = $this->article($cat, $slug, $audience);
            if (! $article) {
                continue;
            }
            $title = $isSw
                ? (string) ($article['q_sw'] ?? $article['q_en'] ?? '')
                : (string) ($article['q_en'] ?? $article['q_sw'] ?? '');
            $out[] = [
                'category' => $cat,
                'slug' => $slug,
                'title' => $title,
                'url' => route('site.help.article', ['category' => $cat, 'slug' => $slug]),
            ];
            if (count($out) >= 4) {
                break;
            }
        }

        return $out;
    }

    public function howtoLabel(?string $locale = null): string
    {
        return $this->isSw($locale) ? 'JINSI YA' : 'HOW TO';
    }

    /**
     * @return list<array{key:string,label_en:string,label_sw:string,audience:string,icon:string,articles:list<array>}>
     */
    public function defaults(): array
    {
        return [
            $this->cat('getting-started', 'Registration', 'Kujisajili', 'both', '🆕', [
                $this->howto('open-account', 'Registration', 'Usajili',
                    'How do I open an account?', 'Jinsi ya kujisajili',
                    'Open an account', 'Kujisajili',
                    'Register with your mobile number and personal details, then set a PIN and security answers. Kopafasta does not send an SMS code during registration.',
                    'Jisajili kwa namba ya simu na taarifa binafsi, kisha weka PIN na majibu ya usalama. Kopafasta haitumi msimbo wa SMS wakati wa kujisajili.',
                    ['Open Register / Jiunge', 'Choose your country and enter your mobile number', 'Enter your first name, last name and gender', 'Create a private 4-digit PIN and confirm it', 'Answer the security questions (used later if you forget your PIN)', 'Continue to your account'],
                    ['Fungua Jiunge', 'Chagua nchi na weka namba yako ya simu', 'Weka jina la kwanza, jina la mwisho na jinsia', 'Unda PIN ya tarakimu 4 ya faragha na ithibitishe', 'Jibu maswali ya usalama (yatatumika baadaye ukisahau PIN)', 'Endelea kwenye akaunti yako']),
                $this->answer('phone-number', 'Registration', 'Usajili',
                    'Which phone number should I use?', 'Namba ya simu gani inayotumika?',
                    'Use a mobile number you control. This number is your login identity — there is no SMS verification code at registration.',
                    'Tumia namba ya simu unayodhibiti. Namba hii ni utambulisho wako wa kuingia — hakuna msimbo wa SMS wa kuthibitisha wakati wa kujisajili.'),
                $this->answer('phone-already-used', 'Registration', 'Usajili',
                    'My phone number is already in use', 'Namba ya simu tayari imetumika',
                    'Sign in with that number, or use Forgot PIN if you cannot access it. Talk to Support if the number belongs to you but you never registered.',
                    'Ingia kwa namba hiyo, au tumia Nimesahau PIN ikiwa huwezi kufikia. Ongea na Usaidizi ikiwa namba ni yako lakini hukujisajili.'),
                $this->howto('set-pin', 'Registration', 'Usajili',
                    'How do I set a PIN?', 'Ninawezaje kuweka PIN?',
                    'Set a PIN', 'Kuweka PIN',
                    'After personal details, Kopafasta asks you to create a PIN, then security questions for recovery.',
                    'Baada ya taarifa binafsi, Kopafasta inakuuliza uunde PIN, kisha maswali ya usalama kwa urejeshaji.',
                    ['Enter a 4-digit PIN', 'Confirm the same PIN', 'Answer the security questions', 'Do not share your PIN with anyone'],
                    ['Weka PIN ya tarakimu 4', 'Thibitisha PIN ile ile', 'Jibu maswali ya usalama', 'Usishiriki PIN yako na mtu yeyote']),
                $this->howto('sign-in', 'Login', 'Kuingia',
                    'How do I sign in after registering?', 'Jinsi ya kuingia baada ya kujisajili',
                    'Sign in', 'Kuingia',
                    'Use the same phone number and PIN from registration.',
                    'Tumia namba ile ile ya simu na PIN kutoka usajili.',
                    ['Open Sign in / Ingia', 'Enter your registered phone number', 'Enter your PIN', 'Continue to your account'],
                    ['Fungua Ingia', 'Weka namba ya simu iliyosajiliwa', 'Weka PIN yako', 'Endelea kwenye akaunti yako']),
                $this->howto('forgot-pin', 'Login', 'Kuingia',
                    'I forgot my PIN / access help', 'Nimesahau PIN / msaada wa kuingia',
                    'Reset a forgotten PIN', 'Kuweka upya PIN uliyosahau',
                    'Reset from the login screen using your security answers — not an SMS code.',
                    'Weka upya kutoka skrini ya kuingia kwa majibu ya usalama — si msimbo wa SMS.',
                    ['Open Sign in', 'Tap Forgot PIN / Nimesahau PIN', 'Enter your registered phone number', 'Answer your security questions', 'Choose a new 4-digit PIN'],
                    ['Fungua Ingia', 'Gusa Nimesahau PIN', 'Weka namba ya simu iliyosajiliwa', 'Jibu maswali yako ya usalama', 'Chagua PIN mpya ya tarakimu 4']),
                $this->howto('first-details', 'Registration', 'Usajili',
                    'How do I complete initial account setup?', 'Ninawezaje kukamilisha taarifa za mwanzo?',
                    'Complete first details', 'Kukamilisha taarifa za mwanzo',
                    'Your name and phone are captured during registration. After PIN and security questions, open Profile to add ID, residence and income before applying.',
                    'Jina na simu zinachukuliwa wakati wa kujisajili. Baada ya PIN na maswali ya usalama, fungua Wasifu kuongeza kitambulisho, makazi na mapato kabla ya kuomba.',
                    ['Finish PIN and security questions', 'Open Profile', 'Add identity, residence and income', 'Save before leaving', 'Continue into Loans or Dashboard'],
                    ['Maliza PIN na maswali ya usalama', 'Fungua Wasifu', 'Ongeza utambulisho, makazi na mapato', 'Hifadhi kabla ya kuondoka', 'Endelea Mikopo au Dashibodi']),
                $this->answer('registration-problem', 'Registration', 'Usajili',
                    'I have a problem while registering', 'Tatizo wakati wa kujisajili',
                    'Confirm your phone digits and network, then try again. Registration does not wait for an SMS code. If you already started, use the same number to resume PIN setup. If stuck, Talk to Support or Call us.',
                    'Hakiki tarakimu za simu na mtandao, kisha jaribu tena. Usajili hausubiri msimbo wa SMS. Ikiwa ulishaanza, tumia namba ile ile kuendelea na kuweka PIN. Bado ukikwama, Ongea na Usaidizi au Piga simu.'),
            ]),
            $this->cat('apply-loan', 'Applying for a loan', 'Kuomba mkopo', 'member', '📋', [
                $this->howto('how-to-apply', 'Getting started', 'Kuanza',
                    'How do I apply for a loan?', 'Ninawezaje kuomba mkopo?',
                    'Apply for a loan', 'Kuomba mkopo',
                    'Open Loans, pick a product, complete the steps, then submit.',
                    'Fungua Mikopo, chagua bidhaa, kamilisha hatua, kisha wasilisha.',
                    ['Open Loans', 'Choose a loan product', 'Tap Apply', 'Complete required steps', 'Review your details', 'Submit the application'],
                    ['Fungua Mikopo', 'Chagua bidhaa ya mkopo', 'Gusa Omba', 'Kamilisha hatua zinazohitajika', 'Hakiki taarifa zako', 'Wasilisha ombi'],
                    'site.borrower.loan-products', 'Browse loan products', 'Omba mkopo'),
                $this->answer('who-can-apply', 'Getting started', 'Kuanza',
                    'Who can apply?', 'Ni nani anayeweza kuomba?',
                    'Active members with a complete profile can apply for products they are eligible for. Some products need a guarantor or collateral.',
                    'Wanachama hai wenye wasifu kamili wanaweza kuomba bidhaa wanazostahili. Baadhi ya bidhaa zinahitaji mdhamini au dhamana.'),
                $this->answer('how-much-can-i-borrow', 'Getting started', 'Kuanza',
                    'How much can I borrow?', 'Ninaweza kukopa kiasi gani?',
                    'Each product shows its min/max amount. Your offer depends on Screening, income, and product rules — not a fixed promise at apply time.',
                    'Kila bidhaa inaonyesha kiasi cha chini/juu. Ofa inategemea Uchunguzi, mapato, na sheria za bidhaa — si ahadi thabiti wakati wa kuomba.'),
                $this->answer('what-documents', 'Getting started', 'Kuanza',
                    'What documents do I need?', 'Ninahitaji nyaraka gani?',
                    'Start with a complete Profile (ID, face, residence, income). Some products also need guarantor, collateral, or asset documents.',
                    'Anza na Wasifu kamili (kitambulisho, uso, makazi, mapato). Baadhi ya bidhaa zinahitaji pia mdhamini, dhamana, au hati za mali.'),
                $this->answer('application-stage', 'After applying', 'Baada ya kuomba',
                    'What stage is my application at?', 'Ombi langu liko hatua gani?',
                    'Open Loans → your application. The Application View shows the current stage and any actions waiting on you.',
                    'Fungua Mikopo → ombi lako. Muonekano wa Ombi unaonyesha hatua ya sasa na vitendo vinavyokusubiri.'),
                $this->answer('what-is-screening', 'After applying', 'Baada ya kuomba',
                    'What is Screening?', 'Screening ni nini?',
                    'Screening is our first review of your submitted application. We may ask for extra documents before Committee or Management.',
                    'Screening ni ukaguzi wa kwanza wa ombi ulilowasilisha. Tunaweza kuomba nyaraka za ziada kabla ya Kamati au Usimamizi.'),
                $this->howto('submit-requested-documents', 'After applying', 'Baada ya kuomba',
                    'How do I submit requested documents?', 'Ninawezaje kuwasilisha nyaraka zilizoombwa?',
                    'Submit requested documents', 'Kuwasilisha nyaraka zilizoombwa',
                    'Use the document request card on your Application View.',
                    'Tumia kadi ya ombi la nyaraka kwenye Muonekano wa Ombi.',
                    ['Open the application', 'Find the requested-document card', 'Tap + / Upload', 'Select files', 'Submit — the same card moves to received'],
                    ['Fungua ombi', 'Tafuta kadi ya ombi la nyaraka', 'Gusa + / Pakia', 'Chagua faili', 'Wasilisha — kadi ile ile inahamia imepokelewa'],
                    'site.borrower.loans', 'Open Loans', 'Fungua Mikopo'),
                $this->answer('application-rejected', 'After applying', 'Baada ya kuomba',
                    'My application was rejected — what does that mean?', 'Ombi langu limekataliwa — maana yake nini?',
                    'Rejection means this application cannot continue under current rules. Read the reason on Application View. You may apply again later if eligible.',
                    'Kukataliwa kunamaanisha ombi hili haliwezi kuendelea chini ya sheria za sasa. Soma sababu kwenye Muonekano wa Ombi. Unaweza kuomba tena baadaye ukistahili.'),
                $this->answer('what-is-offer', 'After applying', 'Baada ya kuomba',
                    'What is an Offer?', 'Offer ni nini?',
                    'An Offer is the approved loan terms we present for you to accept or decline before contract and disbursement.',
                    'Ofa ni masharti ya mkopo yaliyoidhinishwa tunayokuletea ukubali au ukatae kabla ya mkataba na malipo.'),
            ]),
            $this->cat('profile-verification', 'Profile & verification', 'Wasifu na uthibitishaji', 'both', '🪪', [
                $this->howto('complete-profile', 'Profile', 'Wasifu',
                    'How do I complete my profile?', 'Ninawezaje kukamilisha wasifu?',
                    'Complete your profile', 'Kukamilisha wasifu',
                    'A complete profile speeds Screening.',
                    'Wasifu kamili huharakisha Uchunguzi.',
                    ['Open Profile', 'Fill personal and contact details', 'Upload identity documents', 'Add residence and income', 'Save — look for the saved confirmation'],
                    ['Fungua Wasifu', 'Jaza taarifa binafsi na mawasiliano', 'Pakia hati za utambulisho', 'Ongeza makazi na mapato', 'Hifadhi — angalia uthibitisho wa kuhifadhi'],
                    'site.borrower.profile', 'Open Profile', 'Fungua Wasifu'),
                $this->answer('id-documents', 'Profile', 'Wasifu',
                    'Which ID do I need?', 'Ninahitaji kitambulisho gani?',
                    'Use a valid national ID (or permitted alternative). Clear front/back photos and a face check are usually required.',
                    'Tumia kitambulisho cha taifa halali (au mbadala unaoruhusiwa). Picha wazi za mbele/nyuma na ukaguzi wa uso mara nyingi zinahitajika.'),
                $this->answer('change-details', 'Profile', 'Wasifu',
                    'How do I change my details?', 'Ninawezaje kubadilisha taarifa zangu?',
                    'Update editable fields in Profile. Some verified identity fields are locked — Talk to Support if a correction is needed.',
                    'Sasisha sehemu zinazoweza kuhaririwa kwenye Wasifu. Baadhi ya sehemu za utambulisho zilizothibitishwa zimefungwa — Ongea na Usaidizi ikiwa marekebisho yanahitajika.'),
                $this->answer('signature', 'Verification', 'Uthibitishaji',
                    'Where do I set my signature?', 'Ninaweka wapi sahihi yangu?',
                    'Create or reuse your legal signature from Profile. Loan contracts and guarantor acceptance may require it.',
                    'Unda au tumia tena sahihi yako ya kisheria kutoka Wasifu. Mikataba ya mkopo na kukubali udhamini inaweza kuihitaji.'),
            ]),
            $this->cat('guarantors', 'Guarantors', 'Wadhamini', 'member', '🤝', [
                $this->howto('add-guarantor', 'Inviting', 'Kualika',
                    'How do I add a guarantor?', 'Ninawezaje kuongeza mdhamini?',
                    'Add a guarantor', 'Kuongeza mdhamini',
                    'Invite from the open application — not a separate account type.',
                    'Alika kutoka ombi lililo wazi — si aina tofauti ya akaunti.',
                    ['Open the application', 'Go to Guarantors', 'Enter name and phone', 'Send the invitation', 'Wait for acceptance and profile completion'],
                    ['Fungua ombi', 'Nenda Wadhamini', 'Weka jina na simu', 'Tuma mwaliko', 'Subiri kukubaliwa na kukamilisha wasifu'],
                    'site.borrower.loans', 'Open Loans', 'Fungua Mikopo'),
                $this->howto('accept-request', 'As guarantor', 'Kama mdhamini',
                    'How do I accept a guarantor request?', 'Ninawezaje kukubali ombi la udhamini?',
                    'Accept a guarantor request', 'Kukubali ombi la udhamini',
                    'Use the Guarantor Request notification, then open Mikopo → Mdhamini.',
                    'Tumia arifa ya Ombi la udhamini, kisha fungua Mikopo → Mdhamini.',
                    ['Open the notification Angalia ombi la udhamini', 'Go to Loans → Guarantor requests', 'Tap View on the request card', 'Review loan overview and liability', 'Accept or decline'],
                    ['Fungua arifa Angalia ombi la udhamini', 'Nenda Mikopo → Maombi ya udhamini', 'Gusa Angalia kwenye kadi', 'Hakiki muhtasari na dhamana', 'Kubali au kataa'],
                    'site.borrower.loans', 'Open Loans', 'Fungua Mikopo'),
                $this->howto('complete-guarantor-profile', 'As guarantor', 'Kama mdhamini',
                    'What do I do after accepting?', 'Nifanye nini baada ya kukubali?',
                    'Complete guarantor profile', 'Kukamilisha wasifu wa mdhamini',
                    'After accept, finish your profile so the borrower application can proceed.',
                    'Baada ya kukubali, kamilisha wasifu ili ombi la mkopaji liendelee.',
                    ['Accept the request', 'Open Profile', 'Complete missing sections', 'Return to Loans to track progress'],
                    ['Kubali ombi', 'Fungua Wasifu', 'Kamilisha sehemu zinazokosekana', 'Rudi Mikopo kufuatilia maendeleo'],
                    'site.borrower.profile', 'Complete profile', 'Kamilisha wasifu'),
                $this->answer('replace-guarantor', 'Inviting', 'Kualika',
                    'How do I replace a guarantor?', 'Ninawezaje kubadilisha mdhamini?',
                    'If the invite was declined or expired, use Choose another / Edit on Application View (before acceptance locks edit).',
                    'Ikiwa mwaliko umekataliwa au umekwisha, tumia Chagua mwingine / Hariri kwenye Muonekano wa Ombi (kabla ya kukubaliwa kufunga uhariri).'),
                $this->answer('guarantor-deadline', 'Inviting', 'Kualika',
                    'What is the guarantor deadline?', 'Deadline ya mdhamini ni nini?',
                    'Some products give the guarantor a time window to accept and complete. Track it on Application View and remind via WhatsApp if needed.',
                    'Baadhi ya bidhaa zinampa mdhamini muda wa kukubali na kukamilisha. Fuatilia kwenye Muonekano wa Ombi na kumbusha kwa WhatsApp inapohitajika.'),
            ]),
            $this->cat('group-loans', 'Group loans', 'Mikopo ya kikundi', 'member', '👥', [
                $this->howto('join-group', 'Joining', 'Kujiunga',
                    'How do I join a group loan?', 'Ninawezaje kujiunga na mkopo wa kikundi?',
                    'Join a group loan', 'Kujiunga na mkopo wa kikundi',
                    'Use the group invitation notification → Loans → Angalia.',
                    'Tumia arifa ya mwaliko wa kikundi → Mikopo → Angalia.',
                    ['Open Angalia ombi la kikundi', 'Review group overview', 'Accept the invitation', 'Complete your profile', 'Sign when asked'],
                    ['Fungua Angalia ombi la kikundi', 'Hakiki muhtasari wa kikundi', 'Kubali mwaliko', 'Kamilisha wasifu', 'Weka sahihi unapoulizwa'],
                    'site.borrower.loans', 'Open Loans', 'Fungua Mikopo'),
                $this->answer('leader-vs-member', 'Roles', 'Majukumu',
                    'What is the difference between leader and member?', 'Kuna tofauti gani kati ya kiongozi na mwanachama?',
                    'The leader starts the group application and invites members. Members accept, complete profile, and sign their part.',
                    'Kiongozi anaanzisha ombi la kikundi na kualika wanachama. Wanachama wanakubali, kukamilisha wasifu, na kuweka sahihi yao.'),
                $this->answer('group-application-fee', 'Fees', 'Ada',
                    'Who pays the group application fee?', 'Nani analipa ada ya ombi la kikundi?',
                    'Follow the fee instruction on the group application. Do not invent a second payment outside Payments.',
                    'Fuata maelekezo ya ada kwenye ombi la kikundi. Usianzishe malipo ya pili nje ya Malipo.'),
            ]),
            $this->cat('fees-payments', 'Fees & payments', 'Ada na malipo', 'both', '💳', [
                $this->howto('how-to-pay', 'Making a payment', 'Kufanya malipo',
                    'How do I make a payment?', 'Ninafanyaje malipo?',
                    'Make a payment', 'Kufanya malipo',
                    'Stay on the payment screen until the provider confirms.',
                    'Kaa kwenye skrini ya malipo hadi mtoa huduma athibitishe.',
                    ['Open Payments', 'Select what you are paying', 'Confirm amount', 'Enter mobile money number', 'Approve on your phone', 'Wait for Paid / Failed status'],
                    ['Fungua Malipo', 'Chagua unacholipia', 'Thibitisha kiasi', 'Weka nambari ya simu', 'Idhinisha kwenye simu', 'Subiri hali ya Imelipwa / Imeshindikana'],
                    'site.borrower.payments', 'Open Payments', 'Fungua Malipo'),
                $this->answer('application-fee', 'Fees', 'Ada',
                    'What is the application fee?', 'Ada ya maombi ni nini?',
                    'Some products charge an application fee before or during submission. Pay only from the payment screen shown in your journey.',
                    'Baadhi ya bidhaa zinatoza ada ya maombi kabla au wakati wa kuwasilisha. Lipa tu kutoka skrini ya malipo inayoonekana kwenye safari yako.'),
                $this->answer('payment-pending', 'Problems', 'Matatizo',
                    'Why is my payment pending?', 'Kwa nini malipo yangu yamesimama (Pending)?',
                    'Pending means we are waiting for the payment provider. Do not pay twice. Wait for Paid, Failed, or Expired — then retry if needed.',
                    'Pending inamaanisha tunasubiri mtoa huduma. Usilipe mara mbili. Subiri Imelipwa, Imeshindikana, au Imekwisha — kisha jaribu tena inapohitajika.'),
                $this->answer('payment-failed', 'Problems', 'Matatizo',
                    'What if payment fails?', 'Malipo yakishindikana?',
                    'Use Retry or change number on the same payment surface. Check balance and PIN on your mobile money account.',
                    'Tumia Jaribu tena au badilisha nambari kwenye skrini ile ile. Angalia salio na PIN kwenye akaunti yako ya simu.'),
                $this->answer('payment-not-showing', 'Problems', 'Matatizo',
                    'I paid but my payment is not showing', 'Nimelipa lakini malipo hayajaonekana',
                    'Confirm the payment reference on your phone, wait a few minutes, then refresh Payments. Do not pay again. If it still does not appear, we will open an investigation ticket.',
                    'Thibitisha rejea ya malipo kwenye simu yako, subiri dakika chache, kisha onyesha upya Malipo. Usilipe tena. Ikiwa bado haionekani, tutafungua tiketi ya uchunguzi.',
                    null, null, null, true),
                $this->answer('payment-paid-twice', 'Problems', 'Matatizo',
                    'I paid twice / duplicate payment', 'Nimelipa mara mbili',
                    'Do not make another payment. Keep both receipt references. Support will investigate and reverse or apply the duplicate where policy allows.',
                    'Usifanye malipo mengine. Hifadhi rejea zote mbili. Usaidizi utachunguza na kurejesha au kutumia nakala inaporuhusiwa.',
                    null, null, null, true),
                $this->answer('fee-unclear', 'Fees', 'Ada',
                    'I do not understand a fee', 'Ada sielewi',
                    'Open the payment or application screen that listed the fee. Amounts come from product settings — Support cannot invent a different fee.',
                    'Fungua skrini ya malipo au ombi iliyoonyesha ada. Kiasi kinatoka kwenye mipangilio ya bidhaa — Usaidizi hauwezi kubuni ada tofauti.'),
                $this->answer('receipts', 'Records', 'Rekodi',
                    'Where are my receipts?', 'Risiti zangu ziko wapi?',
                    'Open Payments history for confirmed payments. Successful payments show a receipt reference.',
                    'Fungua historia ya Malipo kwa malipo yaliyothibitishwa. Malipo yaliyofanikiwa yanaonyesha rejea ya risiti.'),
            ]),
            $this->cat('after-approval', 'After approval', 'Baada ya kuidhinishwa', 'member', '✅', [
                $this->answer('accept-offer', 'Offer', 'Ofa',
                    'How do I accept an Offer?', 'Ninawezaje kukubali Ofa?',
                    'Open the application, review Offer terms, then Accept. Declining stops that offer path.',
                    'Fungua ombi, hakiki masharti ya Ofa, kisha Kubali. Kukataa kunasimamisha njia hiyo ya ofa.'),
                $this->answer('post-approval-fees', 'Fees', 'Ada',
                    'What fees come after approval?', 'Ada gani zinakuja baada ya kuidhinishwa?',
                    'Depending on product: valuation, insurance, or other post-approval fees. Pay only from the checklist on your application.',
                    'Kulingana na bidhaa: utathmini, bima, au ada nyingine baada ya idhini. Lipa tu kutoka orodha kwenye ombi lako.'),
                $this->answer('contract-disbursement', 'Disbursement', 'Malipo',
                    'When do I get the money?', 'Nitapata lini pesa?',
                    'After Offer acceptance, required fees, signatures, and disbursement checks. Track checklist items on Application View.',
                    'Baada ya kukubali Ofa, ada zinazohitajika, sahihi, na ukaguzi wa malipo. Fuatilia orodha kwenye Muonekano wa Ombi.'),
            ]),
            $this->cat('repayments', 'Repayments', 'Marejesho', 'member', '📅', [
                $this->answer('due-dates', 'Schedule', 'Ratiba',
                    'Where do I see due dates?', 'Ninaona wapi tarehe za malipo?',
                    'Open Loans → active loan. The schedule shows installment dates and amounts.',
                    'Fungua Mikopo → mkopo hai. Ratiba inaonyesha tarehe na kiasi cha awamu.'),
                $this->howto('pay-installment', 'Paying', 'Kulipa',
                    'How do I pay an installment?', 'Ninawezaje kulipa awamu?',
                    'Pay an installment', 'Kulipa awamu',
                    'Use Payments or Pay loan from your account.',
                    'Tumia Malipo au Lipa mkopo kutoka akaunti yako.',
                    ['Open Payments or the active loan', 'Choose the installment / amount', 'Enter mobile money number', 'Approve on your phone', 'Confirm Paid status'],
                    ['Fungua Malipo au mkopo hai', 'Chagua awamu / kiasi', 'Weka nambari ya simu', 'Idhinisha kwenye simu', 'Thibitisha hali ya Imelipwa'],
                    'site.borrower.payments', 'Open Payments', 'Fungua Malipo'),
                $this->answer('late-payment', 'Problems', 'Matatizo',
                    'What if I pay late?', 'Nikichelewa kulipa?',
                    'Late payments may attract penalties per product rules. Pay as soon as you can and Talk to Support if you need a restructuring option.',
                    'Malipo yaliyochelewa yanaweza kuvuta faini kulingana na sheria za bidhaa. Lipa haraka unavyoweza na Ongea na Usaidizi ikiwa unahitaji urekebishaji.'),
            ]),
            $this->cat('collateral', 'Collateral / assets', 'Dhamana / mali', 'member', '🏠', [
                $this->answer('collateral-required', 'Basics', 'Misingi',
                    'When is collateral required?', 'Dhamana inahitajika lini?',
                    'When the product or Screening requires it. You will see collateral steps on the application.',
                    'Bidhaa au Uchunguzi vinapohitaji. Utaona hatua za dhamana kwenye ombi.'),
                $this->answer('valuation', 'Valuation', 'Utathmini',
                    'What is valuation?', 'Utathmini ni nini?',
                    'An assigned valuer reviews the asset. There may be a valuation fee and a waiting period before Screening continues.',
                    'Mthamini aliyepewa anakagua mali. Kunaweza kuwa na ada ya utathmini na muda wa kusubiri kabla ya Uchunguzi kuendelea.'),
                $this->answer('what-happens-collateral', 'Basics', 'Misingi',
                    'What happens to my collateral?', 'Inatokea nini kwa dhamana yangu?',
                    'Collateral secures the loan under your agreement. Release follows repayment and product rules — not informal promises.',
                    'Dhamana inalinda mkopo chini ya makubaliano yako. Kuachiliwa kunafuata marejesho na sheria za bidhaa — si ahadi zisizo rasmi.'),
            ]),
            $this->cat('marketplace', 'Asset marketplace', 'Soko la mali', 'member', '🛒', [
                $this->answer('asset-financing', 'Basics', 'Misingi',
                    'How does asset financing work?', 'Ufadhili wa mali unafanyaje kazi?',
                    'Browse Marketplace, select an asset linked to a product, then continue the loan application for that asset.',
                    'Vinjari Soko, chagua mali inayohusiana na bidhaa, kisha endelea na ombi la mkopo kwa mali hiyo.'),
                $this->answer('deposit-supplier', 'Purchase journey', 'Safari ya ununuzi',
                    'What about deposit and supplier?', 'Je, amana na msambazaji?',
                    'Follow deposit and supplier steps shown on the asset application. Pay only through Kopafasta payment screens.',
                    'Fuata hatua za amana na msambazaji zinazoonekana kwenye ombi la mali. Lipa tu kupitia skrini za malipo za Kopafasta.'),
            ]),
            $this->cat('account-security', 'Account & security', 'Akaunti na usalama', 'both', '🔐', [
                $this->howto('reset-pin', 'Login', 'Kuingia',
                    'How do I reset my PIN?', 'Ninawezaje kuweka upya PIN?',
                    'Reset PIN', 'Kuweka upya PIN',
                    'Use Forgot PIN on the login screen and answer your security questions.',
                    'Tumia Nimesahau PIN kwenye skrini ya kuingia na jibu maswali yako ya usalama.',
                    ['Open login', 'Tap Forgot PIN / Nimesahau PIN', 'Enter your registered phone number', 'Answer your security questions', 'Choose a new 4-digit PIN'],
                    ['Fungua kuingia', 'Gusa Nimesahau PIN', 'Weka namba ya simu iliyosajiliwa', 'Jibu maswali yako ya usalama', 'Chagua PIN mpya ya tarakimu 4']),
                $this->answer('cannot-login', 'Login', 'Kuingia',
                    'I cannot log in — what should I do?', 'Siwezi kuingia — nifanye nini?',
                    'Confirm registered phone and PIN. Try Forgot PIN. If still blocked, Talk to Support.',
                    'Hakikisha simu na PIN zilizosajiliwa. Jaribu Umesahau PIN. Bado ukizuiwa, Ongea na Usaidizi.'),
                $this->answer('lost-phone', 'Security', 'Usalama',
                    'I lost my phone — what now?', 'Nimepoteza simu — sasa nini?',
                    'Contact Support immediately so we can secure the account. You will need identity checks to restore access.',
                    'Wasiliana na Usaidizi mara moja ili tusalimishe akaunti. Utahitaji ukaguzi wa utambulisho kurejesha ufikiaji.'),
            ]),
            $this->cat('plus', 'Kopafasta Plus', 'Kopafasta Plus', 'member', '✨', [
                $this->answer('what-is-plus', 'Basics', 'Misingi',
                    'What is Kopafasta Plus?', 'Kopafasta Plus ni nini?',
                    'Plus is learning and growth for members: Money, Business, Goals, Reports, Offers and Rewards where enabled.',
                    'Plus ni kujifunza na kukua kwa wanachama: Pesa, Biashara, Malengo, Ripoti, Ofa na Zawadi zinapowezeshwa.'),
                $this->answer('join-plus', 'Basics', 'Misingi',
                    'How do I join Plus?', 'Ninawezaje kujiunga na Plus?',
                    'Open Plus from your account. Follow any payment or activation step shown there.',
                    'Fungua Plus kutoka akaunti yako. Fuata hatua yoyote ya malipo au uanzishaji inayoonekana huko.'),
            ]),
            $this->cat('rewards', 'Rewards & referrals', 'Zawadi na rufaa', 'member', '🎁', [
                $this->answer('how-referrals-work', 'Referrals', 'Rufaa',
                    'How do referrals work?', 'Rufaa zinafanyaje kazi?',
                    'Share your referral link from Rewards where enabled. Eligibility and rewards follow the current programme terms.',
                    'Shiriki kiungo chako cha rufaa kutoka Zawadi inapowezeshwa. Stahiki na zawadi zinafuata masharti ya programu ya sasa.'),
                $this->answer('claiming-rewards', 'Rewards', 'Zawadi',
                    'How do I claim rewards?', 'Ninawezaje kudai zawadi?',
                    'Open Rewards / Engagement and follow claim instructions for eligible items.',
                    'Fungua Zawadi / Ushiriki na fuata maelekezo ya kudai kwa vipengele unavyostahili.'),
            ]),
            $this->cat('complaints', 'Complaints & issues', 'Malalamiko na matatizo', 'both', '📣', [
                $this->answer('raise-complaint', 'Complaints', 'Malalamiko',
                    'How do I raise a complaint?', 'Ninawezaje kuwasilisha malalamiko?',
                    'Use Send feedback and choose Complaint, or Talk to Support. Serious issues may become a tracked Case.',
                    'Tumia Tuma maoni na chagua Malalamiko, au Ongea na Usaidizi. Masuala mazito yanaweza kuwa Kesi inayofuatiliwa.'),
                $this->answer('technical-issue', 'Technical', 'Kiufundi',
                    'I found a technical issue', 'Nimeona tatizo la kiufundi',
                    'Send feedback with what you tapped and what you expected. Screenshots help Support reproduce the issue.',
                    'Tuma maoni ukieleza ulichogusa na ulichotarajia. Picha za skrini zinasaidia Usaidizi kuzalisha tena tatizo.'),
                $this->answer('disputed-payment', 'Disputes', 'Migogoro',
                    'My payment or application looks wrong', 'Malipo au ombi langu linaonekana vibaya',
                    'Do not create a second payment. Talk to Support with the payment/application reference so we can investigate.',
                    'Usianzishe malipo ya pili. Ongea na Usaidizi ukiwa na rejea ya malipo/ombi ili tuchunguze.'),
            ]),
            $this->cat('partner-account', 'Partner account', 'Akaunti ya Mshirika', 'partner', '🏢', [
                $this->howto('become-partner', 'Getting started', 'Kuanza',
                    'How do I become a Partner or Affiliate?', 'Ninawezaje kuwa Mshirika au Affiliate?',
                    'Become a Partner / Affiliate', 'Kuwa Mshirika / Affiliate',
                    'Use the public Partner apply flow for Standard Affiliate and other open Partner roles, then track activation. Premium Affiliate is registered internally — it is not a public self-registration option.',
                    'Tumia mtiririko wa ombi la umma kwa Affiliate ya Standard na majukumu mengine ya Mshirika yaliyo wazi, kisha fuatilia uanzishaji. Affiliate ya Premium inasajiliwa ndani — si chaguo la kujisajili hadharani.',
                    ['Open Partner apply', 'Complete the form for the role you want', 'Submit documents', 'Wait for review', 'Accept agreement and activate'],
                    ['Fungua ombi la Mshirika', 'Kamilisha fomu kwa jukumu unalotaka', 'Wasilisha hati', 'Subiri ukaguzi', 'Kubali makubaliano na anzisha']),
                $this->answer('partner-wallet', 'Wallet', 'Pochi',
                    'Where is my Partner wallet?', 'Pochi yangu ya Mshirika iko wapi?',
                    'Open your Partner workspace wallet for available / pending balance and withdrawal history.',
                    'Fungua pochi ya nafasi yako ya Mshirika kwa salio linalopatikana / linalosubiri na historia ya uondoaji.'),
            ], ['affiliate', 'insurance', 'recovery', 'supplier', 'service', 'valuer', 'capital']),
            $this->cat('affiliate', 'Affiliate', 'Affiliate', 'partner', '🤝', [
                $this->answer('affiliate-how', 'Basics', 'Misingi',
                    'How does Affiliate work?', 'Affiliate inafanyaje kazi?',
                    'Affiliates refer members with a referral/promo code. Commission and discounts follow the current Affiliate configuration. Public information describes Standard Affiliate. Premium Affiliate terms apply only to authenticated Premium Affiliates.',
                    'Affiliate hurejelea wanachama kwa msimbo wa rufaa/promo. Kamisheni na punguzo zinafuata usanidi wa sasa wa Affiliate. Taarifa za umma zinaeleza Affiliate ya Standard. Masharti ya Premium yanatumika tu kwa Affiliate ya Premium iliyothibitishwa.'),
                $this->answer('affiliate-code', 'Referral', 'Rufaa',
                    'Where is my referral / promo code?', 'Msimbo wangu wa rufaa / promo uko wapi?',
                    'Open your Affiliate workspace to view and manage your referral code where editing is allowed.',
                    'Fungua nafasi yako ya Affiliate kuona na kudhibiti msimbo wako wa rufaa pale uhariri unaporuhusiwa.'),
                $this->answer('affiliate-commission', 'Commission', 'Kamisheni',
                    'What commission do I get?', 'Ninapata kamisheni gani?',
                    'Commission follows Affiliate Settings and your agreement. Authenticated Affiliates see their effective rate from account configuration — Support does not invent percentages.',
                    'Kamisheni inafuata Mipangilio ya Affiliate na makubaliano yako. Affiliate walioingia wanaona kiwango chao halisi kutoka usanidi wa akaunti — Usaidizi hautoi asilimia za kubuni.'),
                $this->answer('affiliate-earnings', 'Earnings', 'Mapato',
                    'Where do I see earnings and withdrawals?', 'Ninaona wapi mapato na uondoaji?',
                    'Open your Affiliate wallet for available / pending balance and withdrawal history. Withdrawal rules follow Payments / wallet Settings.',
                    'Fungua pochi yako ya Affiliate kwa salio linalopatikana / linalosubiri na historia ya uondoaji. Sheria za uondoaji zinafuata Mipangilio ya Malipo / pochi.'),
                $this->answer('affiliate-referrals', 'Tracking', 'Ufuatiliaji',
                    'How do I track referrals?', 'Ninafuatiliaje rufaa?',
                    'Open your Affiliate workspace for referrals, campaigns, and commission tracking.',
                    'Fungua nafasi yako ya Affiliate kwa rufaa, kampeni, na ufuatiliaji wa kamisheni.'),
                $this->answer('affiliate-profile', 'Account', 'Akaunti',
                    'Where is my Affiliate profile / agreement?', 'Wasifu / makubaliano yangu ya Affiliate yako wapi?',
                    'Agreements and account settings live under Partner Profile / account. The dashboard shows Needs Attention only when action is required.',
                    'Makubaliano na mipangilio ya akaunti yako chini ya Wasifu / akaunti ya Mshirika. Dashibodi inaonyesha Mahitaji ya Makini tu hatua inapohitajika.'),
            ], ['affiliate']),
            $this->cat('supplier', 'Asset Supplier', 'Msambazaji wa mali', 'partner', '📦', [
                $this->answer('supplier-how', 'Basics', 'Misingi',
                    'How does Asset Supplier work?', 'Msambazaji wa mali anafanyaje kazi?',
                    'Suppliers list marketplace assets, follow deposit and order steps, and receive payouts per marketplace configuration.',
                    'Wasambazaji huorodhesha mali kwenye soko, hufuata hatua za amana na oda, na hupokea malipo kulingana na usanidi wa soko.'),
                $this->howto('supplier-add-asset', 'Assets', 'Mali',
                    'How do I add an asset?', 'Ninawezaje kuongeza mali?',
                    'Add an asset', 'Ongeza mali',
                    'Open your Supplier workspace and follow the list-asset flow shown there.',
                    'Fungua nafasi yako ya Msambazaji na fuata mtiririko wa kuorodhesha mali unaoonekana huko.',
                    ['Open Supplier workspace', 'Start Add / list asset', 'Enter asset details and photos', 'Submit for review', 'Track status in Supplier orders'],
                    ['Fungua nafasi ya Msambazaji', 'Anza Ongeza / orodhesha mali', 'Weka maelezo na picha', 'Wasilisha kwa ukaguzi', 'Fuatilia hali kwenye oda za Msambazaji']),
                $this->answer('supplier-deposit', 'Deposits', 'Amana',
                    'How does the deposit work?', 'Amana inafanyaje kazi?',
                    'Buyer deposits follow Asset Lending / marketplace Settings (tiers and markup). Amounts shown on each deal come from that configuration — Support does not invent deposit figures.',
                    'Amana za mnunuzi zinafuata Mipangilio ya Ufadhili wa Mali / soko (ngazi na markup). Kiasi kwenye kila shughuli kinatokana na usanidi huo — Usaidizi hautoi kiasi cha amana cha kubuni.'),
                $this->answer('supplier-markup', 'Pricing', 'Bei',
                    'How does markup / commission work?', 'Markup / kamisheni inafanyaje kazi?',
                    'Markup and supplier earnings follow Marketplace / Asset Lending Settings and each asset deal. Authenticated Suppliers see applicable configured values when available.',
                    'Markup na mapato ya msambazaji yanafuata Mipangilio ya Soko / Ufadhili wa Mali na kila shughuli. Wasambazaji walioingia wanaona thamani zilizosanidiwa zinapopatikana.'),
                $this->answer('supplier-earnings', 'Earnings', 'Mapato',
                    'What do I earn / how am I paid?', 'Ninachuma nini / ninalipwaje?',
                    'Open Supplier wallet and orders for payouts. Commercial percentages come from Settings / deal configuration — never from free-typed chatbot copy.',
                    'Fungua pochi na oda za Msambazaji kwa malipo. Asilimia za kibiashara zinatokana na Mipangilio / usanidi wa shughuli — si nakala ya gumzo.'),
                $this->answer('supplier-orders', 'Orders', 'Oda',
                    'Where are marketplace orders / requests?', 'Oda / maombi ya soko yako wapi?',
                    'Open your Supplier workspace for marketplace orders and payment status.',
                    'Fungua nafasi yako ya Msambazaji kwa oda za soko na hali ya malipo.'),
                $this->answer('supplier-profile', 'Account', 'Akaunti',
                    'Where is my Supplier profile?', 'Wasifu wangu wa Msambazaji uko wapi?',
                    'Use Partner Profile / account for agreements and KYC. Dashboard Needs Attention appears only when action is required.',
                    'Tumia Wasifu / akaunti ya Mshirika kwa makubaliano na KYC. Mahitaji ya Makini yanaonekana tu hatua inapohitajika.'),
            ], ['supplier']),
            $this->cat('insurance', 'Insurance Partner', 'Mshirika wa Bima', 'partner', '🛡️', [
                $this->answer('insurance-how', 'Basics', 'Misingi',
                    'How does the insurance partnership work?', 'Ushirikiano wa bima unafanyaje kazi?',
                    'Insurance Partners handle assigned customer journeys and policies in the Insurance workspace.',
                    'Washirika wa Bima hushughulikia safari za wateja na sera zilizokabidhiwa kwenye nafasi ya Bima.'),
                $this->answer('insurance-products', 'Products', 'Bidhaa',
                    'Where do I see products / services?', 'Ninaona wapi bidhaa / huduma?',
                    'Open your Insurance workspace for products and services enabled for your partnership.',
                    'Fungua nafasi yako ya Bima kwa bidhaa na huduma zilizowezeshwa kwa ushirikiano wako.'),
                $this->answer('insurance-customers', 'Applications', 'Maombi',
                    'How do customer applications work?', 'Maombi ya wateja yanafanyaje?',
                    'Follow assigned journeys in your Insurance workspace. Status and next actions are shown there.',
                    'Fuata safari zilizokabidhiwa kwenye nafasi yako ya Bima. Hali na hatua zinazofuata zinaonekana huko.'),
                $this->answer('insurance-earnings', 'Earnings', 'Mapato',
                    'How do earnings / commission work?', 'Mapato / kamisheni yanafanyaje?',
                    'Where commission applies, amounts follow Partner / Insurance configuration and your wallet — Support does not invent rates.',
                    'Kamisheni inapotumika, kiasi kinafuata usanidi wa Mshirika / Bima na pochi yako — Usaidizi hautoi viwango vya kubuni.'),
                $this->answer('insurance-profile', 'Account', 'Akaunti',
                    'Where is my Insurance Partner profile?', 'Wasifu wangu wa Mshirika wa Bima uko wapi?',
                    'Agreements live under Partner Profile / account.',
                    'Makubaliano yako chini ya Wasifu / akaunti ya Mshirika.'),
            ], ['insurance', 'service']),
            $this->cat('recovery', 'Recovery Partner', 'Mshirika wa Urejesho', 'partner', '🧭', [
                $this->answer('recovery-assignments', 'Assignments', 'Kazi',
                    'How do recovery assignments work?', 'Kazi za urejesho zinafanyaje?',
                    'Open your Recovery / Collection workspace for assignments and next actions.',
                    'Fungua nafasi yako ya Urejesho / Ukusanyaji kwa kazi na hatua zinazofuata.'),
                $this->answer('recovery-process', 'Process', 'Mchakato',
                    'What is the recovery process?', 'Mchakato wa urejesho ni nini?',
                    'Follow the steps shown on each assignment. Do not invent fees or settlement amounts outside the case screens.',
                    'Fuata hatua zilizoonyeshwa kwenye kila kazi. Usibuni ada au kiasi cha suluhu nje ya skrini za kesi.'),
                $this->answer('recovery-fees', 'Fees', 'Ada',
                    'How do fees / earnings work?', 'Ada / mapato yanafanyaje?',
                    'Fees and earnings follow Recovery Partner configuration and each assignment. Support reads configured values — it does not invent them.',
                    'Ada na mapato yanafuata usanidi wa Mshirika wa Urejesho na kila kazi. Usaidizi husoma thamani zilizosanidiwa — hauzibuni.'),
                $this->answer('recovery-payments', 'Payments', 'Malipo',
                    'Where are payments / withdrawals?', 'Malipo / uondoaji viko wapi?',
                    'Use your Partner wallet for available / pending balance and withdrawal history.',
                    'Tumia pochi yako ya Mshirika kwa salio linalopatikana / linalosubiri na historia ya uondoaji.'),
                $this->answer('recovery-performance', 'Performance', 'Utendaji',
                    'Where do I see performance?', 'Ninaona wapi utendaji?',
                    'Open your Recovery workspace for assignment progress. KPI rules only apply where your Partner agreement requires them.',
                    'Fungua nafasi yako ya Urejesho kwa maendeleo ya kazi. Sheria za KPI zinatumika tu pale makubaliano yako yanapohitaji.'),
                $this->answer('recovery-profile', 'Account', 'Akaunti',
                    'Where is my Recovery Partner profile?', 'Wasifu wangu wa Mshirika wa Urejesho uko wapi?',
                    'Agreements live under Partner Profile / account.',
                    'Makubaliano yako chini ya Wasifu / akaunti ya Mshirika.'),
            ], ['recovery', 'service']),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     * @return list<array<string, mixed>>
     */
    private function injectProductsCategory(array $groups, string $audience): array
    {
        if ($audience === 'partner') {
            return $groups;
        }

        $productsCat = $this->buildProductsCategory();
        if ($productsCat === null) {
            return $groups;
        }

        if (collect($groups)->contains(fn (array $g) => ($g['key'] ?? '') === 'products')) {
            return collect($groups)
                ->map(fn (array $g) => ($g['key'] ?? '') === 'products' ? $productsCat : $g)
                ->values()
                ->all();
        }

        $out = [];
        $inserted = false;
        foreach ($groups as $g) {
            $out[] = $g;
            if (($g['key'] ?? '') === 'getting-started') {
                $out[] = $productsCat;
                $inserted = true;
            }
        }
        if (! $inserted) {
            array_unshift($out, $productsCat);
        }

        return $out;
    }

    private function buildProductsCategory(): ?array
    {
        if (! class_exists(\App\Models\LoanProduct::class)) {
            return null;
        }

        $query = \App\Models\LoanProduct::query()->where('is_active', true);
        if (\Illuminate\Support\Facades\Schema::hasColumn('loan_products', 'status')) {
            $query->where(function ($q) {
                $q->where('status', 'active')->orWhereNull('status')->orWhere('status', '');
            });
        }
        $products = $query->orderBy('name')->limit(40)->get();
        if ($products->isEmpty()) {
            return $this->cat('products', 'Loan products', 'Bidhaa za mikopo', 'member', '📦', [
                $this->answer('no-products', 'Loan products', 'Bidhaa za mikopo',
                    'Which products are available?', 'Bidhaa zipi zinapatikana?',
                    'Open Loans to see products currently offered. Availability follows Admin configuration.',
                    'Fungua Mikopo kuona bidhaa zinazotolewa sasa. Upatikanaji unafuata usanidi wa Admin.'),
            ]);
        }

        $articles = [];
        foreach ($products as $product) {
            $name = method_exists($product, 'localizedName')
                ? (string) $product->localizedName()
                : (string) ($product->name ?? 'Product');
            $slug = 'product-'.(string) ($product->code ?? $product->id);
            $min = $product->min_amount ?? null;
            $max = $product->max_amount ?? null;
            $needsG = (bool) ($product->requires_guarantor ?? false);
            $needsC = (bool) ($product->requires_collateral ?? false);
            $tenureMin = isset($product->tenure_min_months) ? (int) $product->tenure_min_months : null;
            $tenureMax = isset($product->tenure_max_months) ? (int) $product->tenure_max_months : null;
            $cadence = trim((string) ($product->repayment_cadence ?? ''));
            $rangeEn = ($min !== null && $max !== null)
                ? 'Typical amount range: '.format_money((float) $min).' – '.format_money((float) $max).'.'
                : 'Open the product card in Loans for current amount and tenure limits.';
            $rangeSw = ($min !== null && $max !== null)
                ? 'Kiasi cha kawaida: '.format_money((float) $min).' – '.format_money((float) $max).'.'
                : 'Fungua kadi ya bidhaa kwenye Mikopo kwa mipaka ya sasa ya kiasi na muda.';
            $tenureEn = ($tenureMin && $tenureMax)
                ? ($tenureMin === $tenureMax
                    ? "Duration: {$tenureMin} months."
                    : "Duration: {$tenureMin}–{$tenureMax} months.")
                : '';
            $tenureSw = ($tenureMin && $tenureMax)
                ? ($tenureMin === $tenureMax
                    ? "Muda: miezi {$tenureMin}."
                    : "Muda: miezi {$tenureMin}–{$tenureMax}.")
                : '';
            $cadenceEn = $cadence !== '' ? 'Repayment cadence: '.$cadence.'.' : '';
            $cadenceSw = $cadence !== '' ? 'Mpangilio wa marejesho: '.$cadence.'.' : '';
            $commercialEn = $this->productCommercialLine($product, false);
            $commercialSw = $this->productCommercialLine($product, true);
            $reqEn = collect([
                $needsG ? 'May require a guarantor.' : null,
                $needsC ? 'May require collateral.' : null,
            ])->filter()->implode(' ') ?: 'Requirements follow the product card and Screening steps.';
            $reqSw = collect([
                $needsG ? 'Inaweza kuhitaji mdhamini.' : null,
                $needsC ? 'Inaweza kuhitaji dhamana.' : null,
            ])->filter()->implode(' ') ?: 'Mahitaji yanafuata kadi ya bidhaa na hatua za Uchunguzi.';

            $summaryEn = trim(implode(' ', array_filter([$rangeEn, $tenureEn, $cadenceEn, $commercialEn, $reqEn])));
            $summarySw = trim(implode(' ', array_filter([$rangeSw, $tenureSw, $cadenceSw, $commercialSw, $reqSw])));

            $articles[] = $this->howto(
                $slug,
                'Loan products',
                'Bidhaa za mikopo',
                'How does '.$name.' work?',
                $name.' inafanyaje kazi?',
                $name,
                $name,
                $summaryEn.' Apply from Loans, complete required steps, then submit.',
                $summarySw.' Omba kutoka Mikopo, kamilisha hatua zinazohitajika, kisha wasilisha.',
                [
                    'Open Loans',
                    'Select '.$name,
                    'Review eligibility, amount, tenure, fees and rates on the product card',
                    'Tap Apply and complete required steps',
                    'Submit — Application View shows what happens next',
                ],
                [
                    'Fungua Mikopo',
                    'Chagua '.$name,
                    'Hakiki stahiki, kiasi, muda, ada na viwango kwenye kadi ya bidhaa',
                    'Gusa Omba na kamilisha hatua zinazohitajika',
                    'Wasilisha — Muonekano wa Ombi unaonyesha kinachofuata',
                ],
                'site.borrower.loan-products',
                'Browse products',
                'Angalia bidhaa'
            );
        }

        // Overview article first so “What loans do you offer?” is obvious.
        array_unshift($articles, $this->answer(
            'which-loans',
            'Loan products',
            'Bidhaa za mikopo',
            'What loans do you offer / which loan can I apply for?',
            'Mnatoa mikopo gani / ninaweza kuomba mkopo gani?',
            'Active products below reflect current Loan Product configuration. Open each product for amount, duration, fees and repayment options. Eligibility follows Screening — Support does not invent rates.',
            'Bidhaa hai hapa chini zinaonyesha usanidi wa sasa wa Bidhaa za Mikopo. Fungua kila bidhaa kwa kiasi, muda, ada na chaguo za marejesho. Stahiki inafuata Uchunguzi — Usaidizi hautoi viwango vya kubuni.'
        ));

        return $this->cat('products', 'Loan products', 'Bidhaa za mikopo', 'member', '📦', $articles);
    }

    /**
     * Live commercial summary from LoanProduct — omit anything not configured; never invent.
     */
    private function productCommercialLine(object $product, bool $sw): string
    {
        $bits = [];
        $hidesInterest = method_exists($product, 'hidesInterest')
            ? $product->hidesInterest()
            : (bool) ($product->hides_interest ?? false);

        if (! $hidesInterest && isset($product->interest_rate) && (float) $product->interest_rate > 0) {
            $rate = rtrim(rtrim(number_format((float) $product->interest_rate, 4, '.', ''), '0'), '.');
            $method = trim((string) ($product->interest_method ?? ''));
            $bits[] = $sw
                ? ('Kiwango cha riba (usanidi wa sasa): '.$rate.'%'.($method !== '' ? ' · '.$method : '').'.')
                : ('Interest rate (current config): '.$rate.'%'.($method !== '' ? ' · '.$method : '').'.');
        } elseif ($hidesInterest) {
            $bits[] = $sw
                ? 'Maelezo ya riba yanaonyeshwa kwenye kadi ya bidhaa pale yanaporuhusiwa.'
                : 'Interest details appear on the product card where disclosure is enabled.';
        }

        foreach ([
            'processing_fee_rate' => [$sw ? 'Ada ya usindikaji' : 'Processing fee', '%'],
            'service_fee_rate' => [$sw ? 'Ada ya huduma' : 'Service fee', '%'],
            'administration_fee_rate' => [$sw ? 'Ada ya usimamizi' : 'Administration fee', '%'],
        ] as $field => [$label, $suffix]) {
            if (isset($product->{$field}) && (float) $product->{$field} > 0) {
                $val = rtrim(rtrim(number_format((float) $product->{$field}, 4, '.', ''), '0'), '.');
                $bits[] = $label.': '.$val.$suffix.'.';
            }
        }
        if (isset($product->application_fee_amount) && (int) $product->application_fee_amount > 0) {
            $bits[] = ($sw ? 'Ada ya ombi: ' : 'Application fee: ')
                .format_money((float) $product->application_fee_amount).'.';
        }

        if ($bits === []) {
            return $sw
                ? 'Fungua kadi ya bidhaa kwa ada na viwango vilivyosanidiwa sasa.'
                : 'Open the product card for currently configured fees and rates.';
        }

        return implode(' ', $bits);
    }

    /**
     * @param  array<string, mixed>  $group
     * @return array<string, mixed>
     */
    private function normalizeGroup(array $group): array
    {
        $articles = $group['articles'] ?? null;
        if (! is_array($articles)) {
            $articles = [];
            foreach ($group['faqs'] ?? [] as $i => $faq) {
                if (! is_array($faq)) {
                    continue;
                }
                $articles[] = array_merge($faq, [
                    'slug' => $faq['slug'] ?? ('faq-'.($i + 1)),
                    'kind' => 'answer',
                    'subgroup_en' => $faq['subgroup_en'] ?? 'Questions',
                    'subgroup_sw' => $faq['subgroup_sw'] ?? 'Maswali',
                ]);
            }
            foreach ($group['howtos'] ?? [] as $i => $how) {
                if (! is_array($how)) {
                    continue;
                }
                $articles[] = array_merge($how, [
                    'slug' => $how['slug'] ?? ('howto-'.($i + 1)),
                    'kind' => 'howto',
                    'q_en' => $how['q_en'] ?? $how['title_en'] ?? '',
                    'q_sw' => $how['q_sw'] ?? $how['title_sw'] ?? '',
                    'subgroup_en' => $how['subgroup_en'] ?? 'How to',
                    'subgroup_sw' => $how['subgroup_sw'] ?? 'Jinsi ya',
                ]);
            }
        }

        $group['articles'] = array_values($articles);
        $group['icon'] = $group['icon'] ?? '📘';

        return $group;
    }

    private function isSw(?string $locale): bool
    {
        return str_starts_with(strtolower((string) ($locale ?: app()->getLocale())), 'sw');
    }

    /**
     * @param  list<array<string, mixed>>  $articles
     * @param  list<string>  $workspaces
     * @return array<string, mixed>
     */
    private function cat(string $key, string $en, string $sw, string $audience, string $icon, array $articles, array $workspaces = []): array
    {
        return [
            'key' => $key,
            'label_en' => $en,
            'label_sw' => $sw,
            'audience' => $audience,
            'icon' => $icon,
            'articles' => $articles,
            'workspaces' => $workspaces,
        ];
    }

    /**
     * @param  list<string>  $stepsEn
     * @param  list<string>  $stepsSw
     * @return array<string, mixed>
     */
    private function howto(
        string $slug,
        string $subEn,
        string $subSw,
        string $qEn,
        string $qSw,
        string $titleEn,
        string $titleSw,
        string $aEn,
        string $aSw,
        array $stepsEn,
        array $stepsSw,
        ?string $ctaRoute = null,
        ?string $ctaEn = null,
        ?string $ctaSw = null,
        bool $createsTicket = false,
    ): array {
        return [
            'slug' => $slug,
            'kind' => 'howto',
            'subgroup_en' => $subEn,
            'subgroup_sw' => $subSw,
            'q_en' => $qEn,
            'q_sw' => $qSw,
            'title_en' => $titleEn,
            'title_sw' => $titleSw,
            'a_en' => $aEn,
            'a_sw' => $aSw,
            'steps_en' => $stepsEn,
            'steps_sw' => $stepsSw,
            'cta_route' => $ctaRoute,
            'cta_label_en' => $ctaEn,
            'cta_label_sw' => $ctaSw,
            'creates_ticket' => $createsTicket,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function answer(
        string $slug,
        string $subEn,
        string $subSw,
        string $qEn,
        string $qSw,
        string $aEn,
        string $aSw,
        ?string $ctaRoute = null,
        ?string $ctaEn = null,
        ?string $ctaSw = null,
        bool $createsTicket = false,
    ): array {
        return [
            'slug' => $slug,
            'kind' => 'answer',
            'subgroup_en' => $subEn,
            'subgroup_sw' => $subSw,
            'q_en' => $qEn,
            'q_sw' => $qSw,
            'a_en' => $aEn,
            'a_sw' => $aSw,
            'steps_en' => [],
            'steps_sw' => [],
            'cta_route' => $ctaRoute,
            'cta_label_en' => $ctaEn,
            'cta_label_sw' => $ctaSw,
            'creates_ticket' => $createsTicket,
        ];
    }
}
