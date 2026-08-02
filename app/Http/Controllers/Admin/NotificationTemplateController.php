<?php

namespace App\Http\Controllers\Admin;

use App\Models\NotificationTemplate;
use App\Services\Messaging\MessagingCatalog;
use App\Services\Messaging\TemplatePersonalization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class NotificationTemplateController extends ResourceController
{
    protected string $model = NotificationTemplate::class;

    protected string $routePrefix = 'admin.notification-templates';

    protected string $viewFolder = 'notification-templates';

    protected string $singular = 'notification template';

    /** @return array<string, string> */
    protected function locales(): array
    {
        return config('notification_templates.locales', ['en' => 'English', 'sw' => 'Kiswahili']);
    }

    protected function rules(?Model $model = null): array
    {
        $localeKeys = array_keys($this->locales());

        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:80'],
            'channel' => ['required', 'in:sms,email,push,in_app,whatsapp,all'],
            'is_active' => ['nullable', 'boolean'],
            'translations' => ['required', 'array'],
            'translations.*.subject' => ['nullable', 'string', 'max:255'],
            'translations.*.body' => ['nullable', 'string'],
            'translations.*.locale' => ['required', Rule::in($localeKeys)],
        ];
    }

    protected function formData(?Model $record = null): array
    {
        $code = $record?->code ?? request('code');
        $byLocale = collect();
        if ($code) {
            $byLocale = NotificationTemplate::query()
                ->where('code', $code)
                ->get()
                ->keyBy(fn (NotificationTemplate $t) => $t->locale ?: 'en');
        }

        $translations = [];
        foreach ($this->locales() as $locale => $label) {
            $row = $byLocale->get($locale);
            $translations[$locale] = [
                'locale' => $locale,
                'label' => $label,
                'id' => $row?->id,
                'subject' => old("translations.$locale.subject", $row?->subject),
                'body' => old("translations.$locale.body", $row?->body),
            ];
        }

        return [
            'channels' => [
                'sms' => 'SMS',
                'email' => 'Email',
                'push' => 'Push',
                'in_app' => 'In-app',
                'whatsapp' => 'WhatsApp',
                'all' => 'All channels',
            ],
            'personalization' => TemplatePersonalization::grouped(),
            'lifecycles' => MessagingCatalog::LIFECYCLES,
            'eventsGrouped' => MessagingCatalog::eventsGroupedByLifecycle(),
            'locales' => $this->locales(),
            'translations' => $translations,
            'byLocale' => $byLocale,
        ];
    }

    public function store(Request $request)
    {
        $this->normalizeMoneyRequest($request);
        $data = $this->validateBilingual($request);
        $primary = $this->persistTranslations($data);

        return redirect()
            ->route("{$this->routePrefix}.edit", $primary)
            ->with('status', 'Notification template saved for all languages.');
    }

    public function update(Request $request, $id)
    {
        $record = $this->model::findOrFail($id);
        $this->normalizeMoneyRequest($request);
        $data = $this->validateBilingual($request, $record);
        $primary = $this->persistTranslations($data, $record);

        return redirect()
            ->route("{$this->routePrefix}.edit", $primary)
            ->with('status', 'Notification template updated for all languages.');
    }

    public function edit($id)
    {
        $record = $this->model::findOrFail($id);

        return view("admin.{$this->viewFolder}.edit", ['record' => $record] + $this->formData($record));
    }

    /**
     * @return array{name: string, code: string, channel: string, is_active: bool, translations: array<string, array{locale: string, subject: ?string, body: string}>}
     */
    protected function validateBilingual(Request $request, ?Model $existing = null): array
    {
        $localeKeys = array_keys($this->locales());
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:80'],
            'channel' => ['required', 'in:sms,email,push,in_app,whatsapp,all'],
            'is_active' => ['nullable', 'boolean'],
            'translations' => ['required', 'array'],
        ]);

        $code = strtolower(trim((string) $validated['code']));
        $translationsIn = (array) $request->input('translations', []);
        $translations = [];
        $filledBodies = 0;

        foreach ($localeKeys as $locale) {
            $row = (array) ($translationsIn[$locale] ?? []);
            $body = trim((string) ($row['body'] ?? ''));
            $subject = isset($row['subject']) ? trim((string) $row['subject']) : null;
            if ($subject === '') {
                $subject = null;
            }
            if ($body !== '') {
                $filledBodies++;
            }
            $translations[$locale] = [
                'locale' => $locale,
                'subject' => $subject,
                'body' => $body,
            ];
        }

        if ($filledBodies === 0) {
            throw ValidationException::withMessages([
                'translations' => 'Enter a message body for at least one language.',
            ]);
        }

        return [
            'name' => $validated['name'],
            'code' => $code,
            'channel' => $validated['channel'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'translations' => $translations,
        ];
    }

    /**
     * @param  array{name: string, code: string, channel: string, is_active: bool, translations: array<string, array{locale: string, subject: ?string, body: string}>}  $data
     */
    protected function persistTranslations(array $data, ?Model $anchor = null): NotificationTemplate
    {
        $primary = null;

        foreach ($data['translations'] as $locale => $row) {
            if ($row['body'] === '') {
                // Leave empty locales untouched (do not delete existing).
                if ($anchor && ($anchor->locale ?: 'en') === $locale) {
                    // If editing and clearing the current locale body, still require content above.
                }
                continue;
            }

            $template = NotificationTemplate::query()->updateOrCreate(
                ['code' => $data['code'], 'locale' => $locale],
                [
                    'name' => $data['name'],
                    'channel' => $data['channel'],
                    'subject' => $row['subject'],
                    'body' => $row['body'],
                    'is_active' => $data['is_active'],
                ]
            );

            if ($primary === null || $locale === 'en' || ($anchor && $anchor->id === $template->id)) {
                $primary = $template;
            }
        }

        return $primary ?? $anchor ?? NotificationTemplate::query()->where('code', $data['code'])->firstOrFail();
    }
}
