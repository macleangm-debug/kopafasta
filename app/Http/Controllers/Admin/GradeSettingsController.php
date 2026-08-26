<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Grades\CustomerGradeEngine;
use App\Services\Grades\GradeSettings;
use App\Services\Plus\PlusService;
use App\Support\MoneyFormat;
use Illuminate\Http\Request;

class GradeSettingsController extends Controller
{
    public function grades(GradeSettings $settings, CustomerGradeEngine $engine)
    {
        return view('admin.settings.grades', [
            'rules' => $settings->rules(),
            'version' => $settings->currentVersion()?->version,
            'backtest' => $engine->backtest(),
        ]);
    }

    public function saveGrades(Request $request, GradeSettings $settings)
    {
        $rules = $settings->rules();
        $rules['weights']['repayment'] = (int) $request->input('weight_repayment', 35);
        $rules['weights']['handled_credit'] = (int) $request->input('weight_handled_credit', 20);
        $rules['weights']['relationship'] = (int) $request->input('weight_relationship', 15);
        $rules['weights']['current_position'] = (int) $request->input('weight_current_position', 15);
        $rules['weights']['stability'] = (int) $request->input('weight_stability', 10);
        $rules['weights']['verification'] = (int) $request->input('weight_verification', 5);
        $rules['grace_days']['silver'] = (int) $request->input('grace_silver', 14);
        $rules['grace_days']['gold'] = (int) $request->input('grace_gold', 30);
        $rules['grace_days']['platinum'] = (int) $request->input('grace_platinum', 45);
        $rules['integrity']['min_qualifying_principal'] = (float) $request->input('min_qualifying_principal', 100000);
        $rules['country_bands']['TZ']['potential_access']['bronze'] = (float) $request->input('tz_bronze_access', 500000);
        $rules['country_bands']['TZ']['potential_access']['silver'] = (float) $request->input('tz_silver_access', 1500000);
        $rules['country_bands']['TZ']['potential_access']['gold'] = (float) $request->input('tz_gold_access', 5000000);
        $rules['country_bands']['TZ']['potential_access']['platinum'] = (float) $request->input('tz_platinum_access', 15000000);
        foreach (['bronze', 'silver', 'gold', 'platinum'] as $grade) {
            $rules['benefits'][$grade]['repeat_journey'] = (string) $request->input("benefit_{$grade}_repeat", $rules['benefits'][$grade]['repeat_journey'] ?? 'full');
            $rules['benefits'][$grade]['priority'] = (string) $request->input("benefit_{$grade}_priority", $rules['benefits'][$grade]['priority'] ?? 'standard');
            $rules['benefits'][$grade]['offer_tier'] = (string) $request->input("benefit_{$grade}_offer_tier", $rules['benefits'][$grade]['offer_tier'] ?? 'standard');
            $rules['benefits'][$grade]['rewards'] = (string) $request->input("benefit_{$grade}_rewards", $rules['benefits'][$grade]['rewards'] ?? '');
            $rules['benefits'][$grade]['exclusive'] = (string) $request->input("benefit_{$grade}_exclusive", $rules['benefits'][$grade]['exclusive'] ?? '');
            $rules['benefits'][$grade]['max_tenure_months'] = (int) $request->input("benefit_{$grade}_max_tenure", $rules['benefits'][$grade]['max_tenure_months'] ?? 12);
        }

        $version = $settings->save($rules, $request->user()?->id);

        return back()->with('status', 'Grade rules saved as version '.$version->version.'.');
    }

    public function plus(PlusService $plus)
    {
        $plus->ensureSampleContent();
        app(\App\Services\Plus\PlusLearningService::class)->ensureCatalog();

        return view('admin.settings.plus', [
            'config' => $plus->config(),
            'billingCycle' => $plus->billingCycle(),
            'notifications' => \App\Models\Setting::get('kopafasta_plus.notifications') ?: [],
            'triggers' => [
                'money_daily_reminder' => 'Money — daily reminder (stops after today’s entry)',
                'business_no_activity' => 'Business — no activity today (stops after a sale/spend)',
                'goal_near_target' => 'Goals — near target (stops when completed)',
                'goal_completed' => 'Goals — completed',
                'plus_monthly_report_ready' => 'Reports — monthly progress ready (stops when viewed)',
                'plus_lesson_unwatched' => 'Learning — lesson reminder (stops when finished)',
                'learning_continue' => 'Learning — continue started subject',
                'new_eligible_offer' => 'Offers — new eligible offer',
                'reward_available' => 'Rewards — points ready to use',
                'plus_started' => 'Plus — started (always on)',
                'plus_expiring' => 'Plus — expiring',
            ],
        ]);
    }

    public function savePlus(Request $request)
    {
        $data = $request->validate([
            'tz_price' => ['required'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
            'reports_generation_day' => ['nullable', 'integer', 'min:1', 'max:5'],
            'reports_enabled' => ['nullable', 'boolean'],
            'reports_insights' => ['nullable', 'boolean'],
        ]);
        $config = app(PlusService::class)->config();
        $cycle = $data['billing_cycle'];
        $config['plans']['monthly']['billing_cycle'] = $cycle;
        $config['plans']['monthly']['period_days'] = $cycle === 'monthly' ? 30 : 365;
        $config['plans']['monthly']['prices']['TZ']['amount'] = (float) MoneyFormat::toNumber($data['tz_price']);
        $config['reports'] = [
            'enabled' => $request->boolean('reports_enabled', true),
            'generation_day' => (int) ($data['reports_generation_day'] ?? 1),
            'insights' => $request->boolean('reports_insights', true),
        ];
        \App\Models\Setting::set('kopafasta_plus.config', $config);

        return back()->with('status', 'Kopafasta Plus settings saved.');
    }

    public function watch()
    {
        $queue = \App\Models\Customer::query()
            ->whereIn('grade_integrity', ['watch', 'review', 'restricted'])
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get();

        $benefits = app(\App\Services\Grades\GradeBenefitService::class);
        $queue->each(function ($customer) use ($benefits) {
            $customer->setAttribute('watch_copy', $benefits->staffIntegrityCopy($customer));
        });

        return view('admin.customers.grade-watch', ['queue' => $queue]);
    }

    public function saveWatch(Request $request, \App\Models\Customer $customer, CustomerGradeEngine $engine)
    {
        $data = $request->validate([
            'action' => ['required', 'in:clear,keep_review,restrict,escalate'],
            'reason' => ['required', 'string', 'min:8', 'max:500'],
        ]);
        $engine->applyWatchAction($customer, $data['action'], $data['reason'], $request->user()?->id);

        return back()->with('status', 'Watch action recorded.');
    }

    public function saveOverride(Request $request, \App\Models\Customer $customer, CustomerGradeEngine $engine)
    {
        abort_unless(
            $request->user()?->hasPermission('grades.override')
            || $request->user()?->hasPermission('settings.manage'),
            403
        );
        $data = $request->validate([
            'grade' => ['required', 'in:bronze,silver,gold,platinum'],
            'reason' => ['required', 'string', 'min:8', 'max:500'],
            'expires_at' => ['required', 'date', 'after:now'],
        ]);
        $engine->staffOverride($customer, $data['grade'], $data['reason'], $data['expires_at'], $request->user()?->id);

        return back()->with('status', 'Override saved. It expires automatically.');
    }

    public function saveLesson(Request $request)
    {
        return redirect()
            ->route('admin.content.plus-learning')
            ->with('status', 'Plus Learning is managed from Content.');
    }

    public function saveOffer(Request $request)
    {
        return redirect()
            ->route('admin.growth.offers.index')
            ->with('status', 'Offers are managed from Growth.');
    }

    public function saveCategory(Request $request)
    {
        return redirect()
            ->route('admin.content.plus-learning')
            ->with('status', 'Plus Learning is managed from Content.');
    }

    public function saveSubject(Request $request)
    {
        return redirect()
            ->route('admin.content.plus-learning')
            ->with('status', 'Plus Learning is managed from Content.');
    }

    public function savePlusNotifications(Request $request)
    {
        $codes = array_keys($request->input('triggers', []));
        $stored = [];
        foreach ($request->input('known', []) as $code) {
            $stored[$code] = [
                'active' => in_array($code, $codes, true),
            ];
        }
        \App\Models\Setting::set('kopafasta_plus.notifications', $stored);

        return back()->with('status', 'Plus notification triggers saved. Templates and channels stay in Transactional messaging.');
    }
}
