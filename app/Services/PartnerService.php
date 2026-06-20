<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;

class PartnerService
{
    /** @return array<string, string> */
    public function roleOptions(): array
    {
        return [
            'supplier'      => 'Asset supplier',
            'affiliate'     => 'Affiliate',
            'gps_installer' => 'GPS installer',
            'insurance'     => 'Insurance provider',
            'valuer'        => 'Valuer',
            'towing'        => 'Towing',
            'yard'          => 'Yard',
            'auctioneer'    => 'Auctioneer',
            'capital'       => 'Capital partner',
            'call_center'   => 'Call center',
            'debt_collector'=> 'Debt collector',
            'legal_partner' => 'Legal partner',
        ];
    }

    public function filteredQuery(?string $role = null, ?string $search = null): Builder
    {
        return Partner::query()
            ->when(filled($search), function (Builder $q) use ($search): void {
                $term = '%'.$search.'%';
                $q->where(function (Builder $qq) use ($term): void {
                    $qq->where('name', 'like', $term)
                        ->orWhere('partner_number', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('affiliate_code', 'like', $term);
                });
            })
            ->when(filled($role), function (Builder $q) use ($role): void {
                $q->where(function (Builder $qq) use ($role): void {
                    $qq->where('category', $role)
                        ->orWhere('roles', 'like', '%"'.$role.'"%');
                });
            });
    }

    /** @param list<string> $roles */
    public function syncRoles(Vendor $vendor, array $roles): void
    {
        $roles = array_values(array_unique(array_filter($roles)));
        $vendor->update([
            'roles'    => $roles ?: null,
            'category' => $roles[0] ?? $vendor->category,
        ]);
    }
}
