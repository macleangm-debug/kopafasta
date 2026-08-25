<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\PlusBusinessEntry;
use App\Models\PlusGoal;
use App\Models\PlusLesson;
use App\Models\PlusLessonProgress;
use App\Models\PlusMoneyEntry;
use App\Models\PlusRewardLedger;
use Illuminate\Support\Facades\Storage;
use App\Services\Grades\GradeBenefitService;
use App\Services\LoanQualificationService;
use App\Services\MemberEngagementService;
use App\Services\Plus\PlusService;
use Illuminate\Http\Request;

class PlusController extends Controller
{
    public function home(Request $request, PlusService $plus, GradeBenefitService $benefits, MemberEngagementService $engagement)
    {
        $customer = $request->user()->customer;
        $plus->ensureSampleContent();
        $active = $plus->isActive($customer);
        $trust = $engagement->trustScore($customer);
        $locale = app()->getLocale() === 'sw' ? 'sw' : 'en';
        $qualification = app(LoanQualificationService::class)->calculate($customer);
        $access = (float) ($qualification['amount'] ?? 0);
        if ($access <= 0) {
            $access = $benefits->potentialAccess($customer);
        }

        return view('site.plus.home', [
            'customer' => $customer,
            'plusActive' => $active,
            'subscription' => $plus->current($customer),
            'price' => $plus->priceFor($customer),
            'periodDays' => $plus->periodDays(),
            'trust' => $benefits->trustLabel((int) ($trust['percent'] ?? 0), $locale),
            'access' => $access,
            'benefitList' => $benefits->customerBenefits($customer, $locale, $access),
            'nextGrade' => $benefits->nextGradeCopy($customer, $locale),
            'offers' => $active ? $plus->eligibleOffers($customer) : collect(),
            'rewardBalance' => $active ? $plus->rewardBalance($customer) : 0,
            'latestLesson' => PlusLesson::query()
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->latest('published_at')
                ->first(),
        ]);
    }

    public function learn(Request $request, PlusService $plus)
    {
        $customer = $request->user()->customer;
        abort_unless($plus->isActive($customer), 403);
        $plus->ensureSampleContent();
        $lessons = PlusLesson::query()
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->latest('published_at')
            ->get();

        return view('site.plus.learn', compact('customer', 'lessons'));
    }

    public function join(Request $request, PlusService $plus)
    {
        $customer = $request->user()->customer;
        if ($plus->isActive($customer)) {
            return redirect()->route('site.borrower.plus.home');
        }

        $payment = $plus->startCheckout($customer);

        return redirect()->route('site.borrower.payments.show', $payment);
    }

    public function renew(Request $request, PlusService $plus)
    {
        $customer = $request->user()->customer;
        abort_unless($plus->isActive($customer), 403);
        $payment = $plus->startCheckout($customer);

        return redirect()->route('site.borrower.payments.show', $payment);
    }

    public function welcome(Request $request, PlusService $plus)
    {
        $customer = $request->user()->customer;
        if (! $plus->isActive($customer)) {
            return redirect()->route('site.borrower.plus.home');
        }

        return view('site.plus.welcome', compact('customer'));
    }

    public function money(Request $request, PlusService $plus)
    {
        $customer = $request->user()->customer;
        abort_unless($plus->isActive($customer), 403);
        $entries = PlusMoneyEntry::query()->where('customer_id', $customer->id)->latest('entry_date')->limit(14)->get();

        return view('site.plus.money', compact('customer', 'entries'));
    }

    public function saveMoney(Request $request, PlusService $plus)
    {
        $customer = $request->user()->customer;
        abort_unless($plus->isActive($customer), 403);
        $data = $request->validate([
            'in_amount' => ['required', 'numeric', 'min:0'],
            'out_amount' => ['required', 'numeric', 'min:0'],
        ]);
        PlusMoneyEntry::query()->create([
            'customer_id' => $customer->id,
            'entry_date' => now()->toDateString(),
            'inflow' => $data['in_amount'],
            'outflow' => $data['out_amount'],
        ]);

        return back()->with('status', 'Saved.');
    }

    public function business(Request $request, PlusService $plus)
    {
        $customer = $request->user()->customer;
        abort_unless($plus->isActive($customer), 403);
        $entries = PlusBusinessEntry::query()->where('customer_id', $customer->id)->latest('entry_date')->limit(31)->get();
        $sold = (float) $entries->sum('sold');
        $spent = (float) $entries->sum('spent');

        return view('site.plus.business', compact('customer', 'entries', 'sold', 'spent'));
    }

    public function saveBusiness(Request $request, PlusService $plus)
    {
        $customer = $request->user()->customer;
        abort_unless($plus->isActive($customer), 403);
        $data = $request->validate([
            'sold' => ['required', 'numeric', 'min:0'],
            'spent' => ['required', 'numeric', 'min:0'],
        ]);
        PlusBusinessEntry::query()->create([
            'customer_id' => $customer->id,
            'entry_date' => now()->toDateString(),
            'sold' => $data['sold'],
            'spent' => $data['spent'],
        ]);

        return back()->with('status', 'Saved.');
    }

    public function goals(Request $request, PlusService $plus)
    {
        $customer = $request->user()->customer;
        abort_unless($plus->isActive($customer), 403);
        $goals = PlusGoal::query()->where('customer_id', $customer->id)->latest('id')->get();

        return view('site.plus.goals', compact('customer', 'goals'));
    }

    public function saveGoal(Request $request, PlusService $plus)
    {
        $customer = $request->user()->customer;
        abort_unless($plus->isActive($customer), 403);
        $data = $request->validate([
            'kind' => ['required', 'in:emergency,stock,school,other'],
            'title' => ['required', 'string', 'max:80'],
            'target_amount' => ['required', 'numeric', 'min:1'],
        ]);
        PlusGoal::query()->create([
            'customer_id' => $customer->id,
            'kind' => $data['kind'],
            'title' => $data['title'],
            'target_amount' => $data['target_amount'],
            'saved_amount' => 0,
        ]);

        return back()->with('status', 'Goal created.');
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
            'completed_at' => $saved >= (float) $goal->target_amount ? now() : $goal->completed_at,
        ]);

        return back()->with('status', 'Progress saved.');
    }

    public function reports(Request $request, PlusService $plus, GradeBenefitService $benefits, MemberEngagementService $engagement)
    {
        $customer = $request->user()->customer;
        abort_unless($plus->isActive($customer), 403);
        $money = PlusMoneyEntry::query()->where('customer_id', $customer->id)->latest('entry_date')->limit(31)->get();
        $business = PlusBusinessEntry::query()->where('customer_id', $customer->id)->latest('entry_date')->limit(31)->get();
        $goals = PlusGoal::query()->where('customer_id', $customer->id)->get();
        $locale = app()->getLocale() === 'sw' ? 'sw' : 'en';
        $trust = $engagement->trustScore($customer);

        return view('site.plus.reports', [
            'customer' => $customer,
            'moneyIn' => (float) $money->sum('inflow'),
            'moneyOut' => (float) $money->sum('outflow'),
            'sold' => (float) $business->sum('sold'),
            'spent' => (float) $business->sum('spent'),
            'goals' => $goals,
            'trust' => $benefits->trustLabel((int) ($trust['percent'] ?? 0), $locale),
            'grade' => $customer->grade ?: 'bronze',
        ]);
    }

    public function offers(Request $request, PlusService $plus)
    {
        $customer = $request->user()->customer;
        abort_unless($plus->isActive($customer), 403);

        return view('site.plus.offers', [
            'customer' => $customer,
            'offers' => $plus->eligibleOffers($customer),
        ]);
    }

    public function rewards(Request $request, PlusService $plus)
    {
        $customer = $request->user()->customer;
        abort_unless($plus->isActive($customer), 403);
        $ledger = PlusRewardLedger::query()->where('customer_id', $customer->id)->latest('id')->limit(30)->get();

        return view('site.plus.rewards', [
            'customer' => $customer,
            'balance' => $plus->rewardBalance($customer),
            'ledger' => $ledger,
        ]);
    }

    public function redeem(Request $request, PlusService $plus)
    {
        $customer = $request->user()->customer;
        abort_unless($plus->isActive($customer), 403);
        $data = $request->validate([
            'points' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:120'],
        ]);
        $plus->redeemReward($customer, (int) $data['points'], $data['reason']);

        return back()->with('status', 'Points redeemed.');
    }

    public function lesson(Request $request, PlusService $plus, PlusLesson $lesson)
    {
        $customer = $request->user()->customer;
        abort_unless($plus->isActive($customer), 403);
        $locale = app()->getLocale() === 'sw' ? 'sw' : 'en';
        PlusLessonProgress::query()->firstOrCreate(
            ['customer_id' => $customer->id, 'plus_lesson_id' => $lesson->id],
            ['started_at' => now()]
        );

        return view('site.plus.lesson', [
            'customer' => $customer,
            'lesson' => $lesson,
            'videoUrl' => $lesson->signedVideoUrl($locale),
        ]);
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
