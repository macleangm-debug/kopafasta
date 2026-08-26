<?php

namespace App\Services\Plus;

use App\Models\PlusSubject;
use App\Models\PlusSubjectCategory;

class PlusLearningCatalog
{
    public function seed(): void
    {
        foreach ($this->categories() as $index => $category) {
            $row = PlusSubjectCategory::query()->firstOrCreate(
                ['slug' => $category['slug']],
                [
                    'title_en' => $category['en'],
                    'title_sw' => $category['sw'],
                    'sort' => $index + 1,
                    'status' => 'published',
                ]
            );

            foreach ($category['topics'] as $offset => $topic) {
                $slug = $category['slug'].'-'.($offset + 1);
                $publish = $offset === 0;
                $body = $publish
                    ? $this->article($category['slug'])
                    : [null, null];

                PlusSubject::query()->firstOrCreate(
                    ['slug' => $slug],
                    [
                        'plus_subject_category_id' => $row->id,
                        'title_en' => $topic[0],
                        'title_sw' => $topic[1],
                        'intro_en' => $this->intro('en', $category['slug']),
                        'intro_sw' => $this->intro('sw', $category['slug']),
                        'body_en' => $body[0],
                        'body_sw' => $body[1],
                        'duration_minutes' => $topic[2] ?? 4,
                        'content_type' => 'article',
                        'action_en' => $category['action']['en'],
                        'action_sw' => $category['action']['sw'],
                        'action_route' => $category['route'],
                        'icon' => $category['icon'],
                        'featured' => $publish && $index < 8,
                        'status' => $publish ? 'published' : 'draft',
                        'published_at' => $publish ? now()->subDays(max(1, 20 - $index)) : null,
                    ]
                );
            }
        }
    }

    /** @return list<array<string, mixed>> */
    public function categories(): array
    {
        return [
            $this->cat('money', 'My money', 'Pesa zangu', '💸', 'site.borrower.plus.money', 'Plan my money', 'Panga pesa yangu', $this->money()),
            $this->cat('saving', 'Saving', 'Kuweka akiba', '🫙', 'site.borrower.plus.goals', 'Start a small goal', 'Anza lengo dogo', $this->saving()),
            $this->cat('business', 'Business', 'Biashara', '🏪', 'site.borrower.plus.business', 'Check my business', 'Angalia biashara yangu', $this->business()),
            $this->cat('loans', 'Borrowing', 'Mikopo', '🤝', 'site.borrower.plus.home', 'Open my status', 'Angalia hadhi yangu', $this->loans()),
            $this->cat('debt', 'Debts', 'Madeni', '📉', 'site.borrower.plus.money', 'See what is coming', 'Angalia yanayokuja', $this->debt()),
            $this->cat('goals', 'Goals', 'Malengo', '🎯', 'site.borrower.plus.goals', 'Create a goal', 'Tengeneza lengo', $this->goals()),
            $this->cat('family', 'Family & money', 'Familia na pesa', '👨‍👩‍👧', 'site.borrower.plus.money', 'Plan household money', 'Panga pesa ya nyumbani', $this->family()),
            $this->cat('emergency', 'Emergencies', 'Dharura', '🚨', 'site.borrower.plus.goals', 'Start an emergency goal', 'Anza lengo la dharura', $this->emergency()),
            $this->cat('work', 'Work & income', 'Kazi na kipato', '🛠️', 'site.borrower.plus.money', 'Record money in', 'Andika pesa iliyo ingia', $this->work()),
            $this->cat('pricing', 'Price & profit', 'Bei na faida', '🏷️', 'site.borrower.plus.business', 'Record a sale', 'Andika mauzo', $this->pricing()),
            $this->cat('customers', 'Customers', 'Wateja', '🙋', 'site.borrower.plus.business', 'Open business', 'Fungua biashara', $this->customers()),
            $this->cat('stock', 'Stock & buying', 'Stock na manunuzi', '📦', 'site.borrower.plus.business', 'Record spending', 'Andika matumizi', $this->stock()),
            $this->cat('payments', 'Paying & being paid', 'Kulipwa na kulipa', '📱', 'site.borrower.plus.money', 'Write a payment', 'Andika malipo', $this->payments()),
            $this->cat('safety', 'Money safety', 'Usalama wa pesa', '🔒', 'site.borrower.plus.home', 'Open Plus', 'Fungua Plus', $this->safety()),
            $this->cat('insurance', 'Protection', 'Bima na ulinzi', '🛡️', 'site.borrower.plus.offers', 'See offers', 'Angalia ofa', $this->insurance()),
            $this->cat('home', 'Home & assets', 'Nyumba na mali', '🏠', 'site.borrower.plus.goals', 'Set a home goal', 'Weka lengo la nyumba', $this->home()),
            $this->cat('farming', 'Farming income', 'Kilimo na kipato', '🌾', 'site.borrower.plus.money', 'Track seasonal money', 'Fuatilia pesa ya msimu', $this->farming()),
            $this->cat('digital', 'Digital business', 'Digitali na biashara', '💬', 'site.borrower.plus.business', 'Record today’s sales', 'Andika mauzo ya leo', $this->digital()),
            $this->cat('tax', 'Records', 'Kodi na kumbukumbu', '🧾', 'site.borrower.plus.reports', 'Open my report', 'Fungua ripoti yangu', $this->tax()),
            $this->cat('growth', 'Growing money', 'Kukua kifedha', '🌱', 'site.borrower.plus.reports', 'See my month', 'Angalia mwezi wangu', $this->growth()),
        ];
    }

    private function cat(string $slug, string $en, string $sw, string $icon, string $route, string $actionEn, string $actionSw, array $topics): array
    {
        return [
            'slug' => $slug,
            'en' => $en,
            'sw' => $sw,
            'icon' => $icon,
            'route' => $route,
            'action' => ['en' => $actionEn, 'sw' => $actionSw],
            'topics' => $topics,
        ];
    }

    public function categoryIcon(string $slug): string
    {
        foreach ($this->categories() as $category) {
            if ($category['slug'] === $slug) {
                return $category['icon'];
            }
        }

        return '📘';
    }

    public function refreshPublishedCopy(): void
    {
        foreach ($this->categories() as $category) {
            $topic = $category['topics'][0] ?? null;
            if (! $topic) {
                continue;
            }
            $body = $this->article($category['slug']);
            PlusSubject::query()->where('slug', $category['slug'].'-1')->update([
                'intro_en' => $this->intro('en', $category['slug']),
                'intro_sw' => $this->intro('sw', $category['slug']),
                'body_en' => $body[0],
                'body_sw' => $body[1],
                'duration_minutes' => 6,
            ]);
        }
    }

    public function refreshPublishedCopyIfStale(): void
    {
        $published = PlusSubject::query()
            ->where('status', 'published')
            ->get(['intro_en', 'intro_sw', 'body_en']);

        $stale = $published->contains(function (PlusSubject $row) {
            $introEn = (string) $row->intro_en;
            $introSw = (string) $row->intro_sw;
            $bodyEn = (string) $row->body_en;

            return str_contains($introEn, 'Read for a few minutes')
                || str_contains($introSw, 'Soma dakika chache')
                || str_contains($bodyEn, 'This happens to many people')
                || str_contains($bodyEn, 'is not a separate subject')
                || str_contains($bodyEn, 'si somo tofauti')
                || substr_count(trim($bodyEn), "\n\n") > 14;
        });

        if ($stale) {
            $this->refreshPublishedCopy();
        }
    }

    private function intro(string $locale, string $slug): string
    {
        return $this->copy($slug, $locale, 'lead');
    }

    /** @return array{0: ?string, 1: ?string} */
    private function article(string $slug): array
    {
        return [
            implode("\n\n", \App\Support\PlusLearningLongCopy::paragraphs($slug, 'en', $this->copyBundle($slug, 'en'))),
            implode("\n\n", \App\Support\PlusLearningLongCopy::paragraphs($slug, 'sw', $this->copyBundle($slug, 'sw'))),
        ];
    }

    /** @return array<string, string> */
    private function copyBundle(string $slug, string $locale): array
    {
        return [
            'lead' => $this->copy($slug, $locale, 'lead'),
            'why' => $this->copy($slug, $locale, 'why'),
            'how' => $this->copy($slug, $locale, 'how'),
            'today' => $this->copy($slug, $locale, 'today'),
        ];
    }

    private function copy(string $slug, string $locale, string $part): string
    {
        $en = [
            'money' => [
                'lead' => 'A clear picture of money in, money out, and what is left — before the month surprises you.',
                'why' => 'Money often feels like it disappeared because it was never written down. When cash, mobile money and a shop till mix, the month looks full on Monday and empty by Thursday. That is not a character problem. It is a missing picture.',
                'how' => 'Keep three numbers, not a perfect book: what came in, what went out, and what is left. Split the leftover into must-haves (rent, food, travel), coming-soon (fees, bills, repayments), and later (a small save or your goal). You do not need a large amount to start.',
                'today' => 'Open Your money in Plus and write today’s in or out. One number is enough. Tomorrow you will already see the month moving in the card.',
            ],
            'saving' => [
                'lead' => 'A small save you can actually reach — without waiting for a windfall.',
                'why' => 'People wait for a big leftover before they save. The leftover rarely arrives. A reachable save is a number you can add on an ordinary day, even TZS 1,000, kept in a named place so it is not “borrowed” by the week.',
                'how' => 'Name the save (emergency, school, stock). Put it in Plus Goals. Add a little on days money comes in — before the rest of the week spends it. Protect it by writing it as already gone.',
                'today' => 'Open Goals and start or add to a small target. The bar only moves when you add. That is the point.',
            ],
            'business' => [
                'lead' => 'Shop money and house money stay clearer when today’s sale and spend sit in one card.',
                'why' => 'A quiet day still happened. If it is not written, last week and this week look the same, and you cannot tell whether the shop is helping the home or the home is eating the shop.',
                'how' => 'Write what you sold and what the shop spent. The difference is the only number that matters today. Do not wait for a full till count. One sale still counts.',
                'today' => 'Open Your business and record today’s sale or spend. The card at the top is your summary.',
            ],
            'loans' => [
                'lead' => 'Borrowing is a tool. The story that matters is the one you keep when you are not borrowing.',
                'why' => 'A loan does not fix a month that has no picture. Trust grows from keeping promises: paying on time, writing money, finishing a small goal. Plus never asks you to borrow to look active.',
                'how' => 'Use Plus for the days between loans. Write money. Check the report. Keep a goal moving. When you do borrow, you already know what the month can carry.',
                'today' => 'Open your Plus dashboard and complete today’s step. That is the useful action — not a new application.',
            ],
            'debt' => [
                'lead' => 'Debts get quieter when you can see what is coming — and what you already wrote down.',
                'why' => 'A repayment that “suddenly” arrives usually sat in the calendar all along. Fear grows in the dark. A list of coming amounts, even three lines, turns panic into a plan.',
                'how' => 'Write the next payment and the date. Keep household money and shop money apart so a debt does not empty both. Pay the promised amount; do not skip writing the week just because a bill is due.',
                'today' => 'Open Your money and look at what is coming. If nothing is listed, write the next bill as an upcoming amount in your head, then record today’s out so the card stays true.',
            ],
            'goals' => [
                'lead' => 'A named target with a date beats a wish that waits for leftover money.',
                'why' => 'Goals stall because they stay in the air. A target in Plus has an amount, a date, and a bar. Adding a little on a good day is the whole method.',
                'how' => 'Pick one kind: school, home, emergency, stock, business. Set the amount. Choose a date on the calendar. Add progress when money comes in — before the week spends it.',
                'today' => 'Open Goals and create or add to one target. Watch the card update. That is the summary.',
            ],
            'family' => [
                'lead' => 'Household money stays calmer when everyone can see the same three numbers.',
                'why' => 'Family asks often arrive on payday. If the month has no picture, every ask feels like a crisis. A shared plan — must-haves, coming-soon, later — makes “not this week” easier to say without a fight.',
                'how' => 'Write household in and out in Your money. Agree one small later amount (a save or a goal) that is already spoken for. Giving can still happen; it just has a place.',
                'today' => 'Open Your money and record today’s household amount. The card is the picture you can show at home.',
            ],
            'emergency' => [
                'lead' => 'An emergency is less shocking when a small, reachable amount already exists.',
                'why' => 'Emergencies empty the week because there was nowhere else to take from. A named emergency goal, even a modest one, is a shock absorber — not a full insurance policy.',
                'how' => 'Create an emergency goal in Plus. Add on good days. Keep it separate from shop money and from the goal you are building for school or stock.',
                'today' => 'Open Goals and start or add to an emergency target. One add is enough for today.',
            ],
            'work' => [
                'lead' => 'Income that arrives on different days still needs a picture of the week.',
                'why' => 'Uneven pay makes people feel behind even after a good day. Writing money in when it arrives — salary, a job, someone paying you — stops the “I don’t know where it went” week.',
                'how' => 'Record money in the day it comes. Split it before you spend: must-haves, coming-soon, later. Do not wait for a “proper” payday.',
                'today' => 'Open Your money and write what came in, even if it is small. The month card will show it.',
            ],
            'pricing' => [
                'lead' => 'Price and profit stay honest when a sale is written the same day.',
                'why' => 'A price that “feels right” can still lose money if stock and spending are invisible. One recorded sale plus one recorded spend tells you more than a long argument about markup.',
                'how' => 'Write the sale. Write what the shop spent to make it. Look at the difference in Your business. Adjust the next price from that number, not from a neighbour’s rumour.',
                'today' => 'Open Your business and record a sale. The card at the top is today’s profit picture.',
            ],
            'customers' => [
                'lead' => 'Customers return when the shop is calm, priced clearly, and remembered.',
                'why' => 'A busy chat is not cash. A regular who pays and comes back is the business. Recording sales helps you see which days actually fed the home.',
                'how' => 'Keep a simple habit: every sale in Plus, even a small one. Note quiet days too — they still happened. Clear prices reduce bargaining that eats the week.',
                'today' => 'Open Your business and write today’s sales. If there were none, write a spend or leave the card at zero — honesty is the record.',
            ],
            'stock' => [
                'lead' => 'Stock is money sitting on a shelf. Buying without writing it hides the week.',
                'why' => 'A restock can look like a good day until you see that the shop spent more than it sold. Writing spending the same day keeps stock from pretending to be profit.',
                'how' => 'Record shop spending in Your business. Compare it to sales in the same card. Buy again only when the difference still leaves the house standing.',
                'today' => 'Open Your business and record today’s stock spend or a sale. The summary stays in the card.',
            ],
            'payments' => [
                'lead' => 'Paying and being paid both need a line in the same picture.',
                'why' => 'Mobile money, cash, and a promised payment scatter the week. If you only remember the big one, the small leaks empty the leftover.',
                'how' => 'Write money in when you are paid. Write money out when you pay. Upcoming amounts belong in the coming-soon pile so they do not “appear” on Friday.',
                'today' => 'Open Your money and write one payment — in or out. The month card is the summary.',
            ],
            'safety' => [
                'lead' => 'Money safety is mostly habits: a PIN, a separate place, and a record you trust.',
                'why' => 'Loss often starts with mixing: one phone, one wallet, no picture. Plus is a companion for the days you are not borrowing — a place to see the month without sharing your PIN with the street.',
                'how' => 'Keep shop and home apart. Write amounts in Plus, not on a scrap that tears. Do not show balances in public. A report you can open is safer than a memory.',
                'today' => 'Open the Plus dashboard and complete today’s step. That is a safety habit, not a lecture.',
            ],
            'insurance' => [
                'lead' => 'Protection starts with seeing what would hurt — then using the offers that actually fit.',
                'why' => 'People skip protection because it sounds like a product pitch. A useful first step is knowing your month, your goal, and any Plus offer that matches your country — without confusing it with Grade.',
                'how' => 'Read the offer. Claim only what you will use. Keep writing money so an emergency does not wipe the week. Offers sit in Plus; they are not a new loan.',
                'today' => 'Open Offers and read the one marked best for you. Claim it if it helps this week.',
            ],
            'home' => [
                'lead' => 'A home goal is a date and an amount — not a wish that waits for leftover.',
                'why' => 'House and asset plans stall when they stay spoken only. A Plus goal with a calendar date turns “someday” into a bar you can add to on a good day.',
                'how' => 'Create a home goal. Set the amount and the date with the same calendar the rest of Kopafasta uses. Add progress when money comes in, before the week spends it.',
                'today' => 'Open Goals and start or add to a home target. The card will show how far you have come.',
            ],
            'farming' => [
                'lead' => 'Seasonal money still needs a month picture — harvest is not a plan by itself.',
                'why' => 'A good harvest can vanish between school fees, stock, and family asks if it is not written. Seasonal income is uneven; the method is the same three numbers.',
                'how' => 'Record money in when the season pays. Split must-haves, coming-soon, and later (seed, a save, a goal). Do not wait for the next harvest to start the picture.',
                'today' => 'Open Your money and write what came in or went out. The month card holds the season in small lines.',
            ],
            'digital' => [
                'lead' => 'A chat is not a till. Digital sales still need today’s number in the card.',
                'why' => 'Online talk feels like work. Cash is what landed. Recording sales and spending in Plus keeps a busy phone from hiding an empty week.',
                'how' => 'Write the sale when the money arrives, not when the message was sent. Treat data bundles as shop spending. Close the day with sold, spent, leftover.',
                'today' => 'Open Your business and record today’s digital sale or spend. The card is the till.',
            ],
            'tax' => [
                'lead' => 'A record today is a calmer month-end — receipts, sales, and spending in one place.',
                'why' => 'Fear of “tax talk” is often fear of a missing book. You do not need a perfect system. You need what you sold, what you spent, and what is left, kept as you go.',
                'how' => 'Use Plus reports as the monthly picture. Keep receipts (a photo is still a receipt). Write large spends with a name. Do not wait for a folder to feel official.',
                'today' => 'Open your report and read the month. Then write today’s money or sale so next month’s picture is already started.',
            ],
            'growth' => [
                'lead' => 'Growth is a small action repeated — leftover after a hard week, then another ordinary Tuesday.',
                'why' => 'Discipline is not a personality. It is writing the number, adding to a goal, and opening the month without shame. Trust is easier to see when the picture is honest.',
                'how' => 'Compare yourself to last month, not a neighbour. Learn for a few minutes, then do one thing in Plus. Rest is part of the plan; skipping the record is not.',
                'today' => 'Open your report or Your money and take one step. That is the growth action — not a longer article.',
            ],
        ];

        $sw = [
            'money' => [
                'lead' => 'Picha wazi ya pesa inayoingia, inayotoka, na iliyobaki — kabla mwezi haujakushangaza.',
                'why' => 'Pesa inaonekana “imepotea” kwa sababu haikuandikwa. Taslimu, simu na duka zikichanganyika, wiki inaonekana imejaa Jumatatu na tupu Alhamisi. Si tabia mbaya. Ni picha inayokosekana.',
                'how' => 'Shika namba tatu, si daftari kamili: zilizoingia, zilizotoka, na salio. Gawa salio: ya lazima (kodi, chakula, usafiri), yanayokuja (ada, bili, malipo), na ya baadaye (akiba au lengo). Huhitaji kuanza na kiasi kikubwa.',
                'today' => 'Fungua Pesa zako katika Plus na andika za leo. Namba moja inatosha. Kesho utaona mwezi ukisogea kwenye kadi.',
            ],
            'saving' => [
                'lead' => 'Akiba ndogo unayoweza kuifikia — bila kusubiri upepo wa pesa.',
                'why' => 'Watu husubiri salio kubwa ndipo waweke. Salio hilo mara chache linakuja. Akiba inayofikiwa ni kiasi unachoweza kuongeza siku ya kawaida, hata TZS 1,000, mahali palipo na jina ili wiki isipoikopa.',
                'how' => 'Ipe jina (dharura, ada, bidhaa). Iweke kwenye Malengo ya Plus. Ongeza kidogo siku pesa inapoingia — kabla wiki haijaitumia. Ilinda kwa kuiandika kama tayari imetoka.',
                'today' => 'Fungua Malengo na anza au ongeza kwenye lengo dogo. Mstari unasogea unapoongeza. Ndivyo ilivyo.',
            ],
            'business' => [
                'lead' => 'Pesa ya duka na ya nyumbani inakuwa wazi mauzo na matumizi ya leo yakikaa kadi moja.',
                'why' => 'Siku tulivu bado ilitokea. Ikiwa haijaandikwa, wiki hii na iliypita zinafanana, na huwezi kuona kama duka linasaidia nyumba au nyumba inakula duka.',
                'how' => 'Andika ulichouza na duka lilichotumia. Tofauti ndiyo namba ya leo. Usisubiri kuhesabu kila kitu. Mauzo moja bado yanahesabika.',
                'today' => 'Fungua Biashara yako na andika mauzo au matumizi ya leo. Kadi juu ndiyo muhtasari.',
            ],
            'loans' => [
                'lead' => 'Kukopa ni chombo. Hadithi muhimu ni ile unayoendelea nayo siku usizokopa.',
                'why' => 'Mkopo hauwezi kurekebisha mwezi usio na picha. Imani inakua kwa kutimiza ahadi: kulipa kwa wakati, kuandika pesa, kumaliza lengo dogo. Plus haikuombi ukope ili uonekane mwenye bidii.',
                'how' => 'Tumia Plus siku kati ya mikopo. Andika pesa. Angalia ripoti. Sogeza lengo. Ukitaka kukopa, tayari unajua mwezi unachoweza kubeba.',
                'today' => 'Fungua dashibodi ya Plus na kamilisha hatua ya leo. Hiyo ndiyo hatua yenye manufaa — si ombi jipya.',
            ],
            'debt' => [
                'lead' => 'Madeni yanatulia unapoweza kuona yanayokuja — na ulichokwishaandika.',
                'why' => 'Malipo “ya ghafla” mara nyingi yalikuwa kwenye kalenda. Hofu inakua gizani. Orodha ya kiasi kinachokuja, hata mistari mitatu, inageuza hofu kuwa mpango.',
                'how' => 'Andika malipo yajayo na tarehe. Tenganisha pesa ya nyumbani na ya duka ili deni lisitumie zote. Lipa kiasi ulichoahidi; usiache kuandika wiki kwa sababu bili inakuja.',
                'today' => 'Fungua Pesa zako na angalia yanayokuja. Kisha andika zilizotoka leo ili kadi ibaki ya kweli.',
            ],
            'goals' => [
                'lead' => 'Lengo lenye jina na tarehe linashinda tamaa inayosubiri salio.',
                'why' => 'Malengo yanakwama yanapobaki angani. Lengo katika Plus lina kiasi, tarehe, na mstari. Kuongeza kidogo siku nzuri ndiyo njia yote.',
                'how' => 'Chagua aina: ada, nyumba, dharura, bidhaa, biashara. Weka kiasi. Chagua tarehe kwenye kalenda. Ongeza pesa inapoingia — kabla wiki haijaitumia.',
                'today' => 'Fungua Malengo na tengeneza au ongeza kwenye lengo moja. Kadi itasasisha. Huo ndio muhtasari.',
            ],
            'family' => [
                'lead' => 'Pesa ya nyumbani inatulia kila mtu akinena namba zilezile tatu.',
                'why' => 'Maombi ya familia huja siku ya malipo. Mwezi usipo na picha, kila ombi ni dharura. Mpango wa pamoja — ya lazima, yanayokuja, ya baadaye — unawezesha “si wiki hii” bila vita.',
                'how' => 'Andika zinazoingia na kutoka za nyumbani. Kubaliana kiasi kidogo cha baadaye (akiba au lengo) ambacho tayari kimezungumzwa. Kutoa bado kunaweza; kina nafasi.',
                'today' => 'Fungua Pesa zako na andika kiasi cha leo cha nyumbani. Kadi ndiyo picha unaweza kuionyesha nyumbani.',
            ],
            'emergency' => [
                'lead' => 'Dharura inashangaza kidogo kama kiasi kidogo kinachofikiwa tayari kipo.',
                'why' => 'Dharura inamaliza wiki kwa sababu hakukuwa na mahali pengine pa kuchukua. Lengo la dharura, hata dogo, ni kinga — si bima kamili.',
                'how' => 'Tengeneza lengo la dharura katika Plus. Ongeza siku nzuri. Litenganishe na pesa ya duka na lengo la ada au bidhaa.',
                'today' => 'Fungua Malengo na anza au ongeza kwenye lengo la dharura. Ongezo moja linatosha leo.',
            ],
            'work' => [
                'lead' => 'Kipato kinachofika siku tofauti bado kinahitaji picha ya wiki.',
                'why' => 'Malipo yasiyo thabiti yanakufanya uhisi umebaki nyuma hata baada ya siku nzuri. Kuandika pesa inapoingia — mshahara, kazi, mtu aliyekulipa — kunazuia wiki ya “sijui ilienda wapi”.',
                'how' => 'Andika zinazoingia siku zinapofika. Gawa kabla ya kutumia: ya lazima, yanayokuja, ya baadaye. Usisubiri “siku rasmi” ya malipo.',
                'today' => 'Fungua Pesa zako na andika kilichoingia, hata kiwe kidogo. Kadi ya mwezi itaonyesha.',
            ],
            'pricing' => [
                'lead' => 'Bei na faida zinakuwa za kweli mauzo yakandikwa siku ileile.',
                'why' => 'Bei “inayohisi sawa” bado inaweza hasara kama stock na matumizi havionekani. Mauzo moja na matumizi moja yaliyoandikwa yanasema zaidi ya mabishano ya ongezeko la bei.',
                'how' => 'Andika mauzo. Andika duka lilichotumia kuyafanya. Angalia tofauti kwenye Biashara yako. Rekebisha bei ijayo kutoka namba hiyo, si uvumi wa jirani.',
                'today' => 'Fungua Biashara yako na andika mauzo. Kadi juu ndiyo picha ya faida ya leo.',
            ],
            'customers' => [
                'lead' => 'Wateja wanarudi duka likiwa tulivu, bei wazi, na likikumbukwa.',
                'why' => 'Chat nyingi si taslimu. Mteja anayelipa na kurudi ndiye biashara. Kuandika mauzo kunakusaidia kuona siku zipi zililisha nyumba.',
                'how' => 'Kila mauzo katika Plus, hata madogo. Andika pia siku tulivu — zilitokea. Bei wazi inapunguza ubargaining unaokula wiki.',
                'today' => 'Fungua Biashara yako na andika mauzo ya leo. Ikiwa hakukuwa, andika matumizi au acha kadi iwe sifuri — ukweli ndio kumbukumbu.',
            ],
            'stock' => [
                'lead' => 'Stock ni pesa rafuni. Kununua bila kuandika kunaficha wiki.',
                'why' => 'Kujaza tena kunaweza kuonekana siku nzuri hadi uone duka lilitumia zaidi ya lililouza. Kuandika matumizi siku ileile kunazuia stock kujifanya faida.',
                'how' => 'Andika matumizi ya duka. Linganisha na mauzo kwenye kadi ileile. Nunua tena tofauti ikiwa bado inawaacha nyumbani wamesimama.',
                'today' => 'Fungua Biashara yako na andika matumizi ya stock au mauzo ya leo. Muhtasari unakaa kwenye kadi.',
            ],
            'payments' => [
                'lead' => 'Kulipa na kulipwa vyote vinahitaji mstari kwenye picha ileile.',
                'why' => 'Simu, taslimu, na ahadi ya malipo vinatawanya wiki. Ulikumbuka kubwa tu, mianya midogo inamaliza salio.',
                'how' => 'Andika zinazoingia unapolipwa. Andika zinazotoka unapolipa. Yanayokuja yawekwe kwenye “yanayokuja” ili yasionekane “ghafla” Ijumaa.',
                'today' => 'Fungua Pesa zako na andika malipo moja — kuingia au kutoka. Kadi ya mwezi ndiyo muhtasari.',
            ],
            'safety' => [
                'lead' => 'Usalama wa pesa ni tabia: PIN, sehemu tofauti, na kumbukumbu unayoiamini.',
                'why' => 'Hasara mara nyingi inaanza kwa kuchanganya: simu moja, pochi moja, bila picha. Plus ni mwenzako siku usizokopa — mahali pa kuona mwezi bila kushiriki PIN mtaani.',
                'how' => 'Tenganisha duka na nyumbani. Andika kiasi katika Plus, si karatasi inayoraruka. Usionyeshe salio hadharani. Ripoti unayoweza kufungua ni salama kuliko kumbukumbu kichwani.',
                'today' => 'Fungua dashibodi ya Plus na kamilisha hatua ya leo. Hiyo ni tabia ya usalama, si somo.',
            ],
            'insurance' => [
                'lead' => 'Ulinzi unaanza kwa kuona kitakachoumiza — kisha kutumia ofa inayokufaa.',
                'why' => 'Watu wanakwepa ulinzi kwa sababu unasikika kama bidhaa. Hatua ya kwanza ni kujua mwezi wako, lengo lako, na ofa ya Plus ya nchi yako — bila kuichanganya na Daraja.',
                'how' => 'Soma ofa. Dai ile utakayoitumia. Endelea kuandika pesa ili dharura isimalize wiki. Ofa ziko Plus; si mkopo mpya.',
                'today' => 'Fungua Ofa na soma ile inayokufaa zaidi. Idai ikiwa inasaidia wiki hii.',
            ],
            'home' => [
                'lead' => 'Lengo la nyumba ni tarehe na kiasi — si tamaa inayosubiri salio.',
                'why' => 'Mipango ya nyumba na mali inakwama inapobaki maneno tu. Lengo la Plus lenye tarehe ya kalenda linageuza “siku moja” kuwa mstari unaoweza kuongeza siku nzuri.',
                'how' => 'Tengeneza lengo la nyumba. Weka kiasi na tarehe kwa kalenda ileile ya Kopafasta. Ongeza pesa inapoingia, kabla wiki haijaitumia.',
                'today' => 'Fungua Malengo na anza au ongeza kwenye lengo la nyumba. Kadi itaonyesha umefikia wapi.',
            ],
            'farming' => [
                'lead' => 'Pesa ya msimu bado inahitaji picha ya mwezi — mavuno si mpango peke yake.',
                'why' => 'Mavuno mazuri yanaweza kutoweka kati ya ada, bidhaa na maombi ya familia yakiwa hayajaandikwa. Kipato cha msimu si thabiti; njia ni namba zilezile tatu.',
                'how' => 'Andika zinazoingia msimu unapolipa. Gawa ya lazima, yanayokuja, na ya baadaye (mbegu, akiba, lengo). Usisubiri mavuno yajayo kuanza picha.',
                'today' => 'Fungua Pesa zako na andika kilichoingia au kilichotoka. Kadi ya mwezi inashikilia msimu kwa mistari midogo.',
            ],
            'digital' => [
                'lead' => 'Chat si duka. Mauzo ya simu bado yanahitaji namba ya leo kwenye kadi.',
                'why' => 'Mazungumzo mtandaoni yanahisi kazi. Taslimu ndiyo iliyofika. Kuandika mauzo na matumizi katika Plus kunazuia simu yenye shughuli kuficha wiki tupu.',
                'how' => 'Andika mauzo pesa inapofika, si ujumbe ulipotumwa. Bundles ni matumizi ya duka. Funga siku: mauzo, matumizi, salio.',
                'today' => 'Fungua Biashara yako na andika mauzo au matumizi ya leo ya simu. Kadi ndiyo duka.',
            ],
            'tax' => [
                'lead' => 'Kumbukumbu leo ni mwisho wa mwezi tulivu — risiti, mauzo na matumizi mahali pamoja.',
                'why' => 'Hofu ya “kodi” mara nyingi ni hofu ya daftari lisilokuwepo. Huhitaji mfumo kamili. Unahitaji ulichouza, ulichotumia, na kilichobaki, ukiendelea kuandika.',
                'how' => 'Tumia ripoti ya Plus kama picha ya mwezi. Tunga risiti (picha bado ni risiti). Andika matumizi makubwa na jina. Usisubiri folda ihisi rasmi.',
                'today' => 'Fungua ripoti yako na soma mwezi. Kisha andika pesa au mauzo ya leo ili picha ya mwezi ujao ianze.',
            ],
            'growth' => [
                'lead' => 'Kukua ni hatua ndogo inayorudiwa — salio baada ya wiki ngumu, kisha Jumanne nyingine ya kawaida.',
                'why' => 'Nidhamu si tabia ya kuzaliwa. Ni kuandika namba, kuongeza kwenye lengo, na kufungua mwezi bila aibu. Imani inaonekana picha ikiwa ya kweli.',
                'how' => 'Jilinganishe na mwezi uliopita, si jirani. Soma dakika chache, kisha fanya kitu kimoja katika Plus. Pumziko ni sehemu ya mpango; kuruka kumbukumbu siyo.',
                'today' => 'Fungua ripoti au Pesa zako na fanya hatua moja. Hiyo ndiyo kukua — si makala ndefu zaidi.',
            ],
        ];

        $bundle = $locale === 'sw' ? $sw : $en;
        $fallback = $locale === 'sw' ? $sw['money'] : $en['money'];

        return $bundle[$slug][$part] ?? $fallback[$part];
    }

    private function money(): array
    {
        return $this->pack([
            ['Does money run out before month end?', 'Pesa inaisha kabla ya mwisho wa mwezi?'],
            ['Where my money actually goes', 'Pesa yangu inaenda wapi hasa'],
            ['Needs, wants, and the in-between', 'Ya lazima, ya tamaa, na kati'],
            ['A monthly money check that takes 10 minutes', 'Ukaguzi wa pesa wa dakika 10'],
            ['Living with money that arrives unevenly', 'Kuishi na pesa inayofika kwa nyakati tofauti'],
            ['Three envelopes that keep a week calm', 'Bahasha tatu zinazotulia wiki'],
            ['What to do the morning money arrives', 'Unachofanya asubuhi pesa inapofika'],
            ['Small leaks that empty a wallet', 'Mianya midogo inayoisha mfuko'],
            ['How to see leftover money clearly', 'Jinsi ya kuona pesa iliyobaki wazi'],
            ['Writing money down without a fancy book', 'Kuandika pesa bila daftari ghali'],
            ['When relatives ask on payday', 'Wakati ndugu wanaomba siku ya malipo'],
            ['Keeping transport from eating the week', 'Usafiri usile wiki nzima'],
            ['Food money that lasts the month', 'Pesa ya chakula inayodumu mwezi'],
            ['Bills first, then the rest', 'Bili kwanza, kisha mengine'],
            ['A quiet Sunday money review', 'Ukaguzi wa pesa Jumapili'],
            ['What leftover really means', 'Salio linamaanisha nini'],
            ['Stopping the “I don’t know where it went” week', 'Kuzuia wiki ya “sijui ilienda wapi”'],
            ['One number to check every evening', 'Namba moja ya kuangalia kila jioni'],
            ['Sharing a wallet with your future self', 'Kushiriki mfuko na wewe wa baadaye'],
            ['When a good week still feels tight', 'Wiki nzuri bado inahisi kubanwa'],
            ['Cash, mobile money, and mixing them', 'Taslimu, simu, na kuzichanganya'],
            ['Giving without emptying the house', 'Kutoa bila kuisha nyumbani'],
            ['A simple weekly leftover target', 'Lengo rahisi la salio la wiki'],
            ['Restarting after a messy month', 'Kuanza upya baada ya mwezi mchafu'],
            ['Money in, money out, what is left', 'Zinazoingia, zinazotoka, na salio'],
        ]);
    }

    private function saving(): array
    {
        return $this->pack([
            ['Start saving without waiting for a big amount', 'Anza kuweka akiba bila kusubiri pesa nyingi'],
            ['Emergency money that is actually reachable', 'Pesa ya dharura inayofikiwa'],
            ['Daily save vs monthly save', 'Kuweka kila siku au kila mwezi'],
            ['Protecting savings from a sudden ask', 'Kulinda akiba dhidi ya ombi la ghafla'],
            ['Where to keep a small save safely', 'Wapi kuweka akiba ndogo salama'],
            ['Saving on irregular income', 'Kuweka akiba kipato kikiwa hakiko thabiti'],
            ['A save you will not “borrow” from', 'Akiba usiyoikopa mwenyewe'],
            ['Turning leftover into a habit', 'Kugeuza salio kuwa tabia'],
            ['Saving for school without panic', 'Kuweka kwa ada bila hofu'],
            ['The first 10,000 is the hardest', 'Elfu kumi ya kwanza ndiyo ngumu'],
            ['Hiding savings in plain sight', 'Kuficha akiba mahali pa wazi'],
            ['When saving feels selfish', 'Wakati kuweka akiba kunahisi ubinafsi'],
            ['Rounding up every sale', 'Kuzungusha kila mauzo'],
            ['A jar, a book, a goal', 'Kikombe, daftari, lengo'],
            ['Saving after a setback', 'Kuweka tena baada ya kikwazo'],
            ['Why empty months still matter', 'Kwa nini miezi tupu bado ni muhimu'],
            ['Telling family you are saving', 'Kuwaambia familia unaweka akiba'],
            ['Automatic is just “first, then spend”', 'Otomatiki ni “weka kwanza, kisha tumia”'],
            ['Saving for a tool, not a wish', 'Kuweka kwa chombo, si ndoto tupu'],
            ['How much is enough for now', 'Kiasi gani kinatosha sasa'],
            ['Keeping savings separate from shop money', 'Kutenganisha akiba na pesa ya duka'],
            ['A 30-day save challenge that is kind', 'Changamoto ya siku 30 yenye huruma'],
            ['What to do when you break the streak', 'Unachofanya ukivunja mfululizo'],
            ['Saving in a group without losing control', 'Kuweka kikundi bila kupoteza udhibiti'],
            ['Moving a save into a real goal', 'Kuhamisha akiba kwenye lengo halisi'],
        ]);
    }

    private function business(): array
    {
        return $this->pack([
            ['Do you really know today’s profit?', 'Je, unajua faida yako ya leo?'],
            ['Separate shop money and house money', 'Tenganisha pesa ya duka na ya nyumbani'],
            ['A sales note that takes one minute', 'Kumbukumbu ya mauzo ya dakika moja'],
            ['When a busy day is still a loss', 'Siku yenye kazi bado ni hasara'],
            ['Paying yourself from the business', 'Kujilipa kutoka biashara'],
            ['Costs you forget to count', 'Gharama unazosahau kuhesabu'],
            ['A quiet end-of-day count', 'Hesabu tulivu ya mwisho wa siku'],
            ['What “good week” should mean', 'Wiki nzuri inapaswa kumaanisha nini'],
            ['Stock that sits is money sitting', 'Stock inayokaa ni pesa inayokaa'],
            ['A customer who pays later', 'Mteja anayelipa baadaye'],
            ['Price that covers more than the buy price', 'Bei inayofunika zaidi ya bei ya kununua'],
            ['When to pause a slow product', 'Wakati wa kusimamisha bidhaa tulivu'],
            ['Writing expenses the same day', 'Kuandika matumizi siku hiyo hiyo'],
            ['Borrowing for the shop vs the house', 'Kukopa kwa duka au nyumbani'],
            ['A simple weekly business picture', 'Picha rahisi ya biashara ya wiki'],
            ['Cash in the till vs cash in the pocket', 'Pesa kwenye duka dhidi ya mfukoni'],
            ['How to see a trend without a computer', 'Kuona mwelekeo bila kompyuta'],
            ['Restocking without emptying the week', 'Kujaza stock bila kuisha wiki'],
            ['A helper, a wage, and leftover', 'Msaidizi, mshahara, na salio'],
            ['When sales rise but leftover falls', 'Mauzo yakipanda salio likishuka'],
            ['Recording a sale you made on WhatsApp', 'Kuandika mauzo ya WhatsApp'],
            ['The first hour of the trading day', 'Saa ya kwanza ya kufanya biashara'],
            ['Closing the shop with three numbers', 'Kufunga duka kwa namba tatu'],
            ['A month of business in one page', 'Mwezi wa biashara ukurasa mmoja'],
            ['Growing without mixing all the cash', 'Kukua bila kuchanganya pesa zote'],
        ]);
    }

    private function loans(): array
    {
        return $this->pack([
            ['When borrowing actually helps', 'Wakati kukopa kunasaidia kweli'],
            ['Understanding a repayment before you sign', 'Kuelewa malipo kabla ya kusaini'],
            ['Too many commitments at once', 'Ahadi nyingi kwa wakati mmoja'],
            ['Borrowing for growth, not for a hole', 'Kukopa kwa kukua, si kujaza tundu'],
            ['What Trust is really watching', 'Trust inaangalia nini hasa'],
            ['A late payment is a conversation, early', 'Kuchelewa ni mazungumzo, mapema'],
            ['Why more loans do not earn rewards', 'Kwa nini mikopo zaidi hailipi tuzo'],
            ['The true cost of a “small” extra loan', 'Gharama halisi ya mkopo mdogo wa ziada'],
            ['Matching a loan to a real use', 'Kulinganisha mkopo na matumizi halisi'],
            ['What happens if income dips mid-loan', 'Kipato kikishuka katikati ya mkopo'],
            ['Reading the next due date calmly', 'Kusoma tarehe inayofuata kwa utulivu'],
            ['Guarantors and the weight of a yes', 'Wadhamini na uzito wa ndiyo'],
            ['Do not borrow to look busy', 'Usikope ili uonekane mwenye kazi'],
            ['A repayment that fits the week you actually have', 'Malipo yanayolingana na wiki yako'],
            ['Grade is history, not a mood', 'Daraja ni historia, si hisia'],
            ['Asking for help before you fall behind', 'Kuomba msaada kabla ya kuchelewa'],
            ['Using a loan for stock you can sell', 'Kutumia mkopo kwa stock inayouzika'],
            ['The difference between access and need', 'Tofauti ya kufikia na kuhitaji'],
            ['Keeping one clear reason for a loan', 'Sababu moja wazi ya mkopo'],
            ['What “on time” does for next time', 'Kulipa kwa wakati kunafanya nini baadaye'],
            ['Avoiding overlapping due dates', 'Kuepuka tarehe zinazogongana'],
            ['A loan is not income', 'Mkopo si kipato'],
            ['When to wait instead of apply', 'Wakati wa kusubiri badala ya kuomba'],
            ['Talking to Kopafasta before it is urgent', 'Kuongea na Kopafasta kabla haijakuwa dharura'],
            ['Building a record you are proud of', 'Kujenga historia unayoijivunia'],
        ]);
    }

    private function debt(): array
    {
        return $this->pack([
            ['Which debt to face first', 'Deni lipi kukabiliana nalo kwanza'],
            ['Recovering after falling behind', 'Kupata nafuu baada ya kuchelewa'],
            ['Talking to a lender early', 'Kuongea na mkopeshaji mapema'],
            ['Stopping a debt cycle kindly', 'Kukata mzunguko wa deni kwa busara'],
            ['A list of everything you owe', 'Orodha ya kila unachodaiwa'],
            ['Minimums vs finishing one debt', 'Kima cha chini dhidi ya kumaliza moja'],
            ['Family debts that are not on paper', 'Madeni ya familia yasiyoandikwa'],
            ['Shop credit you gave out', 'Deni ulilotoa dukani'],
            ['When interest makes the hole deeper', 'Riba inapofanya tundu kuwa kubwa'],
            ['A week of only must-pay items', 'Wiki ya ya lazima tu'],
            ['Shame helps nobody — a plan does', 'Aibu haisaidii — mpango unasaidia'],
            ['Renegotiating without disappearing', 'Kujadili tena bila kutoweka'],
            ['One extra payment when a week is good', 'Malipo ya ziada wiki nzuri'],
            ['Keeping new debt out while you climb', 'Kuzuia deni jipya unapopanda'],
            ['What to say when you cannot pay Friday', 'Unachosema usipoweza kulipa Ijumaa'],
            ['Debt and sleep', 'Deni na usingizi'],
            ['Using a goal to stay out of a hole', 'Kutumia lengo kuepuka tundu'],
            ['Friends who lend, friends who wait', 'Marafiki wanaokopesha, wanaosubiri'],
            ['A calendar of due dates on one page', 'Kalenda ya tarehe ukurasa mmoja'],
            ['When collecting from customers funds a repayment', 'Kukusanya kwa wateja kulipia malipo'],
            ['Do not hide a missed payment', 'Usifiche malipo yaliyokosa'],
            ['Small wins against a large balance', 'Ushindi mdogo dhidi ya salio kubwa'],
            ['Debt after an emergency', 'Deni baada ya dharura'],
            ['Choosing which call to return first', 'Simu ipi kurudisha kwanza'],
            ['A finish line you can see', 'Mwisho unaoonekana'],
        ]);
    }

    private function goals(): array
    {
        return $this->pack([
            ['School fees without a last-minute scramble', 'Ada bila kukimbia mwisho'],
            ['Buying a motorcycle the slow way', 'Kununua pikipiki polepole'],
            ['A home built in pieces', 'Nyumba inayojengwa vipande'],
            ['Starting a business from a named goal', 'Kuanza biashara kutoka lengo'],
            ['Planning a large purchase in public', 'Kupanga ununuzi mkubwa wazi'],
            ['How much is left, not how far you failed', 'Kilichobaki, si ulivyokosa'],
            ['A date that keeps a goal honest', 'Tarehe inayofanya lengo kuwa la kweli'],
            ['Adding a little on a good day', 'Kuongeza kidogo siku nzuri'],
            ['Pausing a goal without quitting', 'Kusitisha lengo bila kuacha'],
            ['Two goals is often one too many', 'Malengo mawili mara nyingi ni mengi'],
            ['Naming the thing so money has a job', 'Kupa kitu jina ili pesa iwe na kazi'],
            ['School, tools, or a buffer — pick one first', 'Shule, zana, au akiba — chagua kwanza'],
            ['When family goals compete', 'Malengo ya familia yanaposhindana'],
            ['A picture of 70% done', 'Picha ya asilimia 70'],
            ['Celebrating a finished goal quietly', 'Kusherehekea lengo lililokamilika'],
            ['Restarting a goal you abandoned', 'Kuanza tena lengo uliloacha'],
            ['Goals that wait for harvest', 'Malengo yanayosubiri mavuno'],
            ['Keeping a goal separate from the till', 'Kutenganisha lengo na duka'],
            ['What to do with leftover after a goal', 'Unachofanya na salio baada ya lengo'],
            ['A goal small enough to finish this season', 'Lengo dogo la kumaliza msimu huu'],
            ['Telling someone the number you are aiming for', 'Kumwambia mtu namba unayolenga'],
            ['When a goal should change', 'Wakati lengo linapaswa kubadilika'],
            ['Progress on a week you barely sold', 'Maendeleo wiki ya mauzo machache'],
            ['The last stretch is still the same habit', 'Mwisho bado ni tabia ileile'],
            ['From wish to amount and date', 'Kutoka tamaa hadi kiasi na tarehe'],
        ]);
    }

    private function family(): array
    {
        return $this->pack([
            ['Talking about money with a spouse', 'Kuongea pesa na mwenza'],
            ['Household money that both can see', 'Pesa ya nyumbani wote mnaiona'],
            ['Children’s costs that arrive together', 'Gharama za watoto zinazofika pamoja'],
            ['Supporting relatives without emptying the pot', 'Kusaidia ndugu bila kuisha sufuria'],
            ['A family meeting that is 15 minutes', 'Mkutano wa familia wa dakika 15'],
            ['Whose pocket pays school this term', 'Mfuko wa nani unalipa ada'],
            ['When one person earns irregularly', 'Mtu mmoja akipata kwa nyakati'],
            ['Shared goals, separate small spends', 'Malengo ya pamoja, matumizi madogo'],
            ['Explaining a no without a fight', 'Kusema hapana bila ugomvi'],
            ['Money and respect in the house', 'Pesa na heshima nyumbani'],
            ['A book both of you can write in', 'Daftari mnaweza kuandika wote'],
            ['Visits, funerals, and the month’s plan', 'Ziara, mazishi, na mpango wa mwezi'],
            ['Teaching a child one money habit', 'Kumfundisha mtoto tabia moja'],
            ['When in-laws ask', 'Wakwe wanapoomba'],
            ['Splitting a good week fairly', 'Kugawanya wiki nzuri kwa insafu'],
            ['A buffer for the house, a buffer for the shop', 'Akiba ya nyumba, akiba ya duka'],
            ['Hidden spending that surprises the other', 'Matumizi yaliyofichwa'],
            ['Celebrations that do not start a hole', 'Sherehe zisizoanza tundu'],
            ['Illness in the family and the next bill', 'Ugonjwa na bili inayofuata'],
            ['Agreeing the three must-pays', 'Kukubaliana ya lazima matatu'],
            ['When one partner wants a loan', 'Mwenza akitaka mkopo'],
            ['Pocket money that is planned, not leftover', 'Pesa ya mfukoni iliyopangwa'],
            ['A calm way to review last month together', 'Kukagua mwezi uliopita kwa utulivu'],
            ['Protecting children’s school from a slow week', 'Kulinda shule dhidi ya wiki tulivu'],
            ['Love is not a blank cheque', 'Upendo si hundi wazi'],
        ]);
    }

    private function emergency(): array
    {
        return $this->pack([
            ['Medical costs that do not wait', 'Gharama za matibabu hazisubiri'],
            ['What to do when income stops suddenly', 'Unachofanya kipato kinaposimama'],
            ['An emergency fund that starts tiny', 'Mfuko wa dharura unaoanza mdogo'],
            ['Family emergencies vs business holes', 'Dharura ya familia na tundu la duka'],
            ['Who to call before you borrow in a panic', 'Simu ya kupiga kabla ya kukopa kwa hofu'],
            ['A list on the wall for bad days', 'Orodha ukutani kwa siku mbaya'],
            ['Keeping one week of food money aside', 'Kuweka pesa ya chakula ya wiki'],
            ['Hospital, travel, and the third cost', 'Hospitali, usafiri, na gharama ya tatu'],
            ['After the emergency — rebuilding slowly', 'Baada ya dharura — kujenga polepole'],
            ['Do not mix the emergency jar with the till', 'Usichanganye kikombe cha dharura na duka'],
            ['A neighbour, a clinic, a plan', 'Jirani, zahanati, mpango'],
            ['When the phone is the only safety net', 'Simu ikiwa ndiyo wavu pekee'],
            ['Saying no to a non-emergency ask', 'Hapana kwa ombi lisilo dharura'],
            ['Documents you need in a hurry', 'Nyaraka unazohitaji haraka'],
            ['Transport home from a clinic at night', 'Usafiri wa usiku kutoka zahanati'],
            ['A small save labelled “only if”', 'Akiba ndogo yenye lebo “iwapo tu”'],
            ['School continues during a shock', 'Shule inaendelea wakati wa mshtuko'],
            ['Telling Kopafasta when life changed', 'Kuambia Kopafasta maisha yalivyobadilika'],
            ['The first 48 hours of no income', 'Saa 48 za kwanza bila kipato'],
            ['Community help without losing the plot', 'Msaada wa jamii bila kupoteza mpango'],
            ['Replacing stock after a personal emergency', 'Kujaza stock baada ya dharura'],
            ['Grief and money decisions', 'Msiba na maamuzi ya pesa'],
            ['A prepaid airtime and a charged phone', 'Salio na simu yenye chaji'],
            ['Which bill can wait, which cannot', 'Bili ipi inaweza kusubiri'],
            ['Hope is not a plan — a jar is', 'Tumaini si mpango — kikombe ni'],
        ]);
    }

    private function work(): array
    {
        return $this->pack([
            ['A salary that disappears in three days', 'Mshahara unaoisha siku tatu'],
            ['Side income that you actually count', 'Kipato cha ziada unachohesabu'],
            ['Irregular work and a calm month', 'Kazi isiyo thabiti na mwezi mtulivu'],
            ['Raising income without a new loan', 'Kuongeza kipato bila mkopo mpya'],
            ['Payday rules that protect the rest of the month', 'Sheria za siku ya malipo'],
            ['A second skill that fits evenings', 'Stadi ya pili ya jioni'],
            ['When the job pays late', 'Kazi inapolipa kuchelewa'],
            ['Transport to work vs staying closer', 'Usafiri wa kazi dhidi ya kukaa karibu'],
            ['Overtime that costs more than it pays', 'Saa za ziada zinazogharimu'],
            ['Keeping work money out of the shop till', 'Pesa ya kazi nje ya duka'],
            ['A weekly wage envelope', 'Bahasha ya mshahara wa wiki'],
            ['Asking for the rate you need', 'Kuuliza kiwango unachohitaji'],
            ['Tools that pay for themselves', 'Zana zinazojilipa'],
            ['Seasonal work and the quiet months', 'Kazi ya msimu na miezi tulivu'],
            ['Recording every cash job the same day', 'Kuandika kila kazi ya taslimu'],
            ['When two jobs steal your rest', 'Kazi mbili zikiiba pumziko'],
            ['A raise, a cost, and leftover', 'Ongezeko, gharama, na salio'],
            ['Leaving a job that eats the household', 'Kuacha kazi inayokula nyumba'],
            ['Apprenticeship money and patience', 'Pesa ya ujuzi na uvumilivu'],
            ['Phone jobs and real cash', 'Kazi za simu na pesa halisi'],
            ['A simple income floor for the month', 'Kiwango cha chini cha kipato'],
            ['Sharing work news with the household', 'Kushiriki habari za kazi nyumbani'],
            ['Saving from a bonus, not spending it all', 'Kuweka kutoka bonus'],
            ['When gigs cluster then vanish', 'Kazi ndogo zinapofika kisha kuisha'],
            ['Your time has a price too', 'Muda wako una bei'],
        ]);
    }

    private function pricing(): array
    {
        return $this->pack([
            ['Price that covers more than the buy price', 'Bei inayofunika zaidi ya kununua'],
            ['Margin without scary words', 'Faida ndogo bila maneno magumu'],
            ['Discounting without teaching people to wait', 'Punguzo bila kufundisha kusubiri'],
            ['A price for friends, a price for the shop', 'Bei ya marafiki, bei ya duka'],
            ['When costs moved and you did not', 'Gharama zilipobadilika'],
            ['Bundling two things people already buy', 'Kuoanisha vitu viwili'],
            ['The cheapest item can still lose money', 'Bidhaa rahisi bado inaweza kuhasarisha'],
            ['Rounding that still feels fair', 'Kuzungusha bei kwa insafu'],
            ['A chalkboard price you can defend', 'Bei ya ubaoni unayoweza kutetea'],
            ['Wholesale vs one-by-one', 'Jumla dhidi ya moja-moja'],
            ['Transport into the selling price', 'Usafiri ndani ya bei'],
            ['Waste, spoilage, and the real cost', 'Hasara na bei halisi'],
            ['A weekly check: am I still making money?', 'Ukaguzi wa wiki: bado ninafaida?'],
            ['Matching a neighbour without a race to zero', 'Kufuata jirani bila bei sifuri'],
            ['Service price vs product price', 'Bei ya huduma dhidi ya bidhaa'],
            ['When a customer says it is too much', 'Mteja akisema ni ghali'],
            ['A small rise done early', 'Ongezeko dogo mapema'],
            ['Free extras that quietly cost you', 'Bure linalokugharimu'],
            ['Pricing a new item you have never sold', 'Kuweka bei ya bidhaa mpya'],
            ['Peak hours and a different mix', 'Saa zenye kazi na mchanganyiko'],
            ['Writing cost and sell on one line', 'Gharama na bei mstari mmoja'],
            ['Profit is leftover, not noise', 'Faida ni salio, si kelele'],
            ['A price list you actually use', 'Orodha ya bei unayotumia'],
            ['Seasonal prices without confusing people', 'Bei za msimu bila kuchanganya'],
            ['Knowing the number before you bargain', 'Kujua namba kabla ya kubargeni'],
        ]);
    }

    private function customers(): array
    {
        return $this->pack([
            ['Giving credit to customers carefully', 'Kutoa deni kwa wateja kwa busara'],
            ['Collecting what you are owed without a fight', 'Kukusanya bila ugomvi'],
            ['Keeping a customer coming back', 'Kumtunza mteja arudi'],
            ['A difficult customer and a clear boundary', 'Mteja mgumu na mpaka wazi'],
            ['Names, numbers, and what they usually buy', 'Majina, namba, na wanachonunua'],
            ['A reminder that is polite', 'Kumbusho lenye heshima'],
            ['When a regular starts paying late', 'Mzoefu anapoanza kuchelewa'],
            ['Credit limits for people you like', 'Kikomo cha deni kwa unaowapenda'],
            ['Thanking a customer who paid on time', 'Kumshukuru aliyelipa kwa wakati'],
            ['New customers from people who already trust you', 'Wateja wapya kutoka wanaokuamini'],
            ['WhatsApp lists that stay useful', 'Orodha ya WhatsApp yenye manufaa'],
            ['When to stop supplying on credit', 'Wakati wa kuacha kuuza kwa deni'],
            ['A small extra for loyalty, not a hole', 'Kiongezeo cha uaminifu, si tundu'],
            ['Handling a complaint the same day', 'Malalamiko siku hiyo hiyo'],
            ['The customer who only wants the cheapest', 'Anayetaka rahisi tu'],
            ['Writing who owes you every evening', 'Kuandika anayedaiwa kila jioni'],
            ['Family as customers — same rules', 'Familia wakiwa wateja — sheria zilezile'],
            ['A quiet week and checking in', 'Wiki tulivu na kuwasiliana'],
            ['Promises you can keep at the counter', 'Ahadi unazoweza kutimiza'],
            ['When collecting funds a Kopafasta repayment', 'Ukikusanya unalipa Kopafasta'],
            ['Respect first, then the amount', 'Heshima kwanza, kisha kiasi'],
            ['A lost customer is information', 'Mteja aliyepotea ni taarifa'],
            ['Group buyers and one invoice', 'Wanunuzi wa kundi na ankara moja'],
            ['Remembering the last order', 'Kukumbuka oda ya mwisho'],
            ['Trust at the counter, records in the book', 'Imani kaunta, kumbukumbu daftari'],
        ]);
    }

    private function stock(): array
    {
        return $this->pack([
            ['How much stock is enough this week', 'Stock kiasi gani kinatosha wiki hii'],
            ['Dead stock is a silent loan to the shelf', 'Stock iliyokufa ni mkopo kimya'],
            ['Choosing a supplier you can count on', 'Kuchagua msambazaji wa kuaminika'],
            ['Buying in bulk without trapping cash', 'Kununua jumla bila kufunga pesa'],
            ['A list before you leave for the market', 'Orodha kabla ya soko'],
            ['What sold, what sat', 'Kilichouzwa, kilichokaa'],
            ['Fresh goods and a shorter clock', 'Bidhaa mbichi na saa fupi'],
            ['One slow item you keep “just in case”', 'Bidhaa moja ya “labda”'],
            ['Transport home is part of the buy', 'Usafiri ni sehemu ya ununuzi'],
            ['Counting stock on a quiet morning', 'Kuhesabu stock asubuhi tulivu'],
            ['When the supplier raises the price', 'Msambazaji anapopandisha bei'],
            ['A second supplier for when the first fails', 'Msambazaji wa pili'],
            ['Shop space is not free', 'Nafasi ya duka si bure'],
            ['Ordering with leftover, not with hope', 'Kuagiza kwa salio, si tumaini'],
            ['Broken, expired, gone — write it', 'Iliovunjika, iliyoisha — andika'],
            ['Seasonal stock without filling the room', 'Stock ya msimu bila kujaza chumba'],
            ['Paying a supplier vs paying the house', 'Kulipa msambazaji dhidi ya nyumba'],
            ['A small fast item that funds a slow one', 'Bidhaa ya haraka inayofadhili ya pole'],
            ['Taking photos of stock levels', 'Picha za stock'],
            ['The buy you regret by Thursday', 'Ununuzi unaojuta Alhamisi'],
            ['Sharing a bulk buy with another seller', 'Kugawana jumla na muuzaji mwingine'],
            ['Keeping personal shopping out of the stock run', 'Manunuzi ya nyumbani nje ya stock'],
            ['A maximum you will not cross this week', 'Kima cha juu wiki hii'],
            ['When empty shelves are a choice', 'Rafu tupu kama chaguo'],
            ['Stock spending belongs in business, not money-out only', 'Matumizi ya stock ni ya biashara'],
        ]);
    }

    private function payments(): array
    {
        return $this->pack([
            ['Mobile money costs that add up', 'Gharama za simu zinazoongezeka'],
            ['A receipt you can find later', 'Risiti unayoweza kuona baadaye'],
            ['Avoiding a payment to the wrong number', 'Kuepuka kulipia namba isiyo sahihi'],
            ['Confirm before you send', 'Thibitisha kabla ya kutuma'],
            ['Keeping a simple payment book', 'Daftari rahisi la malipo'],
            ['Cash vs float — know which you used', 'Taslimu au float'],
            ['When a payment delays', 'Malipo yanapochelewa'],
            ['Screenshots that actually help', 'Picha za skrini zenye manufaa'],
            ['Paying Kopafasta on the same page every time', 'Kulipa Kopafasta ukurasa uleule'],
            ['A PIN that stays yours', 'PIN inayobaki yako'],
            ['Sending money for someone else', 'Kutuma kwa niaba ya mtu'],
            ['Charges on small amounts', 'Tozo kwenye kiasi kidogo'],
            ['When the agent is closed', 'Wakala akifungwa'],
            ['Matching a name to a number', 'Kulinganisha jina na namba'],
            ['A weekly check of sent and received', 'Ukaguzi wa wiki wa kutuma na kupokea'],
            ['Do not pay twice because you were rushed', 'Usilipe mara mbili kwa haraka'],
            ['Float for the shop, money for the house', 'Float ya duka, pesa ya nyumba'],
            ['What to do if a payment vanishes', 'Malipo yakitoweka'],
            ['Teaching family the confirm step', 'Kufundisha familia kuthibitisha'],
            ['Airtime is not the same as float', 'Salio si float'],
            ['A written reason next to each send', 'Sababu andishi kando ya kila tuma'],
            ['End of day: three payments to check', 'Mwisho wa siku: malipo matatu'],
            ['Bank, mobile, cash — one picture', 'Benki, simu, taslimu — picha moja'],
            ['Quiet hours and still getting paid', 'Saa za utulivu bado unalipwa'],
            ['Your records beat a memory', 'Kumbukumbu zinashinda kumbukumbu ya kichwa'],
        ]);
    }

    private function safety(): array
    {
        return $this->pack([
            ['A PIN nobody else should know', 'PIN ambayo mwingine asijue'],
            ['Fake investment stories', 'Hadithi za uwekezaji bandia'],
            ['A call that asks you to send “to check”', 'Simu inayokuomba utume “kukagua”'],
            ['Protecting mobile money on a shared phone', 'Kulinda pesa ya simu kwenye simu ya pamoja'],
            ['Jobs that promise too much too fast', 'Kazi zinazoahidi mengi haraka'],
            ['A message with a link you did not ask for', 'Ujumbe wenye kiungo usichoomba'],
            ['Keeping Kopafasta login to yourself', 'Login ya Kopafasta ibaki yako'],
            ['When a relative asks for your PIN “just once”', 'Ndugu akiomba PIN “mara moja”'],
            ['Shop cameras and cash on the counter', 'Kamera na taslimu kaunta'],
            ['A second look at a new agent', 'Kuona wakala mpya mara ya pili'],
            ['Lottery, prizes, and fees to claim', 'Bahati nasibu na tozo za kudai'],
            ['Photos of IDs sitting in a chat', 'Picha za vitambulisho kwenye chat'],
            ['If you sent money to a thief', 'Ukituma kwa mwizi'],
            ['A password that is not a birthday', 'Nenosiri si tarehe ya kuzaliwa'],
            ['Public Wi‑Fi and money apps', 'Wi‑Fi ya umma na programu za pesa'],
            ['Someone standing too close at the agent', 'Mtu aliyesimama karibu wakala'],
            ['A “Kopafasta staff” who asks for a PIN', '“Mfanyakazi” anayeomba PIN'],
            ['Locking the phone you trade on', 'Kufunga simu unayofanyia kazi'],
            ['Children and the float', 'Watoto na float'],
            ['Changing a PIN after a scare', 'Kubadilisha PIN baada ya hofu'],
            ['Written numbers vs numbers from memory', 'Namba andishi dhidi ya kumbukumbu'],
            ['A friend who wants to “help you invest”', 'Rafiki wa “kuwekeza”'],
            ['Night sends and extra care', 'Kutuma usiku kwa uangalifu'],
            ['Your records help if something goes wrong', 'Kumbukumbu zinasaidia kitu kikivunjika'],
            ['Trust people, verify the payment', 'Amini watu, thibitisha malipo'],
        ]);
    }

    private function insurance(): array
    {
        return $this->pack([
            ['Why insurance exists in one page', 'Bima ipo kwa nini — ukurasa mmoja'],
            ['Protecting a small business from one shock', 'Kulinda biashara dhidi ya mshtuko'],
            ['Health costs that can wait vs cannot', 'Afya inayoweza kusubiri'],
            ['Reading what is covered before you cheer', 'Kusoma kinachofunikwa'],
            ['Family protection that is not a rumour', 'Ulinzi wa familia si uvumi'],
            ['A partner offer vs a product you chose', 'Ofa ya mshirika dhidi ya ulichochagua'],
            ['When a premium is due', 'Malipo ya bima yanapofika'],
            ['Assets, papers, and a claim', 'Mali, karatasi, na dai'],
            ['Not every “cover” is the same', 'Sio kila “cover” ni sawa'],
            ['Asking three questions before you buy', 'Maswali matatu kabla ya kununua'],
            ['Business interruption in plain words', 'Kukatizwa kwa biashara kwa maneno rahisi'],
            ['A funeral that should not start a loan', 'Mazishi yasiyoanza mkopo'],
            ['Keeping beneficiary names updated', 'Kusasisha majina ya wanufaika'],
            ['If nobody explains it, wait', 'Asipoeleza, subiri'],
            ['Plus offers are not automatic cover', 'Ofa za Plus si bima otomatiki'],
            ['A receipt for every premium', 'Risiti ya kila malipo'],
            ['Matching cover to the thing you could not replace', 'Kufunika kitu usichoweza kubadilisha'],
            ['Group schemes and what you actually get', 'Mipango ya kundi na unachopata'],
            ['When to say not yet', 'Wakati wa kusema bado'],
            ['Health, tools, and stock — different risks', 'Afya, zana, stock — hatari tofauti'],
            ['A claim is paperwork plus honesty', 'Dai ni karatasi na ukweli'],
            ['Do not skip a payment then hope', 'Usiruke malipo kisha utumaini'],
            ['Reading exclusions without fear', 'Kusoma isiyofunikwa bila hofu'],
            ['Asking Kopafasta what an offer really is', 'Kuuliza Kopafasta ofa ni nini'],
            ['Protection is a plan, not a product name', 'Ulinzi ni mpango, si jina'],
        ]);
    }

    private function home(): array
    {
        return $this->pack([
            ['Renting vs buying — this year’s question', 'Kupanga au kununua — swali la mwaka'],
            ['Building a house in stages', 'Kujenga nyumba hatua kwa hatua'],
            ['Repairs that wait become bigger bills', 'Matengenezo yanayosubiri'],
            ['A room that earns vs a room that costs', 'Chumba kinacholeta dhidi ya kinachogharimu'],
            ['Land, papers, and patience', 'Ardhi, karatasi, na uvumilivu'],
            ['A home goal with a real date', 'Lengo la nyumba na tarehe'],
            ['Furniture last, walls first', 'Samani mwisho, kuta kwanza'],
            ['Water, power, and the quiet bills', 'Maji, umeme, na bili tulivu'],
            ['Sharing a plot without mixing money', 'Kugawana kiwanja bila kuchanganya pesa'],
            ['A leak you can see vs one you will feel', 'Uvujaji unaoonekana'],
            ['Moving house without starting a debt', 'Kuhama bila kuanza deni'],
            ['Neighbours, boundaries, and receipts', 'Jirani, mipaka, na risiti'],
            ['A small upgrade that saves every month', 'Kuboresha kidogo kila mwezi'],
            ['Keeping home money out of the shop', 'Pesa ya nyumba nje ya duka'],
            ['When family land is not the same as your name', 'Ardhi ya familia si jina lako'],
            ['A maintenance jar next to the rent jar', 'Kikombe cha matengenezo'],
            ['Building slowly still counts', 'Kujenga polepole bado ni kujenga'],
            ['A roof before a celebration', 'Paa kabla ya sherehe'],
            ['Counting what the house already costs', 'Kuhesabu gharama za nyumba sasa'],
            ['Assets you can use, not only own', 'Mali unazotumia, si kumiliki tu'],
            ['A written plan for the next wall', 'Mpango andishi wa ukuta unaofuata'],
            ['Borrowing for a house vs saving for a house', 'Kukopa nyumba dhidi ya kuweka'],
            ['Guests, rent, and the month’s peace', 'Wageni, kodi, na amani ya mwezi'],
            ['Tools for the house that pay back', 'Zana za nyumbani zinazojilipa'],
            ['Home is a goal you can open in Plus', 'Nyumba ni lengo unalofungua Plus'],
        ]);
    }

    private function farming(): array
    {
        return $this->pack([
            ['Seasonal money and the months between', 'Pesa ya msimu na miezi kati'],
            ['Planning the harvest before it arrives', 'Kupanga mavuno kabla hayajafika'],
            ['Farm costs that hide in bits', 'Gharama za shamba zinazojificha'],
            ['Protecting income between seasons', 'Kulinda kipato kati ya misimu'],
            ['A farm book with three columns', 'Daftari la shamba na safu tatu'],
            ['Inputs now, cash later', 'Pembejeo sasa, pesa baadaye'],
            ['When the rain is late', 'Mvua inapochelewa'],
            ['Selling too fast vs waiting too long', 'Kuuza haraka au kusubiri sana'],
            ['A small food plot and a cash crop', 'Bustani ndogo na zao la pesa'],
            ['Transport to market is a farm cost', 'Usafiri soko ni gharama ya shamba'],
            ['Sharing labour without mixing accounts', 'Kugawana kazi bila kuchanganya'],
            ['A harvest goal in Plus', 'Lengo la mavuno kwenye Plus'],
            ['Debt that assumes a perfect season', 'Deni linalotegemea msimu kamili'],
            ['Keeping house food out of the sale pile', 'Chakula cha nyumba nje ya kuuza'],
            ['A quiet season job that is planned', 'Kazi ya msimu tulivu iliyopangwa'],
            ['Animals, feed, and leftover', 'Mifugo, chakula, na salio'],
            ['When a buyer offers cash today, less later', 'Mnunuzi wa taslimu leo'],
            ['Weather is not a plan — a buffer is', 'Hali ya hewa si mpango'],
            ['Seeds, tools, and what must wait', 'Mbegu, zana, na kinachosubiri'],
            ['Recording a farm expense the day it happens', 'Kuandika gharama siku hiyo'],
            ['Co-op money and your own book', 'Pesa ya kundi na daftari lako'],
            ['A failed crop and the next step', 'Mazao yalivyoshindwa na hatua'],
            ['Water, fuel, and the week’s cash', 'Maji, mafuta, na pesa ya wiki'],
            ['Farming and a small shop that carry each other', 'Kilimo na duka vinavyobeba'],
            ['The season is a calendar, not a surprise', 'Msimu ni kalenda, si mshangao'],
        ]);
    }

    private function digital(): array
    {
        return $this->pack([
            ['Selling on WhatsApp without losing orders', 'Kuuza WhatsApp bila kupoteza oda'],
            ['Digital payments that match the sale', 'Malipo ya dijitali yanayolingana na mauzo'],
            ['A simple way to be found', 'Njia rahisi kupatikana'],
            ['Keeping customer names in one place', 'Majina ya wateja mahali pamoja'],
            ['Photos that tell the truth about stock', 'Picha za stock za kweli'],
            ['Status updates that bring people back', 'Status zinazorudisha watu'],
            ['When a chat is an order', 'Chat inapokuwa oda'],
            ['Delivery promises you can keep', 'Ahadi za kufikisha unazotimiza'],
            ['A second number for the shop', 'Namba ya pili ya duka'],
            ['Broadcasts without spamming', 'Kutuma bila kusumbua'],
            ['Taking payment before you travel', 'Kulipwa kabla ya kusafiri'],
            ['A lost phone and the shop still standing', 'Simu kupotea duka likisimama'],
            ['Simple records on the same phone you sell with', 'Kumbukumbu kwenye simu uleule'],
            ['When online talk does not equal cash', 'Mazungumzo si taslimu'],
            ['A weekly look at which chats became sales', 'Wiki: chat zipi zilikuwa mauzo'],
            ['Groups that help vs groups that distract', 'Makundi yanayosaidia'],
            ['A clear price in the caption', 'Bei wazi kwenye maelezo'],
            ['Returns, complaints, and a calm reply', 'Kurudisha na jibu tulivu'],
            ['Data bundles as a business cost', 'Bundles ni gharama ya biashara'],
            ['Showing up every day without shouting', 'Kuonekana kila siku bila kelele'],
            ['A backup of important chats', 'Nakala ya chat muhimu'],
            ['Mixing friends and customers on one thread', 'Marafiki na wateja kwenye thread moja'],
            ['A catalog that is just your best five', 'Katalogi ya vitu vitano'],
            ['Closing the day: chats, cash, leftover', 'Kufunga siku: chat, pesa, salio'],
            ['Digital is still the same three numbers', 'Digitali bado namba tatu'],
        ]);
    }

    private function tax(): array
    {
        return $this->pack([
            ['Why a record today saves a headache later', 'Kumbukumbu leo inaokoa kesho'],
            ['Receipts in one place', 'Risiti mahali pamoja'],
            ['Separating tax talk from fear', 'Kodi bila hofu'],
            ['A simple book the shop can keep', 'Daftari rahisi la duka'],
            ['What you sold, what you spent, what is left', 'Mauzo, matumizi, salio'],
            ['A photo of a receipt is still a receipt', 'Picha ya risiti ni risiti'],
            ['Month-end in thirty minutes', 'Mwisho wa mwezi dakika 30'],
            ['When someone asks for proof', 'Mtu anapoomba uthibitisho'],
            ['Business money and personal money on paper', 'Pesa ya biashara na binafsi karatasini'],
            ['A name on every large spend', 'Jina kwenye matumizi makubwa'],
            ['Keeping Plus reports as your monthly picture', 'Ripoti ya Plus kama picha ya mwezi'],
            ['Do not wait for a perfect system', 'Usisubiri mfumo kamili'],
            ['Invoices you can send from a phone', 'Ankara kutoka simu'],
            ['A folder for the year', 'Folda ya mwaka'],
            ['When numbers do not match the till', 'Namba zisizolingana na duka'],
            ['Asking for a receipt every time', 'Kuomba risiti kila mara'],
            ['Helpers and a shared book', 'Wasaidizi na daftari la pamoja'],
            ['A signature, a date, an amount', 'Sahihi, tarehe, kiasi'],
            ['Digital copies if paper tears', 'Nakala dijitali karatasi ikiraruka'],
            ['Quiet compliance is just good habits', 'Kuzingatia ni tabia nzuri'],
            ['What to keep, what to let go', 'Nini kutunza, nini kuacha'],
            ['A weekly envelope of paper', 'Bahasha ya karatasi kila wiki'],
            ['Telling the truth in your own book first', 'Ukweli kwenye daftari lako kwanza'],
            ['Reports you can share with a partner', 'Ripoti ya kushiriki na mwenza'],
            ['Records make Trust easier to see', 'Kumbukumbu zinaonyesha Trust'],
        ]);
    }

    private function growth(): array
    {
        return $this->pack([
            ['Discipline is a small action repeated', 'Nidhamu ni hatua ndogo inayorudiwa'],
            ['Resilience is leftover after a hard week', 'Uvumilivu ni salio baada ya wiki ngumu'],
            ['Thinking past this month without fear', 'Kufikiri mwezi ujao bila hofu'],
            ['A habit that outlives a mood', 'Tabia inayodumu zaidi ya hisia'],
            ['Growing Trust by keeping promises', 'Kukuza Trust kwa kutimiza ahadi'],
            ['One better week, then another', 'Wiki bora, kisha nyingine'],
            ['Saying no to a shiny shortcut', 'Hapana kwa njia fupi yenye mng’ao'],
            ['Learning 5 minutes, then doing 1 thing', 'Kujifunza dakika 5, kisha kitu kimoja'],
            ['Comparing yourself to last month, not a neighbour', 'Kujilinganisha na mwezi uliopita'],
            ['A year made of ordinary Tuesdays', 'Mwaka wa Jumanne za kawaida'],
            ['Rest is part of the plan', 'Pumziko ni sehemu ya mpango'],
            ['Asking for a lesson when you are stuck', 'Kuomba somo ukikwama'],
            ['Celebrating leftover, not only sales', 'Kusherehekea salio, si mauzo tu'],
            ['A person you tell the truth about money', 'Mtu unayemwambia ukweli wa pesa'],
            ['Stopping a leak before adding a stream', 'Kuzuia mianya kabla ya kuongeza mto'],
            ['Patience with a goal that is 40%', 'Uvumilivu lengo likiwa 40%'],
            ['Your future self at the market', 'Wewe wa baadaye sokoni'],
            ['When growth means fewer, better sales', 'Kukua ni mauzo machache bora'],
            ['Kopafasta as a companion, not only a lender', 'Kopafasta ni mwenzako, si mkopeshaji tu'],
            ['A monthly look back without shame', 'Kuangalia mwezi bila aibu'],
            ['Teaching someone one thing you learned', 'Kumfundisha mtu kitu kimoja'],
            ['Keeping Plus useful on days you are not borrowing', 'Plus yenye manufaa siku usizokopa'],
            ['A next step smaller than your fear', 'Hatua ndogo kuliko hofu'],
            ['Strong is often just consistent', 'Nguvu mara nyingi ni mfululizo'],
            ['The point is a life that holds', 'Lengo ni maisha yanayoshikilia'],
        ]);
    }

    /** @param list<array{0: string, 1: string}> $pairs */
    private function pack(array $pairs): array
    {
        $out = [];
        foreach ($pairs as $i => $pair) {
            $out[] = [$pair[0], $pair[1], $i === 0 ? 6 : 5];
        }

        return $out;
    }
}
