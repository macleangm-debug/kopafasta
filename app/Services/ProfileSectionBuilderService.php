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
        // Hub is always the seven main categories. Admin ProfileSectionDefinition rows
        // drive required_before_loan / field config inside sections — never hub layout.
        return $this->defaultHubCards($customer);
    }

    /** @return list<array<string, mixed>> */
    private function defaultHubCards(Customer $customer): array
    {
        $completion = app(ProfileCompletionService::class);
        $tabStatuses = $completion->extendedTabStatuses($customer);

        $meta = [
            'personal'  => ['icon' => '👤', 'action' => 'add_section'],
            'activity'  => ['icon' => '💼', 'action' => 'add_section'],
            'residence' => ['icon' => '🏠', 'action' => 'add_section'],
            'payment'   => ['icon' => '💳', 'action' => 'add'],
            'assets'    => ['icon' => '🚗', 'action' => 'manage'],
        ];

        return collect($tabStatuses)
            ->only(array_keys($meta))
            ->map(function (array $tab, string $key) use ($meta, $customer) {
                $status = (string) ($tab['status'] ?? 'not_started');
                $sectionMeta = $meta[$key] ?? ['icon' => '📋', 'action' => 'view_edit'];

                return [
                    'key'          => $key,
                    'icon'         => $sectionMeta['icon'],
                    'label'        => $tab['label'],
                    'description'  => $key === 'personal'
                        ? $this->personalGapSummary($customer)
                        : null,
                    'missing'      => $key === 'personal'
                        ? app(ProfileValidationService::class)->personalGaps($customer)
                        : [],
                    'status'       => $status,
                    'status_label' => $this->statusLabel($status),
                    'action_label' => $this->actionLabel($status, $sectionMeta['action']),
                    'url'          => $tab['url'],
                    'required'     => (bool) ($tab['required'] ?? false),
                    'count'        => $tab['count'] ?? null,
                ];
            })->values()->all();
    }

    private function personalGapSummary(Customer $customer): ?string
    {
        $gaps = app(ProfileValidationService::class)->personalGaps($customer);
        if ($gaps === []) {
            return null;
        }

        return __('borrower.profile.gaps.summary', [
            'items' => collect($gaps)->pluck('label')->take(3)->implode(', '),
        ]);
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
            'optional'     => __('borrower.profile.status.optional'),
            default        => __('borrower.profile.status.not_started'),
        };
    }

    private function actionLabel(string $status, string $fallback = 'view_edit'): string
    {
        if ($status === 'complete') {
            return __('borrower.profile.hub.view_edit');
        }
        if ($status === 'optional') {
            return __('borrower.profile.hub.add_optional');
        }

        return match ($fallback) {
            'upload'      => __('borrower.profile.hub.upload'),
            'add'         => __('borrower.profile.hub.add_account'),
            'add_section' => __('borrower.profile.hub.add'),
            'manage'      => __('borrower.profile.hub.manage'),
            'start'       => __('borrower.profile.hub.start_verification'),
            default       => __('borrower.profile.hub.add'),
        };
    }
}
