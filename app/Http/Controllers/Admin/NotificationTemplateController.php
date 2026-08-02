<?php

namespace App\Http\Controllers\Admin;

use App\Models\NotificationTemplate;
use App\Services\Messaging\TemplatePersonalization;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplateController extends ResourceController
{
    protected string $model = NotificationTemplate::class;

    protected string $routePrefix = 'admin.notification-templates';

    protected string $viewFolder = 'notification-templates';

    protected string $singular = 'notification template';

    protected function rules(?Model $model = null): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:80'],
            'locale' => ['required', 'in:en,sw'],
            'channel' => ['required', 'in:sms,email,push,in_app,whatsapp'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'is_active' => ['nullable', 'boolean'],
            // Uniqueness of code+locale enforced below after transform if needed
        ];
    }

    protected function formData(?Model $record = null): array
    {
        return [
            'channels' => [
                'sms' => 'SMS',
                'email' => 'Email',
                'push' => 'Push',
                'in_app' => 'In-app',
                'whatsapp' => 'WhatsApp',
            ],
            'personalization' => TemplatePersonalization::grouped(),
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['locale'] = $data['locale'] ?? 'en';
        $data['code'] = strtolower(trim((string) $data['code']));

        $exists = NotificationTemplate::query()
            ->where('code', $data['code'])
            ->where('locale', $data['locale'])
            ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
            ->exists();

        if ($exists) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'code' => 'A template with this code already exists for the selected language.',
            ]);
        }

        return $data;
    }
}
