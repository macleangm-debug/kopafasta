<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\SupportConversation;
use App\Services\Support\SupportAutomationService;
use App\Services\Support\SupportGuestService;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Deterministic Help-driven support automation (public / borrower / partner).
 */
class SupportAutomationController extends Controller
{
    public function __construct(
        private readonly SupportAutomationService $automation,
    ) {}

    public function publicStep(Request $request): JsonResponse
    {
        return $this->handle($request, audience: 'member', requireAuth: false);
    }

    public function borrowerStep(Request $request): JsonResponse
    {
        return $this->handle($request, audience: 'member', requireAuth: true, portal: 'borrower');
    }

    public function partnerStep(Request $request): JsonResponse
    {
        return $this->handle($request, audience: 'partner', requireAuth: true, portal: 'partner');
    }

    private function handle(Request $request, string $audience, bool $requireAuth, ?string $portal = null): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'string', 'max:40'],
            'key' => ['nullable', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120'],
            'conversation_id' => ['nullable', 'integer'],
            'guest_first_name' => ['nullable', 'string', 'max:60'],
            'guest_last_name' => ['nullable', 'string', 'max:60'],
            'guest_name' => ['nullable', 'string', 'max:120'],
            'guest_phone' => ['nullable', 'string', 'max:32'],
        ]);

        $action = (string) $data['action'];
        $customer = null;
        $user = $request->user();
        $workspace = null;

        if ($requireAuth) {
            if ($portal === 'borrower') {
                $customer = $request->attributes->get('customer')
                    ?? $user?->customer
                    ?? null;
                if (! $customer) {
                    return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
                }
            } elseif ($portal === 'partner') {
                if (! $user) {
                    return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
                }
                $workspace = (string) ($request->session()->get('partner_workspace')
                    ?? $request->attributes->get('partner_workspace')
                    ?? '');
                if ($workspace === '') {
                    $workspace = null;
                }
            }
        }

        $guestFirst = trim((string) ($data['guest_first_name'] ?? ''));
        $guestLast = trim((string) ($data['guest_last_name'] ?? ''));
        $guestName = trim((string) ($data['guest_name'] ?? '')) ?: trim($guestFirst.' '.$guestLast);
        $guestPhone = null;
        if (! $customer && ! $requireAuth) {
            $guestPhone = PhoneNumber::fromRequest($request, 'guest_phone', (string) session('country', 'TZ'))
                ?: (isset($data['guest_phone'])
                    ? PhoneNumber::normalizeForCountry((string) $data['guest_phone'], (string) session('country', 'TZ'))
                    : null);
            if ($guestPhone) {
                if ($request->hasSession()) {
                    $request->session()->put('support_guest_phone', $guestPhone);
                }
            }
            if ($guestFirst !== '' && $request->hasSession()) {
                $request->session()->put('support_guest_first_name', $guestFirst);
            }
            if ($guestLast !== '' && $request->hasSession()) {
                $request->session()->put('support_guest_last_name', $guestLast);
            }
            if ($guestName !== '' && $request->hasSession()) {
                $request->session()->put('support_guest_name', $guestName);
            }
            if ($guestFirst && $guestLast && $guestPhone) {
                app(SupportGuestService::class)->touchGuest(
                    $guestFirst,
                    $guestLast,
                    $guestPhone,
                    SupportGuestService::SOURCE_GUEST_CHAT,
                );
            }
            // Fall back to session identity for escalate.
            if ($guestName === '' && $request->hasSession()) {
                $guestName = trim((string) $request->session()->get('support_guest_name', ''));
            }
            if (! $guestPhone && $request->hasSession()) {
                $guestPhone = trim((string) $request->session()->get('support_guest_phone', '')) ?: null;
            }
        }

        try {
            if ($action === 'start') {
                if (! $customer && ! $requireAuth) {
                    if ($guestFirst === '' || $guestLast === '' || ! $guestPhone) {
                        return response()->json([
                            'ok' => false,
                            'message' => str_starts_with(app()->getLocale(), 'sw')
                                ? 'Andika jina la kwanza, jina la mwisho na namba ya simu kabla ya kuanza.'
                                : 'Enter first name, last name and phone before starting.',
                            'needs_guest' => true,
                        ], 422);
                    }
                }
                $payload = $this->automation->start(
                    $customer,
                    $requireAuth ? $user : null,
                    $audience,
                    $guestName !== '' ? $guestName : null,
                    $guestPhone,
                    $workspace,
                    app()->getLocale(),
                    $guestFirst !== '' ? $guestFirst : null,
                );
                if (! empty($payload['conversation_id']) && $request->hasSession()) {
                    $request->session()->put('support_automation_conversation_id', (int) $payload['conversation_id']);
                }

                return response()->json($payload);
            }

            $conversation = $this->resolveConversation($request, (int) ($data['conversation_id'] ?? 0), $customer, $user, $guestPhone, $requireAuth);
            if (! $conversation) {
                return response()->json(['ok' => false, 'message' => 'Conversation not found.'], 404);
            }

            if (in_array((string) $conversation->status, [
                \App\Services\Support\SupportConversationService::STATUS_CLOSED,
                \App\Services\Support\SupportConversationService::STATUS_RESOLVED,
            ], true)
                || in_array((string) $conversation->handling_state, [
                    SupportAutomationService::STATE_RESOLVED_AUTOMATED,
                    SupportAutomationService::STATE_RESOLVED_SUPPORT,
                ], true)
            ) {
                return response()->json([
                    'ok' => false,
                    'message' => str_starts_with(app()->getLocale(), 'sw')
                        ? 'Mazungumzo haya yametatuliwa. Anza mazungumzo mapya kwa msaada mpya.'
                        : 'This conversation is resolved. Start a new conversation for new help.',
                    'composer_locked' => true,
                ], 422);
            }

            $input = [
                'key' => $data['key'] ?? $data['slug'] ?? null,
                'slug' => $data['slug'] ?? $data['key'] ?? null,
                'guest_name' => $guestName,
                'guest_phone' => $guestPhone,
                'guest_first_name' => $guestFirst,
                'guest_last_name' => $guestLast,
            ];

            $payload = $this->automation->step(
                $conversation,
                $action,
                $input,
                $customer,
                $requireAuth ? $user : null,
                app()->getLocale(),
            );

            // Resolved Guest thread is final — clear session so next visit starts a new conversation.
            if (
                ! $requireAuth
                && $request->hasSession()
                && in_array((string) ($payload['handling_state'] ?? ''), [
                    SupportAutomationService::STATE_RESOLVED_AUTOMATED,
                    SupportAutomationService::STATE_RESOLVED_SUPPORT,
                ], true)
            ) {
                $request->session()->forget([
                    'support_guest_first_name',
                    'support_guest_last_name',
                    'support_guest_name',
                    'support_guest_phone',
                    'support_automation_conversation_id',
                ]);
            }

            return response()->json($payload);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    private function resolveConversation(
        Request $request,
        int $id,
        $customer,
        $user,
        ?string $guestPhone,
        bool $requireAuth,
    ): ?SupportConversation {
        if ($id < 1) {
            return null;
        }

        $conversation = SupportConversation::query()->find($id);
        if (! $conversation) {
            return null;
        }

        if ($customer && (int) $conversation->customer_id === (int) $customer->id) {
            return $conversation;
        }

        if ($requireAuth && $user && (int) $conversation->user_id === (int) $user->id && ! $conversation->customer_id) {
            return $conversation;
        }

        if (! $requireAuth && $guestPhone && $conversation->guest_phone === $guestPhone && ! $conversation->customer_id) {
            return $conversation;
        }

        // Public anonymous start may not have phone yet — allow session-bound id only once.
        if (! $requireAuth && ! $conversation->customer_id && ! $conversation->user_id) {
            if (! $request->hasSession()) {
                return $conversation;
            }
            $sessionId = (int) $request->session()->get('support_automation_conversation_id', 0);
            if ($sessionId === (int) $conversation->id || $sessionId === 0) {
                $request->session()->put('support_automation_conversation_id', $conversation->id);

                return $conversation;
            }
        }

        return null;
    }
}
