<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\SupportConversation;
use App\Services\Support\SupportConversationService;
use App\Services\Support\SupportHelpLibraryService;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportCenterController extends Controller
{
    public function index(SupportHelpLibraryService $help): View
    {
        $isSw = str_starts_with(app()->getLocale(), 'sw');
        $q = trim((string) request('q', ''));

        return view('site.support.index', [
            'isSw' => $isSw,
            'helpGroups' => $help->groups('member'),
            'helpCategories' => $help->categories('member'),
            'helpResults' => $q !== '' ? $help->search($q, 'member') : [],
            'helpQuery' => $q,
            'helpTopic' => (string) request('topic', ''),
            'helpArticle' => (string) request('article', ''),
            'phones' => support_phones(),
            'primaryPhone' => support_phones()[0] ?? null,
        ]);
    }

    /**
     * Public guest chat — reuses SupportConversationService (same Support Team queue).
     * No login required. Identity is name + phone only (not registration).
     */
    public function chat(Request $request, SupportConversationService $service): View
    {
        $isSw = str_starts_with(app()->getLocale(), 'sw');
        $guest = $this->guestIdentity($request);
        $conversation = null;

        if ($guest['phone'] !== '') {
            $conversation = SupportConversation::query()
                ->whereNull('customer_id')
                ->whereNull('user_id')
                ->where('guest_phone', $guest['phone'])
                ->whereNotIn('status', [
                    SupportConversationService::STATUS_CLOSED,
                    SupportConversationService::STATUS_RESOLVED,
                ])
                ->latest('id')
                ->first();

            if ($conversation) {
                $service->normalizeLegacyStatus($conversation);
                $conversation->load(['messages' => fn ($q) => $q->orderBy('id')]);
            }
        }

        return view('site.support.chat', [
            'isSw' => $isSw,
            'guestName' => $guest['name'],
            'guestPhone' => $guest['phone'],
            'conversation' => $conversation,
            'phones' => support_phones(),
            'primaryPhone' => support_phones()[0] ?? null,
        ]);
    }

    public function speak(Request $request, SupportConversationService $service): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'topic' => ['nullable', 'string', 'max:180'],
            'guest_name' => ['required', 'string', 'max:120'],
            'guest_phone' => ['required', 'string', 'max:32'],
        ]);

        $name = trim($data['guest_name']);
        $phone = PhoneNumber::fromRequest($request, 'guest_phone', (string) session('country', 'TZ'))
            ?: PhoneNumber::normalizeForCountry($data['guest_phone'], (string) session('country', 'TZ'));

        if ($name === '' || ! $phone || strlen(PhoneNumber::digits($phone)) < 9) {
            return response()->json([
                'ok' => false,
                'message' => str_starts_with(app()->getLocale(), 'sw')
                    ? 'Andika jina na namba ya simu ili kuendelea.'
                    : 'Enter your name and phone number to continue.',
            ], 422);
        }

        $this->rememberGuestIdentity($request, $name, $phone);

        $conversation = $service->requestHuman(
            null,
            null,
            trim($data['body']),
            $data['topic'] ?? 'Public guest chat',
            $name,
            $phone,
            'web_chat',
        );

        return response()->json(array_merge([
            'ok' => true,
            'conversation_id' => $conversation->id,
            'status' => $conversation->status,
            'ack' => $service->waitingAcknowledgement(),
            'messages' => $service->serializeMessages($conversation),
            'guest' => true,
        ], $service->memberChatPresence($conversation)));
    }

    public function thread(Request $request, SupportConversationService $service): JsonResponse
    {
        $guest = $this->guestIdentity($request);
        if ($guest['phone'] === '') {
            return response()->json(array_merge([
                'ok' => true,
                'conversation_id' => null,
                'messages' => [],
                'guest' => true,
            ], $service->memberChatPresence(null)));
        }

        $conversation = SupportConversation::query()
            ->whereNull('customer_id')
            ->whereNull('user_id')
            ->where('guest_phone', $guest['phone'])
            ->whereNotIn('status', [
                SupportConversationService::STATUS_CLOSED,
                SupportConversationService::STATUS_RESOLVED,
            ])
            ->latest('id')
            ->first();

        if (! $conversation) {
            return response()->json(array_merge([
                'ok' => true,
                'conversation_id' => null,
                'messages' => [],
                'guest' => true,
            ], $service->memberChatPresence(null)));
        }

        $service->normalizeLegacyStatus($conversation);

        return response()->json(array_merge([
            'ok' => true,
            'conversation_id' => $conversation->id,
            'status' => $conversation->status,
            'needs_human' => $conversation->needs_human,
            'messages' => $service->serializeMessages($conversation),
            'guest' => true,
        ], $service->memberChatPresence($conversation)));
    }

    /**
     * @return array{name: string, phone: string}
     */
    private function guestIdentity(Request $request): array
    {
        $name = trim((string) $request->session()->get('support_guest_name', ''));
        $phone = trim((string) $request->session()->get('support_guest_phone', ''));

        return [
            'name' => $name,
            'phone' => $phone,
        ];
    }

    private function rememberGuestIdentity(Request $request, string $name, string $phone): void
    {
        $request->session()->put('support_guest_name', $name);
        $request->session()->put('support_guest_phone', $phone);
    }
}
