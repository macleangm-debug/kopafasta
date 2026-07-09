<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\ProfileSectionDefinition;
use Illuminate\Support\Collection;

class ProfileSectionBuilderService
{
    /** @return Collection<int, ProfileSectionDefinition> */
    public function activeSections(): Collection
    {
        return ProfileSectionDefinition::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Whether a mapped profile section must be complete before loan submission.
     * Falls back to true for payment when no admin definition exists.
     */
    public function requiredBeforeLoan(string $mapsTo, bool $default = false): bool
    {
        $definition = ProfileSectionDefinition::query()
            ->where('is_active', true)
            ->get()
            ->first(function (ProfileSectionDefinition $section) use ($mapsTo) {
                $mapped = (string) ($section->metadata['maps_to'] ?? $section->key);

                return $mapped === $mapsTo || $section->key === $mapsTo;
            });

        if (! $definition) {
            return $default;
        }

        return (bool) ($definition->required_before_loan || $definition->is_required);
    }

    public function paymentRequiredBeforeLoan(): bool
    {
        return $this->requiredBeforeLoan('payment', true);
    }

    /** @return list<array<string, mixed>> */
    public function hubCards(Customer $customer): array
    {
        $definitions = $this->activeSections();

        if ($definitions->isEmpty()) {
            return $this->defaultHubCards($customer);
        }

        $completion = app(ProfileCompletionService::class);
        $tabStatuses = $completion->tabStatuses($customer);
        $revision = app(ProfileRevisionService::class);

        return $definitions->map(function (ProfileSectionDefinition $section) use ($customer, $tabStatuses, $revision) {
            $mappedKey = (string) ($section->metadata['maps_to'] ?? $section->key);
            $tab = $tabStatuses[$mappedKey] ?? null;
            $status = $this->resolveStatus($customer, $mappedKey, $tab, $revision);

            return [
                'key'         => $section->key,
                'icon'        => $section->icon ?: '📋',
                'label'       => $section->localizedName(),
                'description' => $section->localizedDescription(),
                'status'      => $status,
                'status_label'=> $this->statusLabel($status),
                'action_label'=> $this->actionLabel($status),
                'url'         => $tab['url'] ?? route('site.borrower.profile', ['section' => $mappedKey]),
                'required'    => (bool) $section->is_required,
            ];
        })->all();
    }

    /** @return list<array<string, mixed>> */
    private function defaultHubCards(Customer $customer): array
    {
        $completion = app(ProfileCompletionService::class);
        $tabStatuses = $completion->extendedTabStatuses($customer);

        $meta = [
            'personal'  => ['icon' => '👤', 'action' => 'view_edit'],
            'activity'  => ['icon' => '💼', 'action' => 'view_edit'],
            'residence' => ['icon' => '🏠', 'action' => 'view_edit'],
            'kyc'       => ['icon' => '📄', 'action' => 'upload'],
            'payment'   => ['icon' => '💳', 'action' => 'add'],
            'kin'       => ['icon' => '👥', 'action' => 'edit'],
            'assets'    => ['icon' => '🚗', 'action' => 'manage'],
            'face'      => ['icon' => '📸', 'action' => 'start'],
        ];

        return collect($tabStatuses)->map(function (array $tab, string $key) use ($meta) {
            $status = (string) ($tab['status'] ?? 'not_started');
            $sectionMeta = $meta[$key] ?? ['icon' => '📋', 'action' => 'view_edit'];

            return [
                'key'          => $key,
                'icon'         => $sectionMeta['icon'],
                'label'        => $tab['label'],
                'description'  => $tab['description'] ?? null,
                'status'       => $status,
                'status_label' => $this->statusLabel($status),
                'action_label' => $this->actionLabel($status, $sectionMeta['action']),
                'url'          => $tab['url'],
                'required'     => (bool) ($tab['required'] ?? false),
                'count'        => $tab['count'] ?? null,
            ];
        })->values()->all();
    }

    private function resolveStatus(Customer $customer, string $key, ?array $tab, ProfileRevisionService $revision): string
    {
        if ($tab && isset($tab['status'])) {
            return (string) $tab['status'];
        }

        if ($revision->hasOpenRevision($customer, $key)) {
            return 'needs_work';
        }

        if ($tab && ($tab['complete'] ?? false)) {
            return 'complete';
        }

        return 'not_started';
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'complete'     => __('borrower.profile.status.complete'),
            'in_progress'  => __('borrower.profile.status.in_progress'),
            'needs_work'   => __('borrower.profile.status.needs_work'),
            'under_review' => __('borrower.profile.status.under_review'),
            'rejected'     => __('borrower.profile.status.rejected'),
            'pending'      => __('borrower.profile.status.pending'),
            default        => __('borrower.profile.status.not_started'),
        };
    }

    private function actionLabel(string $status, string $fallback = 'view_edit'): string
    {
        if ($status === 'complete') {
            return __('borrower.profile.hub.view_edit');
        }

        return match ($fallback) {
            'upload' => __('borrower.profile.hub.upload'),
            'add'    => __('borrower.profile.hub.add_account'),
            'edit'   => __('borrower.profile.hub.edit'),
            'manage' => __('borrower.profile.hub.manage'),
            'start'  => __('borrower.profile.hub.start_verification'),
            default  => __('borrower.profile.hub.complete_section'),
        };
    }
}
