<?php

namespace App\Livewire\Admin;

use App\Models\NotificationTemplate;
use App\Services\Messaging\MessagingCatalog;
use Livewire\Attributes\Url;
use Livewire\Component;

class NotificationTemplatesTable extends Component
{
    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'stage')]
    public string $lifecycle = '';

    public function setLifecycle(string $key): void
    {
        $this->lifecycle = $this->lifecycle === $key ? '' : $key;
    }

    public function render()
    {
        $all = NotificationTemplate::query()
            ->orderBy('code')
            ->orderBy('locale')
            ->get();

        $stageCounts = [];
        foreach (array_keys(MessagingCatalog::LIFECYCLES) as $key) {
            $stageCounts[$key] = $all
                ->filter(fn (NotificationTemplate $t) => MessagingCatalog::lifecycleForCode((string) $t->code) === $key)
                ->unique('code')
                ->count();
        }

        $templates = $all->when($this->search !== '', function ($collection) {
            $term = mb_strtolower($this->search);

            return $collection->filter(function (NotificationTemplate $t) use ($term) {
                $hay = mb_strtolower(implode(' ', [
                    $t->name,
                    $t->code,
                    (string) $t->subject,
                    (string) $t->body,
                ]));

                return str_contains($hay, $term);
            });
        });

        if ($this->lifecycle !== '') {
            $templates = $templates->filter(
                fn (NotificationTemplate $t) => MessagingCatalog::lifecycleForCode((string) $t->code) === $this->lifecycle
            );
        }

        $byCode = $templates->groupBy('code');

        $sections = [];
        foreach (MessagingCatalog::LIFECYCLES as $key => $meta) {
            $events = $byCode->filter(
                fn ($rows, $code) => MessagingCatalog::lifecycleForCode((string) $code) === $key
            );
            if ($events->isEmpty()) {
                continue;
            }
            $sections[$key] = [
                'label' => $meta['label'],
                'hint' => $meta['hint'],
                'events' => $events,
            ];
        }

        return view('livewire.admin.notification-templates-table', [
            'sections' => $sections,
            'stageCounts' => $stageCounts,
            'lifecycles' => MessagingCatalog::LIFECYCLES,
            'catalog' => MessagingCatalog::eventsByCode(),
            'totalEvents' => $byCode->count(),
        ]);
    }
}
