<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\PlusBusinessEntry;
use App\Models\PlusGoal;
use App\Models\PlusLesson;
use App\Models\PlusLessonProgress;
use App\Models\PlusMoneyEntry;
use App\Models\PlusOffer;
use App\Models\PlusRewardLedger;
use App\Models\PlusSubject;
use App\Services\Grades\GradeBenefitService;
use App\Services\MemberEngagementService;
use App\Services\Plus\PlusLearningService;
use App\Services\Plus\PlusNextBestActionService;
use App\Services\Plus\PlusNotificationGate;
use App\Services\Plus\PlusService;
use App\Services\Plus\PlusWorkspaceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PlusController extends Controller
{
    public function home(
        Request $request,
        PlusService $plus,
        PlusWorkspaceService $workspace,
        PlusNextBestActionService $nba,
        PlusLearningService $learning,
        GradeBenefitService $benefits,
        MemberEngagementService $engagement,
    ) {
        $customer = $request->user()->customer;
        $plus->ensureSampleContent();
        $learning->ensureCatalog();
        $active = $plus->isActive($customer);
        $rawTrust = $engagement->trustScore($customer);
        $locale = app()->getLocale() === 'sw' ? 'sw' : 'en';
        $trust = $benefits->trustLabel((int) ($rawTrust['percent'] ?? 0), $locale);
        $access = $benefits->potentialAccess($customer);
        $summary = $active ? $workspace->homeSummary($customer) : null;

        return view('site.plus.home', [
            'customer' => $customer,
            'plusActive' => $active,
            'subscription' => $plus->current($customer),
            'price' => $plus->priceFor($customer),
            'periodDays' => $plus->periodDays(),
            'trust' => $trust,
            'access' => $access,
            'benefitList' => $benefits->customerBenefits($customer, $locale, $access),
            'nextGrade' => $benefits->nextGradeCopy($customer, $locale),
            'today' => $active ? $nba->forCustomer($customer) : null,
            'summary' => $summary,
            'offers' => $summary['offers_count'] ?? 0,
            'rewardBalance' => $summary['reward_balance'] ?? 0,
            'latestLesson' => $summary['latest_lesson'] ?? PlusLesson::query()
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->latest('published_at')
                ->first(),
        ]);
    }

    public function learn(Request $request, PlusService $plus, PlusLearningService $learning)
    {
        $customer = $request->user()->customer;
        abort_unless($plus->isActive($customer), 403);
        $plus->ensureSampleContent();
        $discover = $learning->discover(
            $customer,
            $request->string('q')->toString() ?: null,
            $request->string('category')->toString() ?: null,
        );
        $lessons = PlusLesson::query()
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->latest('published_at')
            ->get();

        return view('site.plus.learn', array_merge($discover, [
            'customer' => $customer,
            'lessons' => $lessons,
        ]));
    }

    public function subject(Request $request, PlusService $plus, PlusLearningService $learning, PlusSubject $subject)
    {
        $customer = $request->user()->customer;
        abort_unless($plus->isActive($customer), 403);
        abort_unless($subject->status === 'published', 404);
        $progress = $learning->markViewed($customer, $subject);

        return view('site.plus.subject', compact('customer', 'subject', 'progress'));
    }

    public function completeSubject(Request $request, PlusService $plus, PlusLearningService $learning, PlusSubject $subject)
    {
        $customer = $request->user()->customer;
        abort_unless($plus->isActive($customer), 403);
        $learning->markCompleted($customer, $subject);

        return back()->with('status', __('plus.learn.marked_done'));
    }

    public function saveSubject(Request $request, PlusService $plus, PlusLearningService $learning, PlusSubject $subject)
    {
        $customer = $request->user()->customer;
        abort_unless($plus->isActive($customer), 403);
        $learning->toggleSaved($customer, $subject);

        return back();
    }

    public function subjectAction(Request $request, PlusService $plus, PlusLearningService $learning, PlusSubject $subject)
    {
        $customer = $request->user()->customer;
        abort_unless($plus->isActive($customer), 403);
        $learning->markActionClicked($customer, $subject);
        $url = $subject->actionUrl() ?: route('site.borrower.plus.home');

        return redirect($url);
    }

    public function join(Request $request, PlusService $plus)
    {
        $customer = $request->user()->customer;
        if ($plus->isActive($customer)) {
            return redirect()->route('site.borrower.plus.home');
        }

        return redirect()->route('site.borrower.payments.show', $plus->startCheckout($customer));
    }

    public function renew(Request $request, PlusService $plus)
    {
        $customer = $request->user()->customer;
        abort_unless($plus->isActive($customer), 403);

        return redirect()->route('site.borrower.payments.show', $plus->startCheckout($customer));
    }

    public function welcome(Request $request, PlusService $plus)
    {
        $customer = $request->user()->customer;
        if (! $plus->isActive($customer)) {
            return redirect()->route('site.borrower.plus.home');
        }

        return view('site.plus.welcome', compact('customer'));
    }

    public function money(Request $request, PlusService $plus, PlusWorkspaceService $workspace)
    {
        $customer = $request->user()->customer;
        abort_unless($plus->isActive($customer), 403);

        return view('site.plus.money', array_merge(
            ['customer' => $customer],
            $workspace->moneyDashboard($customer),
        ));
    }

    public function saveMoney(Request $request, PlusService $plus)
    {
        $customer = $request->user()->customer;
        abort_unless($plus->isActive($customer), 403);

        if ($request->filled('in_amount') || $request->filled('out_amount')) {
            $data = $request->validate([
                'in_amount' => ['nullable', 'numeric', 'min:0'],
                'out_amount' => ['nullable', 'numeric', 'min:0'],
            ]);
            PlusMoneyEntry::query()->create([
                'customer_id' => $customer->id,
                'entry_date' => now()->toDateString(),
                'inflow' => (float) ($data['in_amount'] ?? 0),
                'outflow' => (float) ($data['out_amount'] ?? 0),
            ]);

            return back()->with('status', __('plus.saved'));
        }

        $data = $request->validate([
            'direction' => ['required', 'in:in,out'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'category' => ['required', 'string', 'max:40'],
        ]);
        PlusMoneyEntry::query()->create([
            'customer_id' => $customer->id,
            'entry_date' => now()->toDateString(),
            'inflow' => $data['direction'] === 'in' ? $data['amount'] : 0,
            'outflow' => $data['direction'] === 'out' ? $data['amount'] : 0,
            'category' => $data['category'],
        ]);

        return back()->with('status', __('plus.saved'));
    }

    public function business(Request $request, PlusService $plus, PlusWorkspaceService $workspace)
    {
        $customer = $request->user()->customer;
        abort_unless($plus->isActive($customer), 403);

        return view('site.plus.business', array_merge(
            ['customer' => $customer],
            $workspace->businessDashboard($customer),
        ));
    }

    public function saveBusiness(Request $request, PlusService $plus)
    {
        $customer = $request->user()->customer;
        abort_unless($plus->isActive($customer), 403);

        if ($request->filled('sold') || $request->filled('spent')) {
            $data = $request->validate([
                'sold' => ['nullable', 'numeric', 'min:0'],
                'spent' => ['nullable', 'numeric', 'min:0'],
            ]);
            if (($data['sold'] ?? 0) > 0 || ($data['spent'] ?? 0) > 0) {
                PlusBusinessEntry::query()->create([
                    'customer_id' => $customer->id,
                    'entry_date' => now()->toDateString(),
                    'sold' => (float) ($data['sold'] ?? 0),
                    'spent' => (float) ($data['spent'] ?? 0),
                ]);
            }

            return back()->with('status', __('plus.saved'));
        }

        $data = $request->validate([
            'kind' => ['required', 'in:sale,spend'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'category' => ['nullable', 'string', 'max:40'],
        ]);
        PlusBusinessEntry::query()->create([
            'customer_id' => $customer->id,
            'entry_date' => now()->toDateString(),
            'sold' => $data['kind'] === 'sale' ? $data['amount'] : 0,
            'spent' => $data['kind'] === 'spend' ? $data['amount'] : 0,
            'category' => $data['category'] ?? $data['kind'],
        ]);

        return back()->with('status', __('plus.saved'));
    }

    public function goals(Request $request, PlusService $plus, PlusWorkspaceService $workspace)
    {
        $customer = $request->user()->customer;
        abort_unless($plus->isActive($customer), 403);

        return view('site.plus.goals', array_merge(
            ['customer' => $customer],
            $workspace->goalsDashboard($customer),
        ));
    }

    public function saveGoal(Request $request, PlusService $plus)
    {
        $customer = $request->user()->customer;
        abort_unless($plus->isActive($customer), 403);
        $data = $request->validate([
            'kind' => ['required', 'in:business,school,home,vehicle,emergency,other,stock'],
            'title' => ['nullable', 'string', 'max:80'],
            'target_amount' => ['required', 'numeric', 'min:1'],
            'target_date' => ['nullable', 'date'],
        ]);
        $kinds = app(PlusWorkspaceService::class)->goalKinds();
        $locale = app()->getLocale() === 'sw' ? 'sw' : 'en';
        $title = filled($data['title'] ?? null)
            ? $data['title']
            : ($kinds[$data['kind']][$locale] ?? $data['kind']);
        PlusGoal::query()->create([
            'customer_id' => $customer->id,
            'kind' => $data['kind'],
            'title' => $title,
            'target_amount' => $data['target_amount'],
            'saved_amount' => 0,
            'target_date' => $data['target_date'] ?? null,
            'status' => 'active',
        ]);

        return back()->with('status', __('plus.saved'));
    }

    public function contributeGoal(Request $request, PlusService $plus, PlusGoal $goal)
    {
        $customer = $request->user()->customer;
        abort_unless($plus->isActive($customer), 403);
        abort_unless((int) $goal->customer_id === (int) $customer->id, 403);
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01']]);
        $saved = (float) $goal->saved_amount + (float) $data['amount'];
        $goal->update([
            'saved_amount' => $saved,
            'status' => $saved >= (float) $goal->target_amount ? 'completed' : ($goal->status ?: 'active'),
            'completed_at' => $saved >= (float) $goal->target_amount ? now() : $goal->completed_at,
        ]);

        return back()->with('status', __('plus.saved'));
    }

    public function pauseGoal(Request $request, PlusService $plus, PlusGoal $goal)
    {
        $customer = $request->user()->customer;
        abort_unless($plus->isActive($customer), 403);
        abort_unless((int) $goal->customer_id === (int) $customer->id, 403);
        $goal->update([
            'status' => $goal->isPaused() ? 'active' : 'paused',
        ]);

        return back();
    }

    public function reports(Request $request, PlusService $plus, PlusWorkspaceService $workspace, GradeBenefitService $benefits, MemberEngagementService $engagement)
    {
        $customer = $request->user()->customer;
        abort_unless($plus->isActive($customer), 403);
        $period = in_array($request->query('period'), ['week', 'month', 'year'], true)
            ? $request->query('period')
            : 'month';
        $locale = app()->getLocale() === 'sw' ? 'sw' : 'en';
        $trust = $benefits->trustLabel((int) ($engagement->trustScore($customer)['percent'] ?? 0), $locale);
        $report = $workspace->reportsDashboard($customer, $period);

        return view('site.plus.reports', [
            'customer' => $customer,
            'period' => $period,
            'report' => $report,
            'trust' => $trust,
            'grade' => $customer->grade ?: 'bronze',
            'print' => $request->boolean('print'),
        ]);
    }

    public function offers(Request $request, PlusService $plus)
    {
        $customer = $request->user()->customer;
        abort_unless($plus->isActive($customer), 403);
        $offers = $plus->eligibleOffers($customer);
        foreach ($offers as $offer) {
            $plus->recordOfferEvent($customer, $offer, 'viewed');
        }

        return view('site.plus.offers', [
            'customer' => $customer,
            'offers' => $offers,
            'claimed' => $offers->mapWithKeys(fn ($o) => [$o->id => $plus->hasClaimed($customer, $o)]),
        ]);
    }

    public function openOffer(Request $request, PlusService $plus, PlusOffer $offer)
    {
        $customer = $request->user()->customer;
        abort_unless($plus->isActive($customer), 403);
        $plus->recordOfferEvent($customer, $offer, 'opened');

        return back();
    }

    public function claimOffer(Request $request, PlusService $plus, PlusOffer $offer)
    {
        $customer = $request->user()->customer;
        abort_unless($plus->isActive($customer), 403);
        abort_unless($plus->eligibleOffers($customer)->contains('id', $offer->id), 403);
        $plus->recordOfferEvent($customer, $offer, 'claimed');

        return back()->with('status', __('plus.offers.claimed'));
    }

    public function rewards(Request $request, PlusService $plus, PlusWorkspaceService $workspace)
    {
        $customer = $request->user()->customer;
        abort_unless($plus->isActive($customer), 403);

        return view('site.plus.rewards', [
            'customer' => $customer,
            'balance' => $plus->rewardBalance($customer),
            'catalog' => $plus->rewardCatalog(),
            'ledger' => PlusRewardLedger::query()->where('customer_id', $customer->id)->latest('id')->limit(30)->get(),
            'earned' => $workspace->recentRewardEarns($customer),
        ]);
    }

    public function redeem(Request $request, PlusService $plus)
    {
        $customer = $request->user()->customer;
        abort_unless($plus->isActive($customer), 403);
        $catalog = collect($plus->rewardCatalog());
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:40'],
            'points' => ['nullable', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:120'],
        ]);
        if (filled($data['code'] ?? null)) {
            $item = $catalog->firstWhere('code', $data['code']);
            abort_unless($item, 422);
            $plus->redeemReward($customer, (int) $item['points'], $item['title']);
        } else {
            $plus->redeemReward($customer, (int) $data['points'], (string) $data['reason']);
        }

        return back()->with('status', __('plus.rewards.redeemed'));
    }

    public function lesson(Request $request, PlusService $plus, PlusLesson $lesson)
    {
        $customer = $request->user()->customer;
        abort_unless($plus->isActive($customer), 403);
        $locale = app()->getLocale() === 'sw' ? 'sw' : 'en';
        $progress = PlusLessonProgress::query()->firstOrCreate(
            ['customer_id' => $customer->id, 'plus_lesson_id' => $lesson->id],
            ['started_at' => now()]
        );

        return view('site.plus.lesson', [
            'customer' => $customer,
            'lesson' => $lesson,
            'progress' => $progress,
            'videoUrl' => $lesson->signedVideoUrl($locale),
        ]);
    }

    public function completeLesson(Request $request, PlusService $plus, PlusNotificationGate $gate, PlusLesson $lesson)
    {
        $customer = $request->user()->customer;
        abort_unless($plus->isActive($customer), 403);
        $progress = PlusLessonProgress::query()->firstOrCreate(
            ['customer_id' => $customer->id, 'plus_lesson_id' => $lesson->id],
            ['started_at' => now()]
        );
        $progress->update(['completed_at' => $progress->completed_at ?? now()]);
        $gate->notify($customer, 'plus_lesson_completed', [
            'lesson' => $lesson->title_en,
            '_fallback_body' => 'You finished this month’s Plus lesson.',
        ]);

        return back()->with('status', __('plus.learn.marked_done'));
    }

    public function lessonAction(Request $request, PlusService $plus, PlusLesson $lesson)
    {
        $customer = $request->user()->customer;
        abort_unless($plus->isActive($customer), 403);
        PlusLessonProgress::query()->firstOrCreate(
            ['customer_id' => $customer->id, 'plus_lesson_id' => $lesson->id],
            ['started_at' => now()]
        )->update(['action_done_at' => now()]);

        return redirect()->route('site.borrower.plus.money');
    }

    public function video(Request $request, PlusService $plus, PlusLesson $lesson)
    {
        $customer = $request->user()->customer;
        abort_unless($plus->isActive($customer), 403);
        $locale = $request->query('locale', 'en');
        $path = $locale === 'sw' ? $lesson->video_sw_path : $lesson->video_en_path;
        $path = $path ?: $lesson->video_en_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Content-Type' => 'video/mp4',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
