<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\CountryWaitlistRequest;
use App\Models\Customer;
use App\Models\Lender;
use App\Models\User;
use App\Models\Vendor;
use App\Rules\FourDigitPin;
use App\Services\AffiliateAttributionService;
use App\Services\AffiliateService;
use App\Services\BranchService;
use App\Services\CountrySettingsService;
use App\Services\GroupMemberOnboardingService;
use App\Services\GuarantorOnboardingService;
use App\Services\KopafastaLaunchService;
use App\Services\MembershipService;
use App\Services\NidaVerificationService;
use App\Services\NotificationService;
use App\Services\PartnerEnrollmentService;
use App\Services\PartnerPortalRedirectService;
use App\Services\PartnerWelcomeService;
use App\Services\PinRecoveryChallengeService;
use App\Services\PinService;
use App\Services\PortalOnboardingResumeService;
use App\Services\ReferralService;
use App\Services\TrustedDeviceService;
use App\Services\TurnstileService;
use App\Services\WebLoginThrottle;
use App\Services\WebTwoFactorAuthService;
use App\Support\Celebration;
use App\Support\NationalIdValidator;
use App\Support\NidaNumber;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(
        private readonly PinService $pins,
        private readonly WebLoginThrottle $throttle,
        private readonly TrustedDeviceService $trustedDevices,
    ) {}

    public function showLogin(Request $request): View
    {
        if ($request->boolean('clear_guarantor')) {
            $request->session()->forget(['guarantor_invite_token', 'login_redirect']);
        }

        if ($request->boolean('clear_group_invite')) {
            $request->session()->forget(['group_member_invite_token', 'login_redirect']);
        }

        if ($redirect = $request->query('redirect')) {
            $request->session()->put('login_redirect', $redirect);
        }

        $partnerPortal = $request->query('portal') === 'partner';
        if ($partnerPortal) {
            $request->session()->put('login_portal', 'partner');
        } else {
            $request->session()->forget('login_portal');
        }

        return view('site.auth.login', [
            'defaultMethod' => $request->query('auth_method', 'pin'),
            'prefillPhone' => $request->query('phone'),
            'biometricEnabled' => (bool) config('auth_portal.biometric_enabled', false),
            'clearedGuarantorContext' => $request->boolean('clear_guarantor'),
            'partnerPortal' => $partnerPortal || $request->session()->get('login_portal') === 'partner',
        ]);
    }

    /** End any borrower (or other) web session, then open the partner login screen. */
    public function switchToPartnerLogin(Request $request): RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $request->session()->put('login_portal', 'partner');

        return redirect()->route('site.login', ['portal' => 'partner']);
    }

    public function login(Request $request): RedirectResponse
    {
        app(TurnstileService::class)->assertHuman($request);

        $method = $request->input('auth_method', 'pin');

        return $method === 'password'
            ? $this->loginWithPassword($request)
            : $this->loginWithPin($request);
    }

    public function loginWithPin(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'phone_local' => ['nullable', 'string', 'max:20'],
            'pin' => ['required', 'string', new FourDigitPin],
            'partner_code' => ['nullable', 'string', 'max:50'],
            'remember' => ['nullable', 'boolean'],
            'trust_device' => ['nullable', 'boolean'],
        ]);

        $phone = PhoneNumber::fromRequest($request, 'phone')
            ?? PhoneNumber::normalizeForCountry($data['phone'], 'TZ')
            ?? trim($data['phone']);

        if ($this->throttle->tooManyAttempts($phone, $request)) {
            $seconds = $this->throttle->availableIn($phone, $request);

            return back()
                ->withErrors(['phone' => 'Too many failed attempts. Try again in '.ceil($seconds / 60).' minutes.'])
                ->withInput(['phone' => $phone, 'auth_method' => 'pin']);
        }

        $partnerPortal = $request->session()->get('login_portal') === 'partner'
            || filled($data['partner_code'] ?? null);

        $user = $partnerPortal
            ? ($this->findVendorUserByPhone($phone) ?? $this->findInvestorUserByPhone($phone))
            : $this->findBorrowerUserByPhone($phone);

        if (! $user) {
            return $this->failedLogin($request, $phone, 'phone', 'Phone number or PIN is incorrect.');
        }

        if ($locked = $this->lockedResponse($user)) {
            return $locked;
        }

        if (! $this->pins->hasPin($user)) {
            return back()
                ->withErrors(['phone' => 'No PIN set for this account. Sign in with email and password, then set your PIN in Profile → Security.'])
                ->withInput(['phone' => $phone, 'auth_method' => 'pin']);
        }

        if (! $this->pins->verify($data['pin'], $user->pin_hash)) {
            return $this->failedLogin($request, $phone, 'phone', 'Phone number or PIN is incorrect.', $user);
        }

        if (! $partnerPortal && $user->role === 'vendor') {
            return back()
                ->withErrors(['phone' => __('site.auth.partner_account_borrower_login')])
                ->withInput(['phone' => $phone, 'auth_method' => 'pin']);
        }

        if ($partnerPortal && ! in_array($user->role, ['vendor', 'investor'], true)) {
            return back()
                ->withErrors(['phone' => 'No partner account found for this phone number.'])
                ->withInput(['phone' => $phone, 'auth_method' => 'pin', 'partner_code' => $data['partner_code'] ?? null]);
        }

        if (filled($data['partner_code'] ?? null)) {
            $code = strtoupper(trim($data['partner_code']));

            if ($user->role === 'investor') {
                $lender = Lender::query()
                    ->where('user_id', $user->id)
                    ->where('code', $code)
                    ->first();

                if (! $lender) {
                    return back()
                        ->withErrors(['partner_code' => 'Partner code does not match this account.'])
                        ->withInput(['phone' => $phone, 'auth_method' => 'pin', 'partner_code' => $data['partner_code']]);
                }
            } else {
                $vendor = Vendor::query()
                    ->where('user_id', $user->id)
                    ->where('partner_number', $code)
                    ->first();

                if (! $vendor) {
                    return back()
                        ->withErrors(['partner_code' => 'Partner code does not match this account.'])
                        ->withInput(['phone' => $phone, 'auth_method' => 'pin', 'partner_code' => $data['partner_code']]);
                }

                if (! $vendor->activated_at) {
                    return redirect()->route('site.partner.start')
                        ->with('warning', 'Complete partner activation before signing in.');
                }
            }
        } elseif ($user->role === 'vendor') {
            $vendor = Vendor::query()->where('user_id', $user->id)->first();
            if ($vendor && ! $vendor->activated_at) {
                return redirect()->route('site.partner.start')
                    ->with('warning', 'Complete partner activation before signing in.');
            }
        }

        return $this->completeWebLogin($user, $request, $phone, (bool) ($data['trust_device'] ?? false));
    }

    public function loginWithPassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
            'trust_device' => ['nullable', 'boolean'],
        ]);

        $login = trim($data['login']);

        if ($this->throttle->tooManyAttempts($login, $request)) {
            $seconds = $this->throttle->availableIn($login, $request);

            return back()
                ->withErrors(['login' => 'Too many failed attempts. Try again in '.ceil($seconds / 60).' minutes.'])
                ->withInput(['login' => $login, 'auth_method' => 'password']);
        }

        $user = User::query()
            ->where('email', $login)
            ->orWhere('phone', $login)
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return $this->failedLogin($request, $login, 'login', 'Those credentials do not match. Please try again.', $user);
        }

        if ($locked = $this->lockedResponse($user)) {
            return $locked;
        }

        $partnerPortal = $request->session()->get('login_portal') === 'partner';

        if ($partnerPortal && ! in_array($user->role, ['vendor', 'investor'], true)) {
            $vendorUser = $this->findVendorUserByPhone($login);
            if ($vendorUser) {
                $user = $vendorUser;
            } else {
                return back()
                    ->withErrors(['login' => 'No partner account found for this login. Use the member login page for borrower access.'])
                    ->withInput(['login' => $login, 'auth_method' => 'password']);
            }
        }

        if (! $partnerPortal && $user->role === 'vendor') {
            return back()
                ->withErrors(['login' => __('site.auth.partner_account_borrower_login')])
                ->withInput(['login' => $login, 'auth_method' => 'password']);
        }

        return $this->completeWebLogin($user, $request, $login, (bool) ($data['trust_device'] ?? false));
    }

    public function showSetupPin(PinRecoveryChallengeService $recovery): View|RedirectResponse
    {
        $user = Auth::user();
        if ($user->role !== 'borrower') {
            return redirect()->route('site.borrower.dashboard');
        }

        $needsPin = ! $this->pins->hasPin($user);
        $needsRecovery = ! $recovery->hasEnrolledAnswers($user);

        if (! $needsPin && ! $needsRecovery) {
            return redirect()->route('site.borrower.dashboard');
        }

        $keys = session('pin_setup_question_keys');
        if (! is_array($keys) || count($keys) < (int) config('pin_recovery.questions_to_ask', 3)) {
            $keys = $recovery->pickRandomKeys();
            session(['pin_setup_question_keys' => $keys]);
        }

        return view('site.auth.setup-pin', [
            'needsPin' => $needsPin,
            'phase' => $needsPin ? 'pin' : 'questions',
            'questions' => $recovery->questionsForKeys($keys),
        ]);
    }

    public function storeSetupPin(Request $request, PinRecoveryChallengeService $recovery): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->role === 'borrower', 403);

        $needsPin = ! $this->pins->hasPin($user);
        $needsRecovery = ! $recovery->hasEnrolledAnswers($user);
        $phase = (string) $request->input('phase', $needsPin ? 'pin' : 'questions');

        if ($phase === 'pin') {
            abort_unless($needsPin, 403);
            $data = $request->validate([
                'pin' => ['required', 'string', new FourDigitPin, 'confirmed'],
            ]);
            $this->pins->setPin($user, $data['pin']);

            if (! $needsRecovery) {
                $request->session()->forget('pin_setup_question_keys');

                return $this->redirectAfterPinSetup($request, $user);
            }

            return redirect()->route('site.borrower.setup-pin')
                ->with('status', __('site.auth.pin_recovery.setup_pin_saved'));
        }

        abort_unless($needsRecovery, 403);
        $keys = session('pin_setup_question_keys', []);
        if (! is_array($keys) || count($keys) < (int) config('pin_recovery.questions_to_ask', 3)) {
            return redirect()->route('site.borrower.setup-pin')
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

        return $this->redirectAfterPinSetup($request, $user);
    }

    private function redirectAfterPinSetup(Request $request, $user): RedirectResponse
    {
        if ($user->customer && ($guarantorRedirect = app(PortalOnboardingResumeService::class)->redirectIfPending($request, $user->customer))) {
            return $guarantorRedirect;
        }

        if ($returnUrl = $request->session()->pull('login_redirect')) {
            return redirect($returnUrl)
                ->with('status', __('site.auth.pin_recovery.setup_done'))
                ->with(Celebration::SESSION_KEY, ['registration']);
        }

        return redirect()->route('site.borrower.dashboard')
            ->with('status', __('borrower.membership.pin_ready_browse'))
            ->with(Celebration::SESSION_KEY, ['registration']);
    }

    public function showForgotPin(Request $request): View
    {
        $step = (int) $request->query('step', old('step', 1));
        $mode = (string) $request->session()->get('pin_recovery_mode', old('mode', 'phone'));
        $questions = $request->session()->get('pin_recovery_questions', []);
        $verified = (bool) $request->session()->get('pin_recovery_answers_ok', false);

        if ($step === 2 && ($mode !== 'kba' || blank($questions))) {
            $step = 1;
            $mode = 'phone';
        }

        if ($step === 3 && (! $verified || blank($request->session()->get('pin_recovery_token')))) {
            $step = blank($questions) ? 1 : 2;
        }

        return view('site.auth.forgot-pin', [
            'step' => $step,
            'mode' => $mode,
            'questions' => is_array($questions) ? $questions : [],
            'requiredCorrect' => (int) $request->session()->get('pin_recovery_required', 2),
            'prefillPhone' => old('phone', $request->session()->get('pin_recovery_phone', $request->query('phone'))),
            'challengeToken' => $request->session()->get('pin_recovery_token'),
            'expiresAt' => (int) $request->session()->get('pin_recovery_expires_at', 0),
        ]);
    }

    public function startPinRecovery(Request $request, PinRecoveryChallengeService $challenge): RedirectResponse
    {
        app(TurnstileService::class)->assertHuman($request);

        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $phone = trim($data['phone']);
        $user = $this->findUserByPhone($phone);

        $request->session()->forget([
            'pin_recovery_token',
            'pin_recovery_mode',
            'pin_recovery_questions',
            'pin_recovery_required',
            'pin_recovery_phone',
            'pin_recovery_answers_ok',
            'pin_recovery_expires_at',
        ]);

        if (! $user) {
            return back()
                ->withInput(['phone' => $phone])
                ->with('feedback', [
                    'tone' => 'error',
                    'title' => __('site.auth.pin_recovery.title'),
                    'message' => __('site.auth.pin_recovery.not_registered'),
                ]);
        }

        if (! $this->pins->hasPin($user)) {
            return back()
                ->withInput(['phone' => $phone])
                ->with('feedback', [
                    'tone' => 'warning',
                    'title' => __('site.auth.pin_recovery.title'),
                    'message' => __('site.auth.pin_recovery.no_pin_yet'),
                ]);
        }

        $started = $challenge->startForUser($user);
        if (! $started) {
            return back()
                ->withInput(['phone' => $phone])
                ->with('feedback', [
                    'tone' => 'warning',
                    'title' => __('site.auth.pin_recovery.title'),
                    'message' => __('site.auth.pin_recovery.not_enrolled'),
                ]);
        }

        $request->session()->put([
            'pin_recovery_token' => $started['token'],
            'pin_recovery_mode' => 'kba',
            'pin_recovery_questions' => $started['questions'],
            'pin_recovery_required' => $started['required_correct'],
            'pin_recovery_phone' => $phone,
            'pin_recovery_answers_ok' => false,
            'pin_recovery_expires_at' => (int) ($started['expires_at'] ?? now()->addSeconds($challenge->sessionTtlSeconds())->getTimestamp()),
        ]);

        return redirect()
            ->route('site.forgot-pin', ['step' => 2])
            ->withInput(['phone' => $phone, 'step' => 2, 'mode' => 'kba']);
    }

    public function verifyPinRecoveryAnswers(Request $request, PinRecoveryChallengeService $challenge): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'phone' => ['required', 'string', 'max:20'],
            'answers' => ['required', 'array'],
            'answers.*' => ['nullable', 'string', 'max:120'],
        ]);

        $token = (string) $data['token'];
        $sessionToken = (string) $request->session()->get('pin_recovery_token', '');
        if ($sessionToken === '' || ! hash_equals($sessionToken, $token)) {
            return redirect()
                ->route('site.forgot-pin')
                ->with('feedback', [
                    'tone' => 'error',
                    'title' => __('site.auth.pin_recovery.title'),
                    'message' => __('site.auth.pin_recovery.expired'),
                ]);
        }

        $result = $challenge->verify($token, $data['answers'] ?? []);
        if (! ($result['ok'] ?? false)) {
            $reason = $result['reason'] ?? 'mismatch';
            if ($reason === 'locked' || $reason === 'expired') {
                $request->session()->forget([
                    'pin_recovery_token',
                    'pin_recovery_mode',
                    'pin_recovery_questions',
                    'pin_recovery_required',
                    'pin_recovery_phone',
                    'pin_recovery_answers_ok',
                    'pin_recovery_expires_at',
                ]);
                $challenge->forget($token);

                return redirect()
                    ->route('site.forgot-pin')
                    ->with('feedback', [
                        'tone' => 'error',
                        'title' => __('site.auth.pin_recovery.title'),
                        'message' => __('site.auth.pin_recovery.'.$reason),
                    ]);
            }

            // Wrong answers: stay on questions, try again.
            return redirect()
                ->route('site.forgot-pin', ['step' => 2])
                ->withInput($request->except(['_token']))
                ->with('feedback', [
                    'tone' => 'warning',
                    'title' => __('site.auth.pin_recovery.answers_wrong_title'),
                    'message' => __('site.auth.pin_recovery.mismatch', [
                        'remaining' => $result['remaining_attempts'] ?? 0,
                    ]),
                ]);
        }

        $request->session()->put('pin_recovery_answers_ok', true);

        return redirect()
            ->route('site.forgot-pin', ['step' => 3])
            ->with('feedback', [
                'tone' => 'success',
                'title' => __('site.auth.pin_recovery.answers_ok_title'),
                'message' => __('site.auth.pin_recovery.answers_ok_body'),
            ]);
    }

    public function resetPinWithChallenge(Request $request, PinRecoveryChallengeService $challenge): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'phone' => ['required', 'string', 'max:20'],
            'pin' => ['required', 'string', new FourDigitPin, 'confirmed'],
        ]);

        $token = (string) $data['token'];
        $sessionToken = (string) $request->session()->get('pin_recovery_token', '');
        if ($sessionToken === '' || ! hash_equals($sessionToken, $token) || ! $request->session()->get('pin_recovery_answers_ok')) {
            return redirect()
                ->route('site.forgot-pin')
                ->with('feedback', [
                    'tone' => 'error',
                    'title' => __('site.auth.pin_recovery.title'),
                    'message' => __('site.auth.pin_recovery.expired'),
                ]);
        }

        $payload = $challenge->consumeVerified($token);
        $user = $payload ? User::query()->find($payload['user_id'] ?? null) : null;
        if (! $user) {
            return redirect()
                ->route('site.forgot-pin')
                ->with('feedback', [
                    'tone' => 'error',
                    'title' => __('site.auth.pin_recovery.title'),
                    'message' => __('site.auth.pin_recovery.expired'),
                ]);
        }

        $this->pins->setPin($user, $data['pin']);
        $user->forceFill(['locked_until' => null])->save();

        if ($user->customer) {
            app(NotificationService::class)->notifyInApp(
                $user->customer,
                __('site.auth.pin_recovery.success'),
                'security',
                'pin_reset',
                __('site.auth.pin_recovery.success_title'),
                '/borrower/notifications',
                null,
                [
                    'title_key' => 'site.auth.pin_recovery.success_title',
                    'body_key' => 'site.auth.pin_recovery.success',
                ],
            );
        }

        $request->session()->forget([
            'pin_recovery_token',
            'pin_recovery_mode',
            'pin_recovery_questions',
            'pin_recovery_required',
            'pin_recovery_phone',
            'pin_recovery_answers_ok',
            'pin_recovery_expires_at',
        ]);

        return redirect()->route('site.login', [
            'phone' => $data['phone'],
            'auth_method' => 'pin',
        ]);
    }

    public function swapSetupPinQuestion(Request $request, PinRecoveryChallengeService $recovery): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->role === 'borrower', 403);

        $data = $request->validate([
            'index' => ['required', 'integer', 'min:0', 'max:9'],
        ]);

        $keys = session('pin_setup_question_keys', []);
        if (! is_array($keys) || $keys === []) {
            return redirect()->route('site.borrower.setup-pin');
        }

        session([
            'pin_setup_question_keys' => $recovery->swapQuestionKey($keys, (int) $data['index']),
        ]);

        return redirect()->route('site.borrower.setup-pin');
    }

    private function completeWebLogin(User $user, Request $request, string $identifier, bool $trustDevice): RedirectResponse
    {
        if (! in_array($user->role, ['borrower', 'vendor', 'investor'], true)) {
            return redirect()->route('staff.login')
                ->withErrors(['email' => 'Staff accounts must sign in from the staff workspace.']);
        }

        if (! ($user->is_active ?? true)) {
            return back()->withErrors(['login' => 'This account is inactive.']);
        }

        if ($user->role === 'vendor' && ($twoFactorRedirect = $this->partnerTwoFactorGate($user, $request))) {
            return $twoFactorRedirect;
        }

        $this->throttle->clear($identifier, $request);
        $this->throttle->log($request, 'auth.web_login_success', $identifier, ['role' => $user->role], $user->id);

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $preferred = data_get($user->preferences, 'preferred_locale')
            ?? data_get($user->preferences, 'locale');
        if (is_string($preferred) && in_array($preferred, ['en', 'sw'], true)) {
            $request->session()->put('locale', $preferred);
            app()->setLocale($preferred);
        }

        $request->session()->forget('login_portal');

        app(KopafastaLaunchService::class)->arm($request);

        $trusted = $this->trustedDevices->extractToken($request);
        if ($trusted && ($device = $this->trustedDevices->find($user, $trusted))) {
            $this->trustedDevices->touch($device);
        }

        $response = $this->redirectAfterLogin($user);

        if ($trustDevice) {
            $token = $this->trustedDevices->create($user, $request);
            $response->withCookie($this->trustedDevices->makeCookie($token));
        }

        if ($user->role === 'borrower' && ! $this->pins->hasPin($user)) {
            return redirect()->route('site.borrower.setup-pin');
        }

        if ($user->role === 'borrower' && $user->customer) {
            if ($guarantorRedirect = app(PortalOnboardingResumeService::class)->redirectIfPending($request, $user->customer)) {
                return $guarantorRedirect;
            }
        }

        if ($user->role === 'borrower' && ($returnUrl = $request->session()->pull('login_redirect'))) {
            if (is_string($returnUrl) && (str_starts_with($returnUrl, url('/')) || str_starts_with($returnUrl, '/'))) {
                return redirect()->to($returnUrl);
            }
        }

        if ($user->role === 'vendor') {
            app(PartnerWelcomeService::class)->sendIfFirstLogin($user);
        }

        return $response;
    }

    private function failedLogin(Request $request, string $identifier, string $field, string $message, ?User $user = null): RedirectResponse
    {
        $this->throttle->hit($identifier, $request);
        $this->throttle->log($request, 'auth.web_failed_login', $identifier, [
            'remaining_attempts' => $this->throttle->remainingAttempts($identifier, $request),
        ], $user?->id);

        if ($user) {
            $this->throttle->lockUserIfNeeded($user, $request, $identifier);
        }

        return back()
            ->withErrors([$field => $message])
            ->withInput($request->only('login', 'phone', 'auth_method'));
    }

    private function lockedResponse(User $user): ?RedirectResponse
    {
        if (! $user->locked_until || ! $user->locked_until->isFuture()) {
            return null;
        }

        $customer = $user->customer;
        $nida = app(NidaVerificationService::class);

        if ($customer && $nida->isLocked($customer)) {
            $message = $nida->lockMessage($customer) ?? __('borrower.nida.result.locked_default');
        } else {
            $minutes = max(1, (int) now()->diffInMinutes($user->locked_until, false));
            $message = "Account locked after too many failed attempts. Try again in {$minutes} minutes.";
        }

        return back()->withErrors([
            'login' => $message,
            'phone' => $message,
        ]);
    }

    private function findUserByPhone(string $phone): ?User
    {
        $digits = preg_replace('/\D/', '', $phone);
        if ($digits === '') {
            return null;
        }

        $suffix = substr($digits, -9);

        return User::query()
            ->where(function ($query) use ($phone, $digits, $suffix) {
                $query->where('phone', $phone)
                    ->orWhere('phone', $digits)
                    ->orWhere('phone', 'like', '%'.$suffix);
            })
            ->first();
    }

    private function findBorrowerUserByPhone(string $phone): ?User
    {
        $digits = preg_replace('/\D/', '', $phone);
        if ($digits === '') {
            return null;
        }

        $suffix = substr($digits, -9);

        return User::query()
            ->where('role', 'borrower')
            ->where(function ($query) use ($phone, $digits, $suffix) {
                $query->where('phone', $phone)
                    ->orWhere('phone', $digits)
                    ->orWhere('phone', 'like', '%'.$suffix);
            })
            ->first();
    }

    private function findVendorUserByPhone(string $phone): ?User
    {
        $digits = preg_replace('/\D/', '', $phone);
        if ($digits === '') {
            return null;
        }

        $suffix = substr($digits, -9);

        $partner = Vendor::query()
            ->whereNotNull('user_id')
            ->where(function ($query) use ($phone, $digits, $suffix) {
                $query->where('phone', $phone)
                    ->orWhere('phone', $digits)
                    ->orWhere('phone', 'like', '%'.$suffix);
            })
            ->first();

        if ($partner?->user_id) {
            return User::query()->where('id', $partner->user_id)->where('role', 'vendor')->first();
        }

        return User::query()
            ->where('role', 'vendor')
            ->where(function ($query) use ($phone, $digits, $suffix) {
                $query->where('phone', $phone)
                    ->orWhere('phone', $digits)
                    ->orWhere('phone', 'like', '%'.$suffix);
            })
            ->first();
    }

    private function findInvestorUserByPhone(string $phone): ?User
    {
        $digits = preg_replace('/\D/', '', $phone);
        if ($digits === '') {
            return null;
        }

        $suffix = substr($digits, -9);

        return User::query()
            ->where('role', 'investor')
            ->where(function ($query) use ($phone, $digits, $suffix) {
                $query->where('phone', $phone)
                    ->orWhere('phone', $digits)
                    ->orWhere('phone', 'like', '%'.$suffix);
            })
            ->first();
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = Auth::guard('web')->user();
        $isPartner = $user && in_array((string) $user->role, ['vendor', 'affiliate'], true);

        Auth::guard('web')->logout();
        app(WebTwoFactorAuthService::class)->clearSessionVerification($request);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route($isPartner ? 'site.partners' : 'site.home')
            ->withCookie(app(TrustedDeviceService::class)->forgetCookie());
    }

    public function showRegisterBorrower(Request $request, ReferralService $referrals): View
    {
        if ($ref = $request->query('ref')) {
            $referrals->recordClick((string) $ref, $request);
        }
        if ($code = $request->query('aff')) {
            session(['affiliate_code' => strtoupper(trim($code))]);
        }

        app(AffiliateAttributionService::class)->mergeIntoSession($request);

        if ($redirect = $request->query('redirect')) {
            $request->session()->put('login_redirect', $redirect);
        }

        $guarantorOnboarding = app(GuarantorOnboardingService::class);
        $groupOnboarding = app(GroupMemberOnboardingService::class);
        $groupOnboarding->seedInvitationFromQuery($request);

        $guarantorInvitation = $guarantorOnboarding->invitationFromSession($request);
        $groupInvitation = $guarantorInvitation ? null : $groupOnboarding->invitationFromSession($request);

        $guarantorRegistration = $guarantorOnboarding->registrationPrefill($guarantorInvitation)
            ?? $groupOnboarding->registrationPrefill($groupInvitation);
        $isGroupInviteRegistration = $groupInvitation !== null && $guarantorInvitation === null;

        $registrationCountries = app(CountrySettingsService::class)->forRegistration();
        $defaultCountry = app(CountrySettingsService::class)->defaultCountryCode();
        $defaultDialPrefix = collect($registrationCountries)->firstWhere('code', $defaultCountry)['prefix'] ?? '+255';

        return view('site.auth.register-borrower', [
            'referralCode' => $request->query('ref'),
            'affiliateCode' => $request->query('aff') ?? session('affiliate_code'),
            'guarantorRegistration' => $guarantorRegistration,
            'isGuarantorRegistration' => $guarantorRegistration !== null && ! $isGroupInviteRegistration,
            'isGroupInviteRegistration' => $isGroupInviteRegistration,
            'registrationCountries' => $registrationCountries,
            'defaultCountry' => $defaultCountry,
            'defaultDialPrefix' => $defaultDialPrefix,
        ]);
    }

    public function checkBorrowerPhone(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $phoneDigits = preg_replace('/\D/', '', $data['phone']) ?: '';
        if (strlen($phoneDigits) < 11) {
            return response()->json([
                'available' => false,
                'message' => __('borrower.auth.phone_invalid'),
            ]);
        }

        $userTaken = User::query()
            ->where('role', 'borrower')
            ->where(function ($query) use ($data, $phoneDigits) {
                $query->where('phone', $data['phone'])
                    ->orWhere('phone', $phoneDigits)
                    ->when(strlen($phoneDigits) >= 9, fn ($q) => $q->orWhere('phone', 'like', '%'.substr($phoneDigits, -9)));
            })
            ->exists();

        $customerTaken = Customer::query()
            ->where(function ($query) use ($data, $phoneDigits) {
                $query->where('phone', $data['phone'])
                    ->orWhere('phone', $phoneDigits)
                    ->when(strlen($phoneDigits) >= 9, fn ($q) => $q->orWhere('phone', 'like', '%'.substr($phoneDigits, -9)));
            })
            ->exists();

        if ($userTaken || $customerTaken) {
            return response()->json([
                'available' => false,
                'message' => __('borrower.auth.phone_taken'),
                'redirect' => route('site.login', [
                    'phone' => $data['phone'],
                    'auth_method' => 'pin',
                ]),
            ]);
        }

        return response()->json(['available' => true]);
    }

    public function registerBorrower(Request $request, ReferralService $referrals): RedirectResponse
    {
        app(TurnstileService::class)->assertHuman($request);

        $guarantorOnboarding = app(GuarantorOnboardingService::class);
        $groupOnboarding = app(GroupMemberOnboardingService::class);

        $guarantorInvitation = $guarantorOnboarding->invitationFromSession($request);
        $groupInvitation = $guarantorInvitation ? null : $groupOnboarding->invitationFromSession($request);

        $guarantorPrefill = $guarantorOnboarding->registrationPrefill($guarantorInvitation)
            ?? $groupOnboarding->registrationPrefill($groupInvitation);
        $isGuarantorRegistration = $guarantorPrefill !== null;

        $countryService = app(CountrySettingsService::class);
        $activeCountryCodes = collect($countryService->forRegistration())
            ->where('active', true)
            ->pluck('code')
            ->all();

        $rules = [
            'country' => ['required', 'string', 'in:'.implode(',', $activeCountryCodes)],
            'first_name' => ['required', 'string', 'max:60'],
            'middle_name' => ['nullable', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'gender' => ['required', 'in:male,female'],
            'phone' => [
                'required',
                'string',
                'max:20',
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'referral_code' => ['nullable', 'string', 'max:32'],
            'affiliate_code' => ['nullable', 'string', 'max:32'],
            'promo_code' => ['nullable', 'string', 'max:40'],
        ];

        if ($isGuarantorRegistration) {
            $rules['national_id'] = ['nullable', 'string', 'max:30'];
        }

        $data = $request->validate($rules);

        $phoneDigits = preg_replace('/\D/', '', $data['phone']);
        $phoneTaken = User::query()
            ->where('role', 'borrower')
            ->where(function ($query) use ($data, $phoneDigits) {
                $query->where('phone', $data['phone'])
                    ->orWhere('phone', $phoneDigits)
                    ->when(strlen($phoneDigits) >= 9, fn ($q) => $q->orWhere('phone', 'like', '%'.substr($phoneDigits, -9)));
            })
            ->exists()
            || Customer::query()
                ->where(function ($query) use ($data, $phoneDigits) {
                    $query->where('phone', $data['phone'])
                        ->orWhere('phone', $phoneDigits)
                        ->when(strlen($phoneDigits) >= 9, fn ($q) => $q->orWhere('phone', 'like', '%'.substr($phoneDigits, -9)));
                })
                ->exists();

        if ($phoneTaken && ! $isGuarantorRegistration) {
            return redirect()
                ->route('site.login', [
                    'phone' => $data['phone'],
                    'auth_method' => 'pin',
                ])
                ->with('status', __('borrower.auth.phone_taken_login'));
        }

        if (filled($data['promo_code'] ?? null) && blank($data['affiliate_code'] ?? null) && blank($data['referral_code'] ?? null)) {
            $promo = strtoupper(trim($data['promo_code']));
            $data['affiliate_code'] = $promo;
            $data['referral_code'] = $promo;
        }

        if ($isGuarantorRegistration && $guarantorInvitation) {
            if (! $guarantorOnboarding->phoneMatchesInvitation($guarantorInvitation, $data['phone'])) {
                return back()
                    ->withInput()
                    ->withErrors(['phone' => __('borrower.guarantor_invite.register_phone_mismatch')]);
            }
        } elseif ($isGuarantorRegistration && $groupInvitation) {
            if (! $groupOnboarding->phoneMatchesInvitation($groupInvitation, $data['phone'])) {
                return back()
                    ->withInput()
                    ->withErrors(['phone' => __('borrower.apply.group.invite_phone_mismatch')]);
            }
        }

        $digits = preg_replace('/\D/', '', $data['phone']) ?: Str::random(8);
        $email = $digits.'@phone.kopafasta.local';

        $user = DB::transaction(function () use ($data, $email, $referrals, $request) {
            $fullName = trim(collect([$data['first_name'], $data['middle_name'] ?? null, $data['last_name']])->filter()->implode(' '));

            $user = User::create([
                'name' => $fullName,
                'email' => $email,
                'phone' => $data['phone'],
                'password' => Hash::make($data['password']),
                'role' => 'borrower',
                'is_active' => true,
            ]);

            $customer = Customer::create([
                'user_id' => $user->id,
                'customer_number' => 'C-'.strtoupper(Str::random(6)),
                'type' => 'individual',
                'status' => 'active',
                'branch_id' => app(BranchService::class)->headOfficeId(),
                'country_code' => strtoupper($data['country']),
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'gender' => $data['gender'],
                'national_id' => filled($data['national_id'] ?? null)
                    ? (NationalIdValidator::format($data['national_id'], $data['country']) ?? NidaNumber::format($data['national_id']))
                    : null,
                'date_of_birth' => null,
                'email' => null,
                'phone' => $data['phone'],
                'onboarded_at' => now(),
            ]);

            app(BranchService::class)->assignDefault($customer);
            app(MembershipService::class)->ensureMemberNumber($customer);

            $referrals->attachReferrerFromSession($customer, $request);
            if (blank($customer->fresh()->referred_by_customer_id)) {
                $referrals->attachReferrer($customer, $data['referral_code'] ?? null);
            }
            $referrals->ensureCode($customer);
            app(AffiliateService::class)->attachAffiliate(
                $customer,
                $data['affiliate_code'] ?? session('affiliate_code'),
                $request
            );

            $guarantorOnboarding = app(GuarantorOnboardingService::class);
            if ($token = request()->session()->get('guarantor_invite_token')) {
                $invitation = $guarantorOnboarding->findByToken($token);
                if ($invitation) {
                    $guarantorOnboarding->linkInvitee($invitation, $customer, fromTrustedSession: true);
                }
            }

            $groupOnboarding = app(GroupMemberOnboardingService::class);
            if ($token = request()->session()->get('group_member_invite_token')) {
                $invitation = $groupOnboarding->findByToken($token);
                if ($invitation) {
                    $groupOnboarding->linkInvitee($invitation, $customer, fromTrustedSession: true);
                }
            }

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        $defaultLocale = app(CountrySettingsService::class)->defaultLocale($data['country']);
        $request->session()->put('locale', $defaultLocale);
        app()->setLocale($defaultLocale);
        $prefs = $user->preferences ?? [];
        $prefs['preferred_locale'] = $defaultLocale;
        $user->preferences = $prefs;
        $user->save();

        $guarantorOnboarding = app(GuarantorOnboardingService::class);
        $groupOnboarding = app(GroupMemberOnboardingService::class);
        if ($user->customer && ($invitation = $guarantorOnboarding->pendingInvitationForCustomer($user->customer))) {
            $guarantorOnboarding->rememberInvitation($request, $invitation);
        } elseif ($user->customer && ($invitation = $groupOnboarding->pendingInvitationForCustomer($user->customer))) {
            $groupOnboarding->rememberInvitation($request, $invitation);
        }

        $welcome = $isGuarantorRegistration
            ? ($guarantorInvitation
                ? __('borrower.guarantor_invite.continue_after_pin')
                : __('borrower.apply.group.continue_after_pin'))
            : __('borrower.membership.welcome_pay_body');

        if ($user->customer && ! $isGuarantorRegistration) {
            try {
                app(NotificationService::class)->notifyInApp(
                    $user->customer,
                    __('borrower.membership.welcome_pay_body'),
                    'membership',
                    'membership_welcome',
                    __('borrower.membership.welcome_pay_title'),
                    route('site.membership.renew'),
                    __('borrower.membership.pay_registration'),
                );
            } catch (\Throwable) {
                // Non-blocking — registration should still succeed.
            }
        }

        return redirect()->route('site.borrower.setup-pin')
            ->with('status', $welcome);
    }

    public function storeWaitlistRequest(Request $request): RedirectResponse
    {
        $countryLabels = [
            'TZ' => 'Tanzania',
            'KE' => 'Kenya',
            'UG' => 'Uganda',
            'RW' => 'Rwanda',
            'BI' => 'Burundi',
            'SS' => 'South Sudan',
        ];

        $data = $request->validate([
            'country' => ['required', 'string', 'in:TZ,KE,UG,RW,BI,SS'],
            'email' => ['required', 'email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'step' => ['nullable', 'integer'],
        ]);

        CountryWaitlistRequest::updateOrCreate([
            'country_code' => $data['country'],
            'email' => strtolower($data['email']),
        ], [
            'phone' => $data['phone'] ?? null,
        ]);

        return back()
            ->with('waitlist_status', 'Thanks! We will notify you when Kopafasta launches in '.$countryLabels[$data['country']].'.')
            ->withInput([
                'country' => $data['country'],
                'step' => 1,
                'waitlist_email' => $data['email'],
                'waitlist_local_phone' => PhoneNumber::split($data['phone'] ?? '')['local'] ?? '',
            ]);
    }

    public function showRegisterVendor(): View
    {
        return view('site.auth.register-vendor');
    }

    public function registerVendor(Request $request): RedirectResponse
    {
        app(TurnstileService::class)->assertHuman($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', 'string', 'in:gps_installer,insurance,valuer,yard,debt_collector,supplier,auctioneer,legal_partner,call_center,towing'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $data['category'] = app(PartnerEnrollmentService::class)->normalizeCategory($data['category']);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => Hash::make($data['password']),
                'role' => 'vendor',
                'is_active' => true,
            ]);

            Vendor::create([
                'user_id' => $user->id,
                'vendor_number' => 'V-'.strtoupper(Str::random(6)),
                'name' => $data['name'],
                'category' => $data['category'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'address' => $data['address'] ?? null,
                'status' => 'pending',
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('site.partner.dashboard');
    }

    public function redirectAfterLogin($user): RedirectResponse
    {
        return match ($user->role) {
            'borrower' => redirect()->route('site.borrower.dashboard'),
            'vendor' => redirect()->to(app(PartnerPortalRedirectService::class)->homeUrl($user)),
            'investor' => redirect()->route('site.investor.dashboard'),
            default => redirect()->route('admin.dashboard'),
        };
    }

    /**
     * Branded "no, you want the admin console" redirector,
     * in case anyone hits /login looking for staff access.
     */
    public function staffHint(): RedirectResponse
    {
        return redirect()->route('staff.login');
    }

    private function partnerTwoFactorGate(User $user, Request $request): ?RedirectResponse
    {
        $twoFactor = app(WebTwoFactorAuthService::class);
        $redirectTo = app(PartnerPortalRedirectService::class)->homeUrl($user);

        if ($twoFactor->mustEnroll($user, 'partner')) {
            $twoFactor->storePendingLogin($request, $user, 'web', 'partner', $redirectTo, $request->boolean('remember'));

            return redirect()->route('auth.two-factor.setup', ['context' => 'partner']);
        }

        if ($twoFactor->needsChallenge($user, $request, 'partner')) {
            $twoFactor->storePendingLogin($request, $user, 'web', 'partner', $redirectTo, $request->boolean('remember'));

            return redirect()->route('auth.two-factor.challenge', ['context' => 'partner']);
        }

        return null;
    }

    public function showRegisterInvestor(): View
    {
        return view('site.auth.register-investor');
    }

    public function registerInvestor(Request $request): RedirectResponse
    {
        app(TurnstileService::class)->assertHuman($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:individual,institution,fund'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => Hash::make($data['password']),
                'role' => 'investor',
                'is_active' => true,
            ]);

            Lender::create([
                'user_id' => $user->id,
                'code' => 'INV-'.strtoupper(Str::random(6)),
                'name' => $data['name'],
                'type' => $data['type'],
                'contact_person' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'address' => $data['address'] ?? null,
                'credit_limit' => 0,
                'available_balance' => 0,
                'risk_preference' => 'medium',
                'status' => 'active',
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('site.investor.dashboard');
    }

    public function showRegisterCapital(): View
    {
        return view('site.auth.register-capital');
    }

    public function registerCapital(Request $request): RedirectResponse
    {
        app(TurnstileService::class)->assertHuman($request);

        $data = $request->validate([
            'organization' => ['required', 'string', 'max:160'],
            'org_type' => ['required', 'in:bank,mfi,dfi,family_office,asset_manager,other'],
            'contact_name' => ['required', 'string', 'max:120'],
            'contact_role' => ['nullable', 'string', 'max:80'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20'],
            'country' => ['required', 'string', 'max:60'],
            'commitment_band' => ['required', 'in:50k_250k,250k_1m,1m_5m,5m_plus'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['contact_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => Hash::make($data['password']),
                'role' => 'investor',
                'is_active' => true,
            ]);

            $lenderAttrs = [
                'user_id' => $user->id,
                'code' => 'CAP-'.strtoupper(Str::random(6)),
                'name' => $data['organization'],
                'type' => 'institution',
                'contact_person' => $data['contact_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'address' => $data['address'] ?? null,
                'credit_limit' => 0,
                'available_balance' => 0,
                'risk_preference' => 'medium',
                'status' => 'pending',
            ];

            // Only set metadata if the column exists.
            if (\Schema::hasColumn('lenders', 'metadata')) {
                $lenderAttrs['metadata'] = [
                    'org_type' => $data['org_type'],
                    'contact_role' => $data['contact_role'] ?? null,
                    'country' => $data['country'],
                    'commitment_band' => $data['commitment_band'],
                    'notes' => $data['notes'] ?? null,
                    'channel' => 'capital_partner_signup',
                ];
            }

            Lender::create($lenderAttrs);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('site.investor.dashboard')
            ->with('status', 'Your capital partner application has been received. A relationship manager will be in touch within 24 hours.');
    }
}
