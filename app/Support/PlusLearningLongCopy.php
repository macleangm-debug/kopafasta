<?php

namespace App\Support;

class PlusLearningLongCopy
{
    /**
     * Full paragraphs for a published subject. Title and lead live on the page
     * already, so the body starts at why/how and does not expand every draft topic.
     *
     * @param  array<string, string>  $bundle
     * @return list<string>
     */
    public static function paragraphs(string $slug, string $locale, array $bundle): array
    {
        $paras = [];
        foreach (['why', 'how'] as $key) {
            $text = trim((string) ($bundle[$key] ?? ''));
            if ($text !== '') {
                $paras[] = $text;
            }
        }

        foreach (self::core($slug, $locale) as $block) {
            $text = trim($block);
            if ($text !== '') {
                $paras[] = $text;
            }
        }

        $today = trim((string) ($bundle['today'] ?? ''));
        if ($today !== '') {
            $paras[] = $today;
        }

        $clean = [];
        $last = null;
        foreach ($paras as $para) {
            $norm = mb_strtolower($para);
            if ($norm === $last) {
                continue;
            }
            $clean[] = $para;
            $last = $norm;
        }

        return $clean;
    }

    /** @return list<string> */
    private static function core(string $slug, string $locale): array
    {
        $all = [
            'money' => [
                'en' => [
                    'A month without a picture feels like money vanished. It rarely vanished. It left through food, travel, a helper, a small top-up, and a promise you did not write down.',
                    'Keep three numbers only: what came in, what went out, and what is left. Split leftover into must-haves, coming-soon, and later. Later is where a small save or a goal lives.',
                    'Do this on the day money moves — not on Sunday when the week is already a blur. One line is enough. Tomorrow the card already shows the month moving.',
                    'Uneven income is not a reason to skip the picture. A good Tuesday and a quiet Thursday still belong on the same card. That is how leftover stops being a surprise.',
                    'If you remember only the large amounts, the small leaks win. Airtime, a snack, a short ride — written, they look small. Unwritten, they empty Thursday.',
                    'You do not need a fancy book. You need a habit: write it, then look at leftover before the rest of the week spends it. Plus is that habit on days you are not borrowing.',
                ],
                'sw' => [
                    'Mwezi usio na picha huhisi pesa imepotea. Mara chache imepotea. Imetoka kwa chakula, usafiri, msaada, top-up, na ahadi ambayo hukuandika.',
                    'Shika namba tatu tu: zilizoingia, zilizotoka, na salio. Gawa salio: ya lazima, yanayokuja, na ya baadaye. Ya baadaye ndipo akiba ndogo au lengo linakaa.',
                    'Fanya hivi siku pesa inaposogea — si Jumapili wiki ikiwa tayari imefifia. Mstari mmoja unatosha. Kesho kadi inaonyesha mwezi ukisogea.',
                    'Kipato kisicho thabiti si sababu ya kuruka picha. Jumanne nzuri na Alhamisi tulivu bado ziko kadi ileile. Ndivyo salio linaacha kuwa mshangao.',
                    'Ukikumbuka kiasi kikubwa tu, mianya midogo inashinda. Bundle, kiamsha kinywa, safari fupi — vikiandikwa vinaonekana vidogo. Bila kuandikwa, vinamaliza Alhamisi.',
                    'Huhitaji daftari ghali. Unahitaji tabia: andika, kisha angalia salio kabla wiki haijaitumia. Plus ndiyo tabia hiyo siku usizokopa.',
                ],
            ],
            'saving' => [
                'en' => [
                    'Waiting for a big leftover is how saving never starts. A reachable save is a number you can add on an ordinary day, even a small one, kept in a named place.',
                    'Name it: emergency, school, stock, home. Put it in Goals. Add on days money comes in — before the week borrows it back. Write it as already gone.',
                    'Protection is not a lecture. It is a jar that is not mixed with rent, food, or the shop till. When the week is hard, the jar is still there.',
                    'Skip the shame of a small start. Shame delays the first add. The bar only moves when you add. That is the whole method.',
                ],
                'sw' => [
                    'Kusubiri salio kubwa ndiyo namna akiba haitaanza. Akiba inayofikiwa ni kiasi unachoweza kuongeza siku ya kawaida, hata kidogo, mahali palipo na jina.',
                    'Ipe jina: dharura, ada, bidhaa, nyumba. Iweke kwenye Malengo. Ongeza siku pesa inapoingia — kabla wiki haijaikopa. Iandike kama tayari imetoka.',
                    'Ulinzi si somo. Ni kikombe kisichochanganywa na kodi, chakula, au duka. Wiki ikiwa ngumu, kikombe bado kipo.',
                    'Acha aibu ya kuanza kidogo. Aibu inachelewesha ongezo la kwanza. Mstari unasogea unapoongeza. Ndivyo njia ilivyo.',
                ],
            ],
            'business' => [
                'en' => [
                    'A quiet day still happened. If it is not written, last week and this week look the same, and you cannot tell whether the shop is helping the home or the home is eating the shop.',
                    'Write what you sold and what the shop spent. The difference is the only number that matters today. One sale still counts. A zero day still counts if you write the spend.',
                    'Stock on a shelf is money sitting still. Buying without writing it hides the week. Price that only covers the buy price is not a plan — it is hope.',
                    'Customers return when the shop is calm, priced clearly, and remembered. Chat is not cash. Cash is what you write in the card the day it arrives.',
                ],
                'sw' => [
                    'Siku tulivu bado ilitokea. Ikiwa haijaandikwa, wiki hii na iliypita zinafanana, na huwezi kuona kama duka linasaidia nyumba au nyumba inakula duka.',
                    'Andika ulichouza na duka lilichotumia. Tofauti ndiyo namba ya leo. Mauzo moja bado yanahesabika. Siku ya sifuri bado inahesabika ukiandika matumizi.',
                    'Stock rafuni ni pesa iliyokaa. Kununua bila kuandika kunaficha wiki. Bei inayofunika bei ya kununua tu si mpango — ni tumaini.',
                    'Wateja wanarudi duka likiwa tulivu, bei wazi, na likikumbukwa. Chat si taslimu. Taslimu ndiyo unaandika kwenye kadi siku inapofika.',
                ],
            ],
            'loans' => [
                'en' => [
                    'Borrowing is a tool. The story that matters is the one you keep when you are not borrowing: writing money, moving a goal, opening the month without shame.',
                    'A loan does not fix a month with no picture. Trust grows from keeping promises — paying on time, writing the week, finishing a small target. Plus never asks you to borrow to look active.',
                    'Use Plus for the days between loans. When you do borrow, you already know what the month can carry. That is the useful order.',
                ],
                'sw' => [
                    'Kukopa ni chombo. Hadithi muhimu ni ile unayoendelea nayo siku usizokopa: kuandika pesa, kusogeza lengo, kufungua mwezi bila aibu.',
                    'Mkopo hauwezi kurekebisha mwezi usio na picha. Imani inakua kwa kutimiza ahadi — kulipa kwa wakati, kuandika wiki, kumaliza lengo dogo. Plus haikuombi ukope ili uonekane mwenye bidii.',
                    'Tumia Plus siku kati ya mikopo. Ukitaka kukopa, tayari unajua mwezi unachoweza kubeba. Huo ndio mpangilio wenye manufaa.',
                ],
            ],
            'debt' => [
                'en' => [
                    'A repayment that “suddenly” arrives usually sat in the calendar all along. Fear grows in the dark. Three coming amounts, with dates, turn panic into a plan.',
                    'Keep household money and shop money apart so one debt does not empty both. Pay the promised amount. Do not skip writing the week just because a bill is due.',
                    'List what is coming. Then write today’s out so the card stays true. The list without today’s line is still a guess.',
                ],
                'sw' => [
                    'Malipo “ya ghafla” mara nyingi yalikuwa kwenye kalenda. Hofu inakua gizani. Kiasi vitatu vinavyokuja, na tarehe, vinageuza hofu kuwa mpango.',
                    'Tenganisha pesa ya nyumbani na ya duka ili deni moja lisitumie zote. Lipa kiasi ulichoahidi. Usiache kuandika wiki kwa sababu bili inakuja.',
                    'Orodhesha yanayokuja. Kisha andika zilizotoka leo ili kadi ibaki ya kweli. Orodha bila mstari wa leo bado ni dhana.',
                ],
            ],
            'goals' => [
                'en' => [
                    'A named target with a date beats a wish that waits for leftover money. Leftover rarely arrives on its own. Adding a little on a good day is the method.',
                    'Pick one kind. Set the amount. Choose a date after today on the calendar. Add progress when money comes in — before the week spends it.',
                    'The bar is honest. It only moves when you add. That is why a small add on an ordinary Tuesday is worth more than a big plan you never open.',
                ],
                'sw' => [
                    'Lengo lenye jina na tarehe linashinda tamaa inayosubiri salio. Salio mara chache linakuja peke yake. Kuongeza kidogo siku nzuri ndiyo njia.',
                    'Chagua aina. Weka kiasi. Chagua tarehe baada ya leo kwenye kalenda. Ongeza pesa inapoingia — kabla wiki haijaitumia.',
                    'Mstari ni wa kweli. Unasogea unapoongeza. Ndiyo maana ongezo dogo Jumanne ya kawaida lina thamani kuliko mpango mkubwa usiofunguliwa.',
                ],
            ],
            'family' => [
                'en' => [
                    'Household money calms down when everyone can see the same three numbers. Without a picture, every ask feels like an emergency.',
                    'Agree a small “later” amount in advance — a save or a goal already spoken. Giving can still happen. It has a place, and a week that is not this one.',
                    'Show the card at home if it helps. A shared picture is kinder than a surprise no.',
                ],
                'sw' => [
                    'Pesa ya nyumbani inatulia kila mtu akinena namba zilezile tatu. Bila picha, kila ombi ni dharura.',
                    'Kubaliana kiasi kidogo cha “baadaye” mapema — akiba au lengo lililosemwa. Kutoa bado kunaweza. Kina nafasi, na wiki ambayo si hii.',
                    'Onyesha kadi nyumbani ikiwa inasaidia. Picha ya pamoja ni laini kuliko hapana ya ghafla.',
                ],
            ],
            'emergency' => [
                'en' => [
                    'An emergency is less shocking when a small, reachable amount already sits in a named place. It is not full insurance. It is a buffer so the week does not collapse.',
                    'Keep it apart from the shop and from school or stock goals. Add on good days. Use it only for the thing you named.',
                    'Start smaller than your fear. The first add is the habit. The habit is the protection.',
                ],
                'sw' => [
                    'Dharura inashangaza kidogo kama kiasi kidogo kinachofikiwa tayari kipo mahali palipo na jina. Si bima kamili. Ni kinga wiki isianguke.',
                    'Litenganishe na duka na lengo la ada au bidhaa. Ongeza siku nzuri. Litumie kwa kile ulichokiita tu.',
                    'Anza kidogo kuliko hofu. Ongezo la kwanza ndiyo tabia. Tabia ndiyo ulinzi.',
                ],
            ],
            'work' => [
                'en' => [
                    'Income that arrives on different days still needs a week picture. A good payday can disappear by Thursday if it is not split when it lands.',
                    'Write money in when it arrives — salary, a job, someone who paid you. Split must-haves, coming-soon, and later before the week spends it.',
                    'Do not wait for an official payday ritual. The day it arrives is the day you write it.',
                ],
                'sw' => [
                    'Kipato kinachofika siku tofauti bado kinahitaji picha ya wiki. Siku nzuri ya malipo inaweza kutoweka kufikia Alhamisi ikiwa haikugawanywa inapofika.',
                    'Andika zinazoingia zinapofika — mshahara, kazi, mtu aliyekulipa. Gawa ya lazima, yanayokuja, na ya baadaye kabla wiki haijaitumia.',
                    'Usisubiri taratibu rasmi ya malipo. Siku inapofika ndiyo siku ya kuandika.',
                ],
            ],
            'pricing' => [
                'en' => [
                    'Price and profit stay honest when a sale is written the same day. A price that “feels right” can still lose money if stock and spend stay invisible.',
                    'Write the sale. Write what the shop spent to make it. Look at the difference. Change the next price from that number, not from a neighbour’s rumour.',
                    'One honest day of numbers teaches more than a week of guessing.',
                ],
                'sw' => [
                    'Bei na faida zinakuwa za kweli mauzo yakandikwa siku ileile. Bei “inayohisi sawa” bado inaweza hasara kama stock na matumizi havionekani.',
                    'Andika mauzo. Andika duka lilichotumia kuyafanya. Angalia tofauti. Badilisha bei ijayo kutoka namba hiyo, si uvumi wa jirani.',
                    'Siku moja ya namba za kweli inafundisha zaidi ya wiki ya kukisia.',
                ],
            ],
            'customers' => [
                'en' => [
                    'Customers return when the shop is calm, priced clearly, and remembered. Many chats are not cash. The customer who pays and comes back is the business.',
                    'Write every sale, even a small one. Write quiet days too — they happened. Clear prices cut the bargaining that eats the week.',
                    'Credit given without a date is a gift you did not plan. If you give time, write the amount and the day it should return.',
                ],
                'sw' => [
                    'Wateja wanarudi duka likiwa tulivu, bei wazi, na likikumbukwa. Chat nyingi si taslimu. Mteja anayelipa na kurudi ndiye biashara.',
                    'Andika kila mauzo, hata madogo. Andika pia siku tulivu — zilitokea. Bei wazi inapunguza ubargaining unaokula wiki.',
                    'Mkopo bila tarehe ni zawadi usiyoipanga. Ukipe muda, andika kiasi na siku inayopaswa kurudi.',
                ],
            ],
            'stock' => [
                'en' => [
                    'Stock is money sitting on a shelf. Buying without writing it hides the week. A full shelf can look like a good day until you see the shop spent more than it sold.',
                    'Write shop spending the day it happens. Compare it with sales on the same card. Buy again only if leftover still leaves the home standing.',
                    'Enough stock this week is a number, not a feeling at the market.',
                ],
                'sw' => [
                    'Stock ni pesa rafuni. Kununua bila kuandika kunaficha wiki. Rafu imejaa inaweza kuonekana siku nzuri hadi uone duka lilitumia zaidi ya lililouza.',
                    'Andika matumizi ya duka siku ileile. Linganisha na mauzo kwenye kadi ileile. Nunua tena tofauti ikiwa bado inawaacha nyumbani wamesimama.',
                    'Stock ya kutosha wiki hii ni namba, si hisia sokoni.',
                ],
            ],
            'payments' => [
                'en' => [
                    'Paying and being paid both need a line on the same picture. Phone, cash, and a promise scatter the week if only the large ones are remembered.',
                    'Write money in when you are paid. Write money out when you pay. Put coming amounts in “coming soon” so Friday does not feel sudden.',
                    'A payment you will make next week is already part of leftover today. Treat it that way.',
                ],
                'sw' => [
                    'Kulipa na kulipwa vyote vinahitaji mstari kwenye picha ileile. Simu, taslimu, na ahadi vinatawanya wiki ukikumbuka kubwa tu.',
                    'Andika zinazoingia unapolipwa. Andika zinazotoka unapolipa. Yanayokuja yawekwe kwenye “yanayokuja” ili Ijumaa isihisi ghafla.',
                    'Malipo utakayofanya wiki ijayo tayari ni sehemu ya salio leo. Yachukulie hivyo.',
                ],
            ],
            'safety' => [
                'en' => [
                    'Money safety is a habit: PIN, separate places, and a record you can trust. Mixing one phone, one wallet, and no picture is how loss starts.',
                    'Split shop and home. Write amounts in Plus, not on a scrap that tears. Do not show leftover in public. A report you can open is safer than a memory.',
                    'Plus is a companion on days you are not borrowing — a place to see the month without sharing your PIN in the street.',
                ],
                'sw' => [
                    'Usalama wa pesa ni tabia: PIN, sehemu tofauti, na kumbukumbu unayoiamini. Kuchanganya simu moja, pochi moja, bila picha ndiyo hasara inavyoanza.',
                    'Tenganisha duka na nyumbani. Andika kiasi katika Plus, si karatasi inayoraruka. Usionyeshe salio hadharani. Ripoti unayoweza kufungua ni salama kuliko kumbukumbu kichwani.',
                    'Plus ni mwenzako siku usizokopa — mahali pa kuona mwezi bila kushiriki PIN mtaani.',
                ],
            ],
            'insurance' => [
                'en' => [
                    'Protection starts with seeing what would hurt — then using the offer that actually fits. It should not sound like a product pitch. It should sound like your month.',
                    'Read the offer. Claim only what you will use. Keep writing money so an emergency does not wipe the week. Offers sit in Plus; they are not a new loan, and they do not change Grade.',
                    'Know your month and your goal first. Then the offer has somewhere to land.',
                ],
                'sw' => [
                    'Ulinzi unaanza kwa kuona kitakachoumiza — kisha kutumia ofa inayokufaa. Isiwe kama bidhaa. Iwe kama mwezi wako.',
                    'Soma ofa. Dai ile utakayoitumia. Endelea kuandika pesa ili dharura isimalize wiki. Ofa ziko Plus; si mkopo mpya, na hazibadilishi Daraja.',
                    'Jua mwezi wako na lengo lako kwanza. Kisha ofa ina mahali pa kutua.',
                ],
            ],
            'home' => [
                'en' => [
                    'A home goal is a date and an amount — not a wish that waits for leftover. House plans stall when they stay spoken only.',
                    'Create the goal. Set the amount and a date after today. Add when money comes in, before the week spends it. Building slowly still counts.',
                    'Count what the house already costs this month. That number belongs in the picture before a new wall is a promise.',
                ],
                'sw' => [
                    'Lengo la nyumba ni tarehe na kiasi — si tamaa inayosubiri salio. Mipango ya nyumba inakwama inapobaki maneno tu.',
                    'Tengeneza lengo. Weka kiasi na tarehe baada ya leo. Ongeza pesa inapoingia, kabla wiki haijaitumia. Kujenga polepole bado ni kujenga.',
                    'Hesabu gharama za nyumba mwezi huu. Namba hiyo ni sehemu ya picha kabla ukuta mpya haujawa ahadi.',
                ],
            ],
            'farming' => [
                'en' => [
                    'Seasonal money still needs a month picture. Harvest is not a plan by itself. A good harvest can vanish between fees, stock, and family asks if it is not written.',
                    'Record money in when the season pays. Split must-haves, coming-soon, and later — seed, a save, a goal. Do not wait for the next harvest to start the picture.',
                    'Weather is not a plan. A buffer is. Inputs now and cash later belong on the same three numbers.',
                ],
                'sw' => [
                    'Pesa ya msimu bado inahitaji picha ya mwezi. Mavuno si mpango peke yake. Mavuno mazuri yanaweza kutoweka kati ya ada, bidhaa na maombi ya familia yakiwa hayajaandikwa.',
                    'Andika zinazoingia msimu unapolipa. Gawa ya lazima, yanayokuja, na ya baadaye — mbegu, akiba, lengo. Usisubiri mavuno yajayo kuanza picha.',
                    'Hali ya hewa si mpango. Kinga ndiyo mpango. Pembejeo sasa na pesa baadaye ziko kwenye namba zilezile tatu.',
                ],
            ],
            'digital' => [
                'en' => [
                    'A chat is not a till. Digital sales still need today’s number in the card. Online talk feels like work. Cash is what landed.',
                    'Write the sale when the money arrives, not when the message was sent. Treat data bundles as shop spending. Close the day with sold, spent, leftover.',
                    'A busy phone can hide an empty week. The card is the till, even when the shop is a status update.',
                ],
                'sw' => [
                    'Chat si duka. Mauzo ya simu bado yanahitaji namba ya leo kwenye kadi. Mazungumzo mtandaoni yanahisi kazi. Taslimu ndiyo iliyofika.',
                    'Andika mauzo pesa inapofika, si ujumbe ulipotumwa. Bundles ni matumizi ya duka. Funga siku: mauzo, matumizi, salio.',
                    'Simu yenye shughuli inaweza kuficha wiki tupu. Kadi ndiyo duka, hata duka likiwa status.',
                ],
            ],
            'tax' => [
                'en' => [
                    'A record today is a calmer month-end. Fear of “tax talk” is often fear of a missing book. You do not need a perfect system. You need sold, spent, leftover, kept as you go.',
                    'Use Plus reports as the monthly picture. Keep receipts — a photo is still a receipt. Name large spends. Do not wait for a folder to feel official.',
                    'Truth in your own book first. Then you can answer when someone asks for proof.',
                ],
                'sw' => [
                    'Kumbukumbu leo ni mwisho wa mwezi tulivu. Hofu ya “kodi” mara nyingi ni hofu ya daftari lisilokuwepo. Huhitaji mfumo kamili. Unahitaji mauzo, matumizi, salio, ukiendelea kuandika.',
                    'Tumia ripoti ya Plus kama picha ya mwezi. Tunga risiti — picha bado ni risiti. Andika matumizi makubwa na jina. Usisubiri folda ihisi rasmi.',
                    'Ukweli kwenye daftari lako kwanza. Kisha unaweza kujibu mtu anapoomba uthibitisho.',
                ],
            ],
            'growth' => [
                'en' => [
                    'Growth is a small action repeated — leftover after a hard week, then another ordinary Tuesday. Discipline is not a personality. It is writing the number, adding to a goal, and opening the month without shame.',
                    'Compare yourself to last month, not a neighbour. Learn, then do one thing in Plus. Rest is part of the plan. Skipping the record is not.',
                    'Kopafasta is a companion, not only a lender. Keep Plus useful on days you are not borrowing. Strong is often just consistent.',
                ],
                'sw' => [
                    'Kukua ni hatua ndogo inayorudiwa — salio baada ya wiki ngumu, kisha Jumanne nyingine ya kawaida. Nidhamu si tabia ya kuzaliwa. Ni kuandika namba, kuongeza kwenye lengo, na kufungua mwezi bila aibu.',
                    'Jilinganishe na mwezi uliopita, si jirani. Jifunze, kisha fanya kitu kimoja katika Plus. Pumziko ni sehemu ya mpango. Kuruka kumbukumbu siyo.',
                    'Kopafasta ni mwenzako, si mkopeshaji tu. Plus iwe na manufaa siku usizokopa. Nguvu mara nyingi ni mfululizo.',
                ],
            ],
        ];

        $localeKey = $locale === 'sw' ? 'sw' : 'en';

        return $all[$slug][$localeKey] ?? $all['money'][$localeKey];
    }
}
