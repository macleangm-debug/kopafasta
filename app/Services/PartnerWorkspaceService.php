<?php

namespace App\Services;

use App\Models\Partner;
use Illuminate\Support\Facades\Session;

/**
 * One Partner identity may hold multiple operational workspaces (roles).
 * Switching changes portal navigation — not the authenticated identity.
 */
class PartnerWorkspaceService
{
    public const SESSION_KEY = 'partner_active_workspace';

    /**
     * @return list<array{key: string, label: string, home_route: string}>
     */
    public function workspaces(Partner $partner): array
    {
        $roles = $partner->partnerRoles();
        if ($roles === [] && filled($partner->category)) {
            $roles = [(string) $partner->category];
        }

        $out = [];
        $seen = [];

        foreach ($roles as $role) {
            $key = $this->workspaceKeyForRole((string) $role);
            if ($key === null || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = [
                'key' => $key,
                'label' => $this->labelForRole((string) $role),
                'home_route' => $this->homeRouteForWorkspace($key),
            ];
        }

        return $out;
    }

    public function canSwitch(Partner $partner): bool
    {
        return count($this->workspaces($partner)) >= 2;
    }

    public function currentKey(Partner $partner): string
    {
        $workspaces = $this->workspaces($partner);
        if ($workspaces === []) {
            return 'service';
        }

        $keys = array_column($workspaces, 'key');
        $stored = Session::get(self::SESSION_KEY);
        if (is_string($stored) && in_array($stored, $keys, true)) {
            return $stored;
        }

        // Prefer primary category shell when present among available workspaces.
        $primary = $this->workspaceKeyForRole((string) ($partner->category ?: ''));
        if ($primary && in_array($primary, $keys, true)) {
            return $primary;
        }

        return $keys[0];
    }

    public function homeUrl(Partner $partner): string
    {
        $key = $this->currentKey($partner);
        foreach ($this->workspaces($partner) as $row) {
            if ($row['key'] === $key) {
                return route($row['home_route']);
            }
        }

        return route('site.partner.dashboard');
    }

    public function switchTo(Partner $partner, string $workspace): string
    {
        $allowed = collect($this->workspaces($partner))->firstWhere('key', $workspace);
        if (! $allowed) {
            return $this->homeUrl($partner);
        }

        Session::put(self::SESSION_KEY, $workspace);

        return route($allowed['home_route']);
    }

    private function workspaceKeyForRole(string $role): ?string
    {
        return match ($role) {
            'affiliate' => 'affiliate',
            'supplier' => 'supplier',
            'capital' => 'capital',
            'insurance', 'valuer', 'gps_installer', 'debt_collector', 'call_center',
            'legal_partner', 'auctioneer', 'towing', 'yard' => 'service',
            default => filled($role) ? 'service' : null,
        };
    }

    private function labelForRole(string $role): string
    {
        return match ($role) {
            'affiliate' => __('site.partner_workspace.affiliate'),
            'supplier' => __('site.partner_workspace.supplier'),
            'capital' => __('site.partner_workspace.capital'),
            'insurance' => __('site.partner_workspace.insurance'),
            'valuer' => __('site.partner_workspace.valuer'),
            'debt_collector' => __('site.partner_workspace.collection'),
            default => __('site.partner_workspace.service'),
        };
    }

    private function labelForWorkspace(string $key): string
    {
        return match ($key) {
            'affiliate' => __('site.partner_workspace.affiliate'),
            'supplier' => __('site.partner_workspace.supplier'),
            'capital' => __('site.partner_workspace.capital'),
            default => __('site.partner_workspace.service'),
        };
    }

    private function homeRouteForWorkspace(string $key): string
    {
        return match ($key) {
            'affiliate' => 'site.affiliate.dashboard',
            'supplier' => 'site.supplier.dashboard',
            'capital' => 'site.investor.dashboard',
            default => 'site.partner.dashboard',
        };
    }
}
