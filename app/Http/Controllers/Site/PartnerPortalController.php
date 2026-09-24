<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Services\PartnerActivationService;
use App\Support\Celebration;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PartnerPortalController extends Controller
{
    public function start(Request $request): View
    {
        $partner = null;
        $code = strtoupper(trim((string) $request->query('partner_code', '')));
        if ($code !== '') {
            $partner = Vendor::query()
                ->whereRaw('UPPER(partner_number) = ?', [$code])
                ->first();
        }

        $maskedPhone = null;
        if ($partner) {
            $digits = PhoneNumber::digits((string) $partner->phone);
            $maskedPhone = strlen($digits) >= 4 ? substr($digits, -4) : null;
        }

        return view('site.partner.start', [
            'partner' => $partner,
            'maskedPhone' => $maskedPhone,
        ]);
    }

    public function lookup(Request $request, PartnerActivationService $activation): RedirectResponse|View
    {
        app(\App\Services\TurnstileService::class)->assertHuman($request);

        $data = $request->validate([
            'partner_code' => ['required', 'string', 'max:50'],
            'phone'        => ['required', 'string', 'max:30'],
            'phone_local'  => ['nullable', 'string', 'max:20'],
        ]);

        $code = strtoupper(trim($data['partner_code']));
        $phone = PhoneNumber::fromRequest($request, 'phone')
            ?? PhoneNumber::normalizeForCountry($data['phone'], null)
            ?? trim($data['phone']);

        $vendor = Vendor::query()
            ->whereRaw('UPPER(partner_number) = ?', [$code])
            ->where(function ($q) use ($phone) {
                PhoneNumber::constrain($q, 'phone', $phone);
            })
            ->first();

        if (! $vendor) {
            return back()->withInput()->withErrors([
                'phone' => __('site.auth.partner_phone_mismatch'),
            ]);
        }

        if ($vendor->activated_at && $vendor->user_id) {
            return redirect()->route('site.login', ['portal' => 'partner'])
                ->with('status', __('site.auth.partner_already_active'));
        }

        $user = $activation->activateWithPartnerCode($vendor, $phone);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->route('site.partner.setup-pin')
            ->with('status', __('site.auth.partner_activated'));
    }

    public function showSetupPin(\App\Services\PinRecoveryChallengeService $recovery): View|RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->role === 'vendor', 403);

        $needsPin = ! app(\App\Services\PinService::class)->hasPin($user);
        $needsRecovery = ! $recovery->hasEnrolledAnswers($user);

        if (! $needsPin && ! $needsRecovery) {
            return redirect()->route('site.partner.dashboard');
        }

        $keys = session('pin_setup_question_keys');
        $needed = $recovery->requiredQuestionCount($user);
        if (! is_array($keys) || count($keys) < $needed) {
            $keys = $recovery->pickRandomKeys($needed);
            session(['pin_setup_question_keys' => $keys]);
        }

        $partner = Vendor::query()->where('user_id', $user->id)->first();

        return view('site.auth.setup-pin', [
            'needsPin' => $needsPin,
            'phase' => $needsPin ? 'pin' : 'questions',
            'questions' => $recovery->questionsForKeys($keys),
            'secureAccountPost' => route('site.partner.setup-pin.post'),
            'secureAccountSwap' => route('site.partner.setup-pin.swap'),
            'partnerContext' => $partner,
        ]);
    }

    public function storeSetupPin(Request $request, \App\Services\PinRecoveryChallengeService $recovery): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->role === 'vendor', 403);

        $needsPin = ! app(\App\Services\PinService::class)->hasPin($user);
        $needsRecovery = ! $recovery->hasEnrolledAnswers($user);

        if (! $needsPin && ! $needsRecovery) {
            $request->session()->forget('pin_setup_question_keys');

            return redirect()->route('site.partner.dashboard')
                ->with(Celebration::SESSION_KEY, ['registration']);
        }

        if ($needsPin) {
            $data = $request->validate([
                'pin' => ['required', 'string', new \App\Rules\FourDigitPin, 'confirmed'],
            ]);
            app(\App\Services\PinService::class)->setPin($user, $data['pin']);

            if (! $needsRecovery) {
                $request->session()->forget('pin_setup_question_keys');

                return redirect()->route('site.partner.dashboard')
                    ->with(Celebration::SESSION_KEY, ['registration']);
            }

            return redirect()->route('site.partner.setup-pin');
        }

        if ((string) $request->input('phase') === 'pin') {
            return redirect()->route('site.partner.setup-pin');
        }

        $needed = $recovery->requiredQuestionCount($user);
        $keys = session('pin_setup_question_keys', []);
        if (! is_array($keys) || count($keys) < $needed) {
            return redirect()->route('site.partner.setup-pin')
                ->withErrors(['answers' => __('site.auth.pin_recovery.expired')]);
        }

        $rules = [
            'answers' => ['required', 'array'],
        ];
        foreach ($keys as $key) {
            $rules["answers.$key"] = ['required', 'string', 'max:120'];
        }

        $data = $request->validate($rules);
        $recovery->enroll($user, collect($keys)->mapWithKeys(
            fn ($key) => [$key => (string) ($data['answers'][$key] ?? '')]
        )->all());
        $request->session()->forget('pin_setup_question_keys');

        return redirect()
            ->route('site.partner.dashboard')
            ->with('status', __('site.auth.pin_recovery.setup_done'))
            ->with(Celebration::SESSION_KEY, ['registration']);
    }

    public function swapSetupPin(Request $request, \App\Services\PinRecoveryChallengeService $recovery): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->role === 'vendor', 403);

        $data = $request->validate([
            'index' => ['required', 'integer', 'min:0', 'max:9'],
        ]);

        $keys = session('pin_setup_question_keys', []);
        if (! is_array($keys) || $keys === []) {
            return redirect()->route('site.partner.setup-pin');
        }

        session([
            'pin_setup_question_keys' => $recovery->swapQuestionKey($keys, (int) $data['index']),
        ]);

        return redirect()->route('site.partner.setup-pin');
    }

    public function showSetupRecovery(\App\Services\PinRecoveryChallengeService $recovery): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->role === 'vendor', 403);

        if ($recovery->hasEnrolledAnswers($user)) {
            return redirect()->route('site.partner.dashboard');
        }

        return redirect()->route('site.partner.setup-pin');
    }

    public function storeSetupRecovery(Request $request, \App\Services\PinRecoveryChallengeService $recovery): RedirectResponse
    {
        return $this->storeSetupPin($request, $recovery);
    }

    public function showForgotPin(Request $request): View
    {
        $step = (int) $request->query('step', old('step', 1));
        $questions = $request->session()->get('partner_pin_recovery_questions', []);
        $verified = (bool) $request->session()->get('partner_pin_recovery_answers_ok', false);
        if ($step === 3 && (! $verified || blank($request->session()->get('partner_pin_recovery_token')))) {
            $step = 1;
        }
        if ($step === 2 && $questions === []) {
            $step = 1;
        }

        return view('site.partner.forgot-pin', [
            'step' => $step,
            'questions' => $questions,
            'requiredCorrect' => (int) $request->session()->get('partner_pin_recovery_required', 2),
            'prefillCode' => old('partner_code', $request->session()->get('partner_pin_recovery_code', $request->query('partner_code'))),
            'prefillPhone' => old('phone', $request->session()->get('partner_pin_recovery_phone', $request->query('phone'))),
            'challengeToken' => $request->session()->get('partner_pin_recovery_token'),
            'expiresAt' => (int) $request->session()->get('partner_pin_recovery_expires_at', 0),
        ]);
    }

    public function startForgotPin(Request $request, \App\Services\PinRecoveryChallengeService $recovery): RedirectResponse
    {
        app(\App\Services\TurnstileService::class)->assertHuman($request);

        $data = $request->validate([
            'partner_code' => ['required', 'string', 'max:50'],
            'phone' => ['required', 'string', 'max:30'],
            'phone_local' => ['nullable', 'string', 'max:20'],
        ]);

        $code = strtoupper(trim($data['partner_code']));
        $phone = PhoneNumber::fromRequest($request, 'phone')
            ?? PhoneNumber::normalizeForCountry($data['phone'], null)
            ?? trim($data['phone']);

        $this->forgetPartnerRecovery($request);

        $vendor = Vendor::query()
            ->whereRaw('UPPER(partner_number) = ?', [$code])
            ->where(function ($q) use ($phone) {
                PhoneNumber::constrain($q, 'phone', $phone);
            })
            ->first();

        $user = $vendor?->user_id ? \App\Models\User::query()->find($vendor->user_id) : null;
        if (! $vendor || ! $user || $user->role !== 'vendor') {
            return back()
                ->withInput(['partner_code' => $code, 'phone' => $phone])
                ->with('feedback', [
                    'tone' => 'error',
                    'title' => __('site.auth.partner_forgot_title'),
                    'message' => __('site.auth.partner_forgot_not_found'),
                ]);
        }

        if (! app(\App\Services\PinService::class)->hasPin($user)) {
            return back()
                ->withInput(['partner_code' => $code, 'phone' => $phone])
                ->with('feedback', [
                    'tone' => 'warning',
                    'title' => __('site.auth.partner_forgot_title'),
                    'message' => __('site.auth.pin_recovery.no_pin_yet'),
                ]);
        }

        $started = $recovery->startForUser($user);
        if (! $started) {
            return back()
                ->withInput(['partner_code' => $code, 'phone' => $phone])
                ->with('feedback', [
                    'tone' => 'warning',
                    'title' => __('site.auth.partner_forgot_title'),
                    'message' => __('site.auth.pin_recovery.not_enrolled'),
                ]);
        }

        $request->session()->put([
            'partner_pin_recovery_token' => $started['token'],
            'partner_pin_recovery_questions' => $started['questions'],
            'partner_pin_recovery_required' => $started['required_correct'],
            'partner_pin_recovery_code' => $code,
            'partner_pin_recovery_phone' => $phone,
            'partner_pin_recovery_answers_ok' => false,
            'partner_pin_recovery_expires_at' => (int) ($started['expires_at'] ?? now()->addSeconds($recovery->sessionTtlSeconds())->getTimestamp()),
        ]);

        return redirect()
            ->route('site.partner.forgot-pin', ['step' => 2])
            ->withInput(['partner_code' => $code, 'phone' => $phone, 'step' => 2]);
    }

    public function verifyForgotPin(Request $request, \App\Services\PinRecoveryChallengeService $recovery): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'answers' => ['required', 'array'],
            'answers.*' => ['nullable', 'string', 'max:120'],
        ]);

        $token = (string) $data['token'];
        $sessionToken = (string) $request->session()->get('partner_pin_recovery_token', '');
        if ($sessionToken === '' || ! hash_equals($sessionToken, $token)) {
            return redirect()
                ->route('site.partner.forgot-pin')
                ->with('feedback', [
                    'tone' => 'error',
                    'title' => __('site.auth.partner_forgot_title'),
                    'message' => __('site.auth.pin_recovery.expired'),
                ]);
        }

        $result = $recovery->verify($token, $data['answers']);
        if (! ($result['ok'] ?? false)) {
            $reason = (string) ($result['reason'] ?? 'mismatch');
            if (in_array($reason, ['expired', 'locked'], true)) {
                $this->forgetPartnerRecovery($request);

                return redirect()
                    ->route('site.partner.forgot-pin')
                    ->with('feedback', [
                        'tone' => 'error',
                        'title' => __('site.auth.partner_forgot_title'),
                        'message' => __('site.auth.pin_recovery.'.$reason),
                    ]);
            }

            return redirect()
                ->route('site.partner.forgot-pin', ['step' => 2])
                ->with('feedback', [
                    'tone' => 'error',
                    'title' => __('site.auth.pin_recovery.answers_wrong_title'),
                    'message' => __('site.auth.pin_recovery.mismatch', [
                        'remaining' => (int) ($result['remaining_attempts'] ?? 0),
                    ]),
                ]);
        }

        $request->session()->put('partner_pin_recovery_answers_ok', true);

        return redirect()
            ->route('site.partner.forgot-pin', ['step' => 3])
            ->with('feedback', [
                'tone' => 'success',
                'title' => __('site.auth.pin_recovery.answers_ok_title'),
                'message' => __('site.auth.pin_recovery.answers_ok_body'),
            ]);
    }

    public function resetForgotPin(Request $request, \App\Services\PinRecoveryChallengeService $recovery): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'pin' => ['required', 'string', new \App\Rules\FourDigitPin, 'confirmed'],
        ]);

        $token = (string) $data['token'];
        $sessionToken = (string) $request->session()->get('partner_pin_recovery_token', '');
        if ($sessionToken === '' || ! hash_equals($sessionToken, $token) || ! $request->session()->get('partner_pin_recovery_answers_ok')) {
            return redirect()
                ->route('site.partner.forgot-pin')
                ->with('feedback', [
                    'tone' => 'error',
                    'title' => __('site.auth.partner_forgot_title'),
                    'message' => __('site.auth.pin_recovery.expired'),
                ]);
        }

        $payload = $recovery->consumeVerified($token);
        $user = $payload ? \App\Models\User::query()->find($payload['user_id'] ?? null) : null;
        if (! $user || $user->role !== 'vendor') {
            return redirect()
                ->route('site.partner.forgot-pin')
                ->with('feedback', [
                    'tone' => 'error',
                    'title' => __('site.auth.partner_forgot_title'),
                    'message' => __('site.auth.pin_recovery.expired'),
                ]);
        }

        app(\App\Services\PinService::class)->setPin($user, $data['pin']);
        $user->forceFill(['locked_until' => null])->save();
        app(\App\Services\AuditService::class)->log($user, 'partner.pin.recovered', $user, [], ['via' => 'security_questions']);
        $this->forgetPartnerRecovery($request);

        return redirect()
            ->route('site.login', ['portal' => 'partner'])
            ->with('status', __('site.auth.pin_recovery.success'));
    }

    private function forgetPartnerRecovery(Request $request): void
    {
        $request->session()->forget([
            'partner_pin_recovery_token',
            'partner_pin_recovery_questions',
            'partner_pin_recovery_required',
            'partner_pin_recovery_code',
            'partner_pin_recovery_phone',
            'partner_pin_recovery_answers_ok',
            'partner_pin_recovery_expires_at',
        ]);
    }

}
