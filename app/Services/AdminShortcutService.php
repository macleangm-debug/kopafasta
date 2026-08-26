<?php

namespace App\Services;

use App\Models\User;

class AdminShortcutService
{
    public const MAX = 6;

    public function __construct(
        private readonly ConsoleNavService $nav,
        private readonly PermissionService $permissions,
    ) {}

    /**
     * @return list<array{route: string, label: string, url: string, query: array<string, mixed>}>
     */
    public function list(User $user): array
    {
        $saved = $user->preferences['admin_shortcuts'] ?? [];
        if (! is_array($saved)) {
            return [];
        }

        $allowed = $this->allowedMap($user);
        $out = [];
        foreach ($saved as $item) {
            if (! is_array($item)) {
                continue;
            }
            $route = (string) ($item['route'] ?? '');
            if ($route === '' || ! isset($allowed[$route])) {
                continue;
            }
            if (isset($item['permission']) && is_string($item['permission']) && $item['permission'] !== ''
                && ! $this->permissions->has($user, $item['permission'])) {
                continue;
            }
            $query = is_array($item['query'] ?? null) ? $item['query'] : [];
            try {
                $url = route($route, $query);
            } catch (\Throwable) {
                continue;
            }
            $out[] = [
                'route' => $route,
                'label' => (string) ($item['label'] ?? $allowed[$route]['label']),
                'url' => $url,
                'query' => $query,
                'permission' => $allowed[$route]['permission'] ?? ($item['permission'] ?? null),
            ];
            if (count($out) >= self::MAX) {
                break;
            }
        }

        return $out;
    }

    /**
     * @return array{route: string, label: string, query: array<string, mixed>}|null
     */
    public function currentCandidate(User $user, ?string $route): ?array
    {
        if (! $route) {
            return null;
        }
        $allowed = $this->allowedMap($user);
        if (! isset($allowed[$route]) || $route === 'admin.dashboard') {
            return null;
        }

        return [
            'route' => $route,
            'label' => $allowed[$route]['label'],
            'query' => [],
        ];
    }

    public function isSaved(User $user, string $route): bool
    {
        foreach ($this->list($user) as $item) {
            if ($item['route'] === $route) {
                return true;
            }
        }

        return false;
    }

    public function add(User $user, string $route, string $label, array $query = []): void
    {
        $allowed = $this->allowedMap($user);
        abort_unless(isset($allowed[$route]), 403);
        $items = $user->preferences['admin_shortcuts'] ?? [];
        if (! is_array($items)) {
            $items = [];
        }
        $items = array_values(array_filter($items, fn ($item) => ($item['route'] ?? '') !== $route));
        if (count($items) >= self::MAX) {
            abort(422, 'You can pin at most '.self::MAX.' shortcuts.');
        }
        $items[] = [
            'route' => $route,
            'label' => $label !== '' ? $label : $allowed[$route]['label'],
            'query' => $query,
            'permission' => $allowed[$route]['permission'],
        ];
        $this->save($user, $items);
    }

    public function remove(User $user, string $route): void
    {
        $items = $user->preferences['admin_shortcuts'] ?? [];
        if (! is_array($items)) {
            return;
        }
        $items = array_values(array_filter($items, fn ($item) => ($item['route'] ?? '') !== $route));
        $this->save($user, $items);
    }

    /** @param  list<string>  $routes */
    public function reorder(User $user, array $routes): void
    {
        $current = collect($this->list($user))->keyBy('route');
        $items = [];
        foreach ($routes as $route) {
            $route = (string) $route;
            if (! $current->has($route)) {
                continue;
            }
            $row = $current->get($route);
            $items[] = [
                'route' => $row['route'],
                'label' => $row['label'],
                'query' => $row['query'],
                'permission' => $row['permission'] ?? null,
            ];
        }
        $this->save($user, array_slice($items, 0, self::MAX));
    }

    /**
     * @return array<string, array{label: string, permission: ?string}>
     */
    private function allowedMap(User $user): array
    {
        $map = [];
        foreach ($this->nav->visibleSections($user) as $section) {
            foreach ($section['items'] as $item) {
                $route = (string) ($item[1] ?? '');
                if ($route === '' || $route === '__group__') {
                    continue;
                }
                $map[$route] = [
                    'label' => (string) $item[0],
                    'permission' => is_string($item[2] ?? null) ? $item[2] : null,
                ];
            }
        }

        return $map;
    }

    /** @param  list<array<string, mixed>>  $items */
    private function save(User $user, array $items): void
    {
        $prefs = $user->preferences ?? [];
        $prefs['admin_shortcuts'] = array_values($items);
        $user->preferences = $prefs;
        $user->save();
    }
}
