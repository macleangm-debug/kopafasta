<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\PlusBusinessEntry;
use App\Models\PlusGoal;
use App\Models\PlusGoalContribution;
use App\Models\PlusLesson;
use App\Models\PlusLessonProgress;
use App\Models\PlusMoneyEntry;
use App\Models\PlusOffer;
use App\Models\PlusSubject;
use App\Services\Grades\GradeBenefitService;
use App\Services\MemberEngagementService;
use App\Services\Plus\PlusLearningService;
use App\Services\Plus\PlusNextBestActionService;
use App\Services\Plus\PlusNotificationGate;
use App\Services\Plus\PlusReportService;
use App\Services\Plus\PlusService;
use App\Services\Plus\PlusWorkspaceService;
use App\Support\MoneyFormat;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
        $expired = $plus->isExpired($customer);
        $rawTrust = $engagement->trustScore($customer);
        $locale = app()->getLocale() === 'sw' ? 'sw' : 'en';
        $trust = $benefits->trustLabel((int) ($rawTrust['percent'] ?? 0), $locale);
        $access = $benefits->potentialAccess($customer);
        $summary = ($active || $expired) ? $workspace->homeSummary($customer) : null;

        return view('site.plus.home', [
            'customer' => $customer,
            'plusActive' => $active,
            'plusExpired' => $expired,
            'plusNeedsRenewal' => $plus->needsRenewal($customer),
            'plusDaysRemaining' => $plus->daysRemaining($customer),
            'subscription' => $plus->current($customer) ?? $plus->latest($customer),
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
            'loyaltyBalance' => app(\App\Services\LoyaltyPointsService::class)->balance($customer),
            'rewardsDash' => app(\App\Services\LoyaltyRedemptionService::class)->dashboard($customer),
        ]);
    }

    public function learn(Request $request, PlusService $plus, PlusLearningService $learning)
    {
        $customer = $this->requireActivePlus($request, $plus);
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
        $customer = $this->requireActivePlus($request, $plus);
        abort_unless($subject->status === 'published', 404);
        $progress = $learning->markViewed($customer, $subject);

        return view('site.plus.subject', compact('customer', 'subject', 'progress'));
    }

    public function completeSubject(Request $request, PlusService $plus, PlusLearningService $learning, PlusSubject $subject)
    {
        $customer = $this->requireActivePlus($request, $plus);
        $learning->markCompleted($customer, $subject);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['ok' => true]);
        }

        return back();
    }

    public function saveSubject(Request $request, PlusService $plus, PlusLearningService $learning, PlusSubject $subject)
    {
        $customer = $this->requireActivePlus($request, $plus);
        $learning->toggleSaved($customer, $subject);

        return back();
    }

    public function subjectAction(Request $request, PlusService $plus, PlusLearningService $learning, PlusSubject $subject)
    {
        $customer = $this->requireActivePlus($request, $plus);
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
        $customer = $this->requireActivePlus($request, $plus);

        return view('site.plus.money', array_merge(
            ['customer' => $customer],
            $workspace->moneyDashboard($customer),
        ));
    }

    public function saveMoney(Request $request, PlusService $plus)
    {
        $customer = $this->requireActivePlus($request, $plus);
        $this->mergeMoneyFields($request, ['in_amount', 'out_amount', 'amount']);

        $direction = $request->input('direction');
        if (! in_array($direction, ['in', 'out'], true)) {
            $direction = $request->filled('in_amount') && ! $request->filled('out_amount') ? 'in' : 'out';
        }

        $amount = $direction === 'in'
            ? (float) ($request->input('in_amount') ?: $request->input('amount') ?: 0)
            : (float) ($request->input('out_amount') ?: $request->input('amount') ?: 0);

        $request->merge([
            'direction' => $direction,
            'amount' => $amount,
        ]);

        $data = $request->validate([
            'direction' => ['required', 'in:in,out'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'category' => ['required', 'string', 'max:40'],
            'category_other' => [Rule::requiredIf(fn () => $request->input('category') === 'other'), 'nullable', 'string', 'max:80'],
        ]);

        $isOther = $data['category'] === 'other';
        PlusMoneyEntry::query()->create([
            'customer_id' => $customer->id,
            'entry_date' => now()->toDateString(),
            'inflow' => $data['direction'] === 'in' ? $data['amount'] : 0,
            'outflow' => $data['direction'] === 'out' ? $data['amount'] : 0,
            'category' => $isOther ? 'other' : $data['category'],
            'other_label' => $isOther ? trim((string) ($data['category_other'] ?? '')) : null,
        ]);

        app(\App\Services\GrowthPointsService::class)->awardMonthlyMoneyCheckIn($customer);

        return back()->with('status', __('plus.money.saved_here'));
    }

    public function business(Request $request, PlusService $plus, PlusWorkspaceService $workspace)
    {
        $customer = $this->requireActivePlus($request, $plus);
        $period = in_array($request->query('period'), ['today', 'week', 'month'], true)
            ? $request->query('period')
            : 'today';

        return view('site.plus.business', array_merge(
            ['customer' => $customer],
            $workspace->businessDashboard($customer, $period),
        ));
    }

    public function saveBusiness(Request $request, PlusService $plus)
    {
        $customer = $this->requireActivePlus($request, $plus);
        $this->mergeMoneyFields($request, ['sold', 'spent', 'amount']);

        $kind = $request->input('kind');
        if (! in_array($kind, ['sale', 'spend'], true)) {
            $kind = $request->filled('sold') ? 'sale' : 'spend';
        }
        $amount = $kind === 'sale'
            ? (float) ($request->input('sold') ?: $request->input('amount') ?: 0)
            : (float) ($request->input('spent') ?: $request->input('amount') ?: 0);
        $request->merge(['kind' => $kind, 'amount' => $amount]);

        $data = $request->validate([
            'kind' => ['required', 'in:sale,spend'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'category' => ['required', 'string', 'max:40'],
            'category_other' => [Rule::requiredIf(fn () => $request->input('category') === 'other'), 'nullable', 'string', 'max:80'],
            'note' => ['nullable', 'string', 'max:160'],
        ]);
        $isOther = $data['category'] === 'other';
        PlusBusinessEntry::query()->create([
            'customer_id' => $customer->id,
            'entry_date' => now()->toDateString(),
            'sold' => $data['kind'] === 'sale' ? $data['amount'] : 0,
            'spent' => $data['kind'] === 'spend' ? $data['amount'] : 0,
            'category' => $isOther ? 'other' : $data['category'],
            'other_label' => $isOther ? trim((string) ($data['category_other'] ?? '')) : null,
            'note' => $data['note'] ?? null,
        ]);

        return back()->with('status', __('plus.business.saved_here'));
    }

    public function goals(Request $request, PlusService $plus, PlusWorkspaceService $workspace)
    {
        $customer = $this->requireActivePlus($request, $plus);

        return view('site.plus.goals', array_merge(
            ['customer' => $customer],
            $workspace->goalsDashboard($customer),
        ));
    }

    public function saveGoal(Request $request, PlusService $plus)
    {
        $customer = $this->requireActivePlus($request, $plus);
        $this->mergeMoneyFields($request, ['target_amount']);
        $data = $request->validate([
            'kind' => ['required', 'in:business,school,home,vehicle,emergency,other,stock,savings'],
            'title' => [Rule::requiredIf(fn () => $request->input('kind') === 'other'), 'nullable', 'string', 'max:80'],
            'target_amount' => ['required', 'numeric', 'min:1'],
            'target_date' => ['required', 'date', 'after_or_equal:tomorrow'],
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
        $customer = $this->requireActivePlus($request, $plus);
        abort_unless((int) $goal->customer_id === (int) $customer->id, 403);
        $this->mergeMoneyFields($request, ['amount']);
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01']]);
        $saved = (float) $goal->saved_amount + (float) $data['amount'];
        $goal->update([
            'saved_amount' => $saved,
            'status' => $saved >= (float) $goal->target_amount ? 'completed' : ($goal->status ?: 'active'),
            'completed_at' => $saved >= (float) $goal->target_amount ? now() : $goal->completed_at,
        ]);
        PlusGoalContribution::query()->create([
            'plus_goal_id' => $goal->id,
            'amount' => $data['amount'],
        ]);

        return back()->with('status', __('plus.saved'));
    }

    public function pauseGoal(Request $request, PlusService $plus, PlusGoal $goal)
    {
        $customer = $this->requireActivePlus($request, $plus);
        abort_unless((int) $goal->customer_id === (int) $customer->id, 403);
        $goal->update([
            'status' => $goal->isPaused() ? 'active' : 'paused',
        ]);

        return back();
    }

    public function completeGoal(Request $request, PlusService $plus, PlusGoal $goal)
    {
        $customer = $this->requireActivePlus($request, $plus);
        abort_unless((int) $goal->customer_id === (int) $customer->id, 403);
        abort_unless($goal->remaining() <= 0, 403, __('plus.goals.complete_only_when_funded'));
        $already = $goal->completed_at !== null;
        $goal->update([
            'status' => 'completed',
            'completed_at' => $goal->completed_at ?? now(),
        ]);
        if (! $already) {
            app(\App\Services\GrowthPointsService::class)->awardOwnerAction(
                $customer,
                'plus_goal',
                null,
                PlusGoal::class,
                (int) $goal->id,
            );
        }

        return back()->with('status', __('plus.goals.completed'));
    }

    public function updateGoal(Request $request, PlusService $plus, PlusGoal $goal)
    {
        $customer = $this->requireActivePlus($request, $plus);
        abort_unless((int) $goal->customer_id === (int) $customer->id, 403);
        $this->mergeMoneyFields($request, ['target_amount']);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:80'],
            'target_amount' => ['required', 'numeric', 'min:1'],
            'target_date' => ['required', 'date', 'after_or_equal:tomorrow'],
        ]);
        $goal->update($data);

        return back()->with('status', __('plus.saved'));
    }

    public function reports(Request $request, PlusService $plus, PlusReportService $reports)
    {
        $customer = $this->requireActivePlus($request, $plus);
        $report = $reports->monthDashboard($customer, $request->query('month'));

        return view('site.plus.reports', [
            'customer' => $customer,
            'report' => $report,
            'print' => $request->boolean('print'),
        ]);
    }

    public function offers(Request $request, PlusService $plus)
    {
        $customer = $this->requireActivePlus($request, $plus);
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
        $customer = $this->requireActivePlus($request, $plus);
        $plus->recordOfferEvent($customer, $offer, 'opened');

        return back();
    }

    public function claimOffer(Request $request, PlusService $plus, PlusOffer $offer)
    {
        $customer = $this->requireActivePlus($request, $plus);
        abort_unless($plus->eligibleOffers($customer)->contains('id', $offer->id), 403);
        $plus->recordOfferEvent($customer, $offer, 'claimed');

        return back()->with('status', __('plus.offers.claimed'));
    }

    public function rewards(Request $request, PlusService $plus)
    {
        $customer = $this->requireActivePlus($request, $plus);
        $redemptions = app(\App\Services\LoyaltyRedemptionService::class);
        $points = app(\App\Services\LoyaltyPointsService::class);

        return view('site.plus.rewards', [
            'customer' => $customer,
            'catalog' => $redemptions->catalog(null, $customer),
            'rewardsDashboard' => $redemptions->dashboard($customer),
            'activeRewards' => $redemptions->activeRewards($customer),
            'transactions' => $points->recentTransactions($customer, 15),
        ]);
    }

    public function redeem(Request $request, PlusService $plus)
    {
        $customer = $this->requireActivePlus($request, $plus);
        $data = $request->validate([
            'option_key' => ['required', 'string', 'max:60'],
        ]);

        try {
            app(\App\Services\LoyaltyRedemptionService::class)->redeem($customer, $data['option_key']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        \App\Support\Celebration::flashOne('reward_redeemed');

        return redirect()->route('site.borrower.plus.rewards')
            ->with('status', __('borrower.rewards.redeemed'));
    }

    public function lesson(Request $request, PlusService $plus, PlusLesson $lesson)
    {
        $customer = $this->requireActivePlus($request, $plus);
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
        $customer = $this->requireActivePlus($request, $plus);
        $progress = PlusLessonProgress::query()->firstOrCreate(
            ['customer_id' => $customer->id, 'plus_lesson_id' => $lesson->id],
            ['started_at' => now()]
        );
        $already = $progress->completed_at !== null;
        $progress->update(['completed_at' => $progress->completed_at ?? now()]);
        if (! $already) {
            app(\App\Services\GrowthPointsService::class)->awardOwnerAction(
                $customer,
                'plus_learn',
                null,
                PlusLesson::class,
                (int) $lesson->id,
            );
        }
        if (! $request->wantsJson() && ! $request->ajax()) {
            $gate->notify($customer, 'plus_lesson_completed', [
                'lesson' => $lesson->title_en,
                '_fallback_body' => 'You finished this month’s Plus lesson.',
            ]);
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['ok' => true]);
        }

        return back();
    }

    public function lessonAction(Request $request, PlusService $plus, PlusLesson $lesson)
    {
        $customer = $this->requireActivePlus($request, $plus);
        PlusLessonProgress::query()->firstOrCreate(
            ['customer_id' => $customer->id, 'plus_lesson_id' => $lesson->id],
            ['started_at' => now()]
        )->update(['action_done_at' => now()]);

        return redirect()->route('site.borrower.plus.money');
    }

    public function video(Request $request, PlusService $plus, PlusLesson $lesson)
    {
        $customer = $this->requireActivePlus($request, $plus);
        $locale = $request->query('locale', 'en');
        $path = $locale === 'sw' ? $lesson->video_sw_path : $lesson->video_en_path;
        $path = $path ?: $lesson->video_en_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Content-Type' => 'video/mp4',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Money diary, Learn, Reports, Offers and Rewards stay closed until Plus is paid.
     */
    private function requireActivePlus(Request $request, PlusService $plus): \App\Models\Customer
    {
        $customer = $request->user()->customer;
        if ($plus->isActive($customer)) {
            return $customer;
        }

        throw new HttpResponseException(
            redirect()
                ->route('site.borrower.plus.home')
                ->with('status', __('plus.home.locked_body'))
        );
    }

    /** @param list<string> $keys */
    private function mergeMoneyFields(Request $request, array $keys): void
    {
        $merge = [];
        foreach ($keys as $key) {
            if ($request->filled($key)) {
                $merge[$key] = MoneyFormat::toNumber($request->input($key));
            }
        }
        if ($merge !== []) {
            $request->merge($merge);
        }
    }
}
