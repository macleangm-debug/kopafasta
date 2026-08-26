<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\Promotion;
use App\Services\ChatbotContentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class CommunicationsController extends Controller
{
    public function index(): View
    {
        $today = [now()->startOfDay(), now()->endOfDay()];
        $sent = 0;
        $delivered = 0;
        $failed = 0;
        $scheduled = 0;

        if (Schema::hasTable('notification_logs') && Schema::hasColumn('notification_logs', 'status')) {
            $sent = NotificationLog::query()
                ->whereBetween('created_at', $today)
                ->whereIn('status', ['sent', 'delivered', 'queued'])
                ->count();
            $delivered = NotificationLog::query()
                ->whereBetween('created_at', $today)
                ->whereIn('status', ['sent', 'delivered'])
                ->count();
            $failed = NotificationLog::query()
                ->whereBetween('created_at', $today)
                ->where(function ($query) {
                    $query->whereIn('status', ['failed', 'skipped']);
                    if (Schema::hasColumn('notification_logs', 'message')) {
                        $query->orWhere('message', 'like', '[skipped]%');
                    }
                })
                ->count();
        }

        $scheduled = Promotion::query()
            ->where('status', 'draft')
            ->get()
            ->filter(fn (Promotion $promo) => ($promo->metadata['send_mode'] ?? '') === 'schedule')
            ->count();

        $missingTranslation = 0;
        $translationCodes = [];
        if (Schema::hasTable('notification_templates')) {
            $byCode = NotificationTemplate::query()
                ->get(['code', 'locale', 'body'])
                ->groupBy('code');
            foreach ($byCode as $code => $rows) {
                $en = $rows->firstWhere('locale', 'en');
                $sw = $rows->firstWhere('locale', 'sw');
                if (blank($en?->body) || blank($sw?->body)) {
                    $missingTranslation++;
                    if (count($translationCodes) < 5) {
                        $translationCodes[] = (string) $code;
                    }
                }
            }
        }

        $failedRows = collect();
        if (Schema::hasTable('notification_logs') && Schema::hasColumn('notification_logs', 'status')) {
            $failedRows = NotificationLog::query()
                ->where(function ($query) {
                    $query->whereIn('status', ['failed', 'skipped']);
                    if (Schema::hasColumn('notification_logs', 'message')) {
                        $query->orWhere('message', 'like', '[skipped]%');
                    }
                })
                ->latest('id')
                ->limit(5)
                ->get();
        }

        return view('admin.communications.index', [
            'stats' => [
                'sent' => $sent,
                'delivered' => $delivered,
                'failed' => $failed,
                'scheduled' => $scheduled,
            ],
            'templateCount' => Schema::hasTable('notification_templates')
                ? NotificationTemplate::query()->select('code')->distinct()->get()->count('code')
                : 0,
            'ticketCount' => class_exists(\App\Models\SupportTicket::class)
                ? \App\Models\SupportTicket::query()->count()
                : 0,
            'missingTranslation' => $missingTranslation,
            'translationCodes' => $translationCodes,
            'failedRows' => $failedRows,
            'scheduledCampaigns' => $scheduled,
        ]);
    }

    public function chatbot(ChatbotContentService $chatbot): View
    {
        $entries = collect($chatbot->entries())
            ->map(function (array $entry) {
                $keywords = $entry['keywords'] ?? [];
                $entry['keywords'] = is_array($keywords) ? implode(', ', $keywords) : (string) $keywords;

                return $entry;
            })
            ->values()
            ->all();

        return view('admin.communications.chatbot', [
            'entries' => $entries,
        ]);
    }

    public function saveChatbot(Request $request): RedirectResponse
    {
        return app(SettingsController::class)->saveChatbot($request);
    }
}
