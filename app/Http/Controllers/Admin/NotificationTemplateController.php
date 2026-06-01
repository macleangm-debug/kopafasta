<?php

namespace App\Http\Controllers\Admin;

use App\Models\NotificationTemplate;
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
            'name'    => ['required', 'string', 'max:150'],
            'code'    => ['required', 'string', 'max:50'],
            'channel' => ['required', 'in:sms,email,push,in_app,whatsapp'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body'    => ['required', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function formData(?Model $record = null): array
    {
        return [
            'channels' => ['sms'=>'SMS','email'=>'Email','push'=>'Push','in_app'=>'In-app','whatsapp'=>'WhatsApp'],
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        return $data;
    }
}
