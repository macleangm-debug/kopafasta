<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\PartnerPayment;
use App\Models\PartnerSettlement;
use App\Models\PartnerTask;
use App\Models\User;
use App\Models\ValuationAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PartnerDeletionService
{
    /**
     * Permanently delete empty partners; otherwise deactivate (status + linked user).
     *
     * @return array{action: 'deleted'|'deactivated', message: string}
     */
    public function remove(Partner $partner, ?User $actor = null): array
    {
        if ($this->hasOperationalHistory($partner)) {
            return $this->deactivate($partner, $actor);
        }

        return $this->hardDelete($partner, $actor);
    }

    /**
     * @return array{action: 'deactivated', message: string}
     */
    public function deactivate(Partner $partner, ?User $actor = null): array
    {
        DB::transaction(function () use ($partner) {
            $updates = ['status' => 'suspended'];
            if ($partner->isAffiliate() || filled($partner->affiliate_lifecycle_status)) {
                $updates['affiliate_lifecycle_status'] = AffiliateLifecycleService::TERMINATED;
            }

            $partner->update($updates);

            if ($partner->user_id) {
                User::query()->whereKey($partner->user_id)->update(['is_active' => false]);
            }
        });

        return [
            'action' => 'deactivated',
            'message' => 'Partner deactivated (history kept). Portal login disabled.',
        ];
    }

    /**
     * @return array{action: 'deleted', message: string}
     */
    public function hardDelete(Partner $partner, ?User $actor = null): array
    {
        if ($this->hasOperationalHistory($partner)) {
            throw ValidationException::withMessages([
                'partner' => 'This partner has tasks, payments, or assignments. Deactivate instead of deleting.',
            ]);
        }

        $userId = $partner->user_id;

        DB::transaction(function () use ($partner, $userId) {
            $partner->delete();

            if ($userId) {
                User::query()->whereKey($userId)->update(['is_active' => false]);
            }
        });

        return [
            'action' => 'deleted',
            'message' => 'Partner deleted.',
        ];
    }

    public function hasOperationalHistory(Partner $partner): bool
    {
        if (PartnerTask::query()->where('partner_id', $partner->id)->exists()) {
            return true;
        }

        if (PartnerPayment::query()->where('partner_id', $partner->id)->exists()) {
            return true;
        }

        if (PartnerSettlement::query()->where('partner_id', $partner->id)->exists()) {
            return true;
        }

        if (ValuationAssignment::query()->where('vendor_id', $partner->id)->exists()) {
            return true;
        }

        if ($partner->affiliateEvents()->exists()) {
            return true;
        }

        if ($partner->marketplaceAssets()->exists()) {
            return true;
        }

        return false;
    }
}
