<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ConsoleSecondFactorService;
use App\Services\PinRecoveryChallengeService;
use App\Services\WebTwoFactorAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConsoleSecureAccessController extends Controller
{
    public function choose(
        Request $request,
        WebTwoFactorAuthService $twoFactor,
        ConsoleSecondFactorService $secondFactor,
    ): View|RedirectResponse {
        $context = (string) $request->query('context', 'staff');
        $user = $this->pendingUser($request, $twoFactor);
        if (! $user) {
            return $this->loginRedirect($context);
        }

        if (! $secondFactor->mustEnroll($user, $context)) {
            return redirect()->to($secondFactor->challengeRedirect($user, $context));
        }

        $methods = $secondFactor->allowedMethodsFor($user, $context);
        if (count($methods) <= 1) {
            return redirect()->to($secondFactor->setupRedirect($user, $context));
        }

        return view('auth.secure-choose-method', [
            'context' => $context,
            'methods' => $methods,
        ]);
    }

    public function questionsSetup(
        Request $request,
        WebTwoFactorAuthService $twoFactor,
        ConsoleSecondFactorService $secondFactor,
        PinRecoveryChallengeService $kba,
    ): View|RedirectResponse {
        $context = (string) $request->query('context', 'staff');
        $user = $this->pendingUser($request, $twoFactor);
        if (! $user) {
            return $this->loginRedirect($context);
        }

        $allowed = $secondFactor->allowedMethodsFor($user, $context);
        if (! in_array(ConsoleSecondFactorService::METHOD_SECURITY_QUESTIONS, $allowed, true)) {
            return redirect()->route('auth.two-factor.setup', ['context' => $context]);
        }

        $keys = old('question_keys', $kba->pickRandomKeys(3));
        if (! is_array($keys) || count($keys) !== 3) {
            $keys = $kba->pickRandomKeys(3);
        }

        return view('auth.secure-questions-setup', [
            'context' => $context,
            'questions' => $kba->questionsForKeys($keys),
            'questionKeys' => $keys,
            'bank' => $kba->bank(),
        ]);
    }

    public function storeQuestionsSetup(
        Request $request,
        WebTwoFactorAuthService $twoFactor,
        ConsoleSecondFactorService $secondFactor,
        PinRecoveryChallengeService $kba,
    ): RedirectResponse {
        $data = $request->validate([
            'context' => ['nullable', 'string'],
            'question_keys' => ['required', 'array', 'size:3'],
            'question_keys.*' => ['required', 'string'],
            'answer_values' => ['required', 'array', 'size:3'],
            'answer_values.*' => ['required', 'string', 'max:120'],
        ]);

        $context = (string) ($data['context'] ?? 'staff');
        $user = $this->pendingUser($request, $twoFactor);
        if (! $user) {
            return $this->loginRedirect($context)->withErrors(['answer_values' => 'Session expired. Sign in again.']);
        }

        $allowed = $secondFactor->allowedMethodsFor($user, $context);
        if (! in_array(ConsoleSecondFactorService::METHOD_SECURITY_QUESTIONS, $allowed, true)) {
            return redirect()->route('auth.two-factor.setup', ['context' => $context]);
        }

        $keys = array_values($data['question_keys']);
        if (count(array_unique($keys)) !== 3) {
            return back()->withErrors(['question_keys' => 'Choose three different questions.'])->withInput();
        }

        $answers = [];
        foreach ($keys as $i => $key) {
            $answers[$key] = (string) ($data['answer_values'][$i] ?? '');
        }

        $kba->enrollSelected($user, $keys, $answers);

        $pending = $twoFactor->pendingLogin($request);
        if ($pending) {
            $response = $twoFactor->completePendingLogin($request);

            return ($response ?? redirect()->route('staff.dashboard'))
                ->with('status', 'Security questions saved.');
        }

        $twoFactor->markSessionVerified($request);

        return redirect()->route('staff.dashboard')->with('status', 'Security questions saved.');
    }

    public function questionsChallenge(
        Request $request,
        WebTwoFactorAuthService $twoFactor,
        ConsoleSecondFactorService $secondFactor,
    ): View|RedirectResponse {
        $context = (string) $request->query('context', 'staff');
        $user = $this->pendingUser($request, $twoFactor);
        if (! $user) {
            return $this->loginRedirect($context);
        }

        if ($secondFactor->challengeMethod($user, $context) !== ConsoleSecondFactorService::METHOD_SECURITY_QUESTIONS) {
            return redirect()->to($secondFactor->challengeRedirect($user, $context));
        }

        // Keep the same question for this browser attempt if a token is already in session.
        $existingToken = $request->session()->get('staff_kba_challenge_token');
        $challenge = null;
        if (is_string($existingToken) && $existingToken !== '') {
            $payload = \Illuminate\Support\Facades\Cache::get(ConsoleSecondFactorService::STAFF_KBA_CACHE.$existingToken);
            if (is_array($payload) && (int) ($payload['user_id'] ?? 0) === $user->id) {
                $key = (string) ($payload['question_key'] ?? '');
                $question = collect(app(PinRecoveryChallengeService::class)->enrolledQuestions($user))
                    ->firstWhere('key', $key);
                if ($question) {
                    $challenge = ['token' => $existingToken, 'question' => $question];
                }
            }
        }

        if ($challenge === null) {
            $challenge = $secondFactor->beginSecurityQuestionChallenge($user);
            $request->session()->put('staff_kba_challenge_token', $challenge['token']);
        }

        return view('auth.secure-questions-challenge', [
            'context' => $context,
            'token' => $challenge['token'],
            'question' => $challenge['question'],
        ]);
    }

    public function verifyQuestionsChallenge(
        Request $request,
        WebTwoFactorAuthService $twoFactor,
        ConsoleSecondFactorService $secondFactor,
    ): RedirectResponse {
        $data = $request->validate([
            'context' => ['nullable', 'string'],
            'token' => ['required', 'string'],
            'answer' => ['required', 'string', 'max:120'],
        ]);

        $context = (string) ($data['context'] ?? 'staff');
        $user = $this->pendingUser($request, $twoFactor);
        if (! $user) {
            return $this->loginRedirect($context)->withErrors(['answer' => 'Session expired. Sign in again.']);
        }

        $result = $secondFactor->verifySecurityQuestionChallenge($data['token'], $data['answer'], $request);
        if (! ($result['ok'] ?? false)) {
            $message = match ($result['reason'] ?? '') {
                'locked' => 'Too many attempts. Sign in again later.',
                'expired' => 'This verification expired. Sign in again.',
                default => 'Verification failed. Try again.',
            };

            return back()->withErrors(['answer' => $message]);
        }

        $request->session()->forget('staff_kba_challenge_token');

        $pending = $twoFactor->pendingLogin($request);
        if ($pending) {
            $response = $twoFactor->completePendingLogin($request);

            return $response ?? redirect()->route('staff.dashboard');
        }

        $twoFactor->markSessionVerified($request);

        return redirect()->route('staff.dashboard');
    }

    protected function pendingUser(Request $request, WebTwoFactorAuthService $twoFactor): ?User
    {
        $pending = $twoFactor->pendingLogin($request);
        if ($pending) {
            return User::query()->find($pending['user_id'] ?? null);
        }

        return $request->user('admin') ?? $request->user();
    }

    protected function loginRedirect(string $context): RedirectResponse
    {
        return redirect()->route($context === 'admin' ? 'admin.login' : 'staff.login');
    }
}
