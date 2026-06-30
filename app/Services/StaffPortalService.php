<?php

namespace App\Services;

use App\Models\User;

class StaffPortalService
{
    /** @return list<array{label: string, route: string, description: string}> */
    public function shortcuts(User $user): array
    {
        $permissions = app(PermissionService::class);
        $items = [];

        foreach ($this->catalog() as $permission => $meta) {
            if ($permissions->has($user, $permission) && \Illuminate\Support\Facades\Route::has($meta['route'])) {
                $items[] = [
                    'label'       => $meta['label'],
                    'route'       => $meta['route'],
                    'description' => $meta['description'],
                ];
            }
        }

        return $items;
    }

    /** @return array<string, array{label: string, route: string, description: string}> */
    protected function catalog(): array
    {
        return [
            'applications.view'       => ['label' => 'Loan applications', 'route' => 'admin.loan-applications.index', 'description' => 'Review and process applications'],
            'customers.view'          => ['label' => 'Customers', 'route' => 'admin.customers.index', 'description' => 'Borrower profiles and KYC'],
            'kyc.review'              => ['label' => 'KYC queue', 'route' => 'admin.face-verifications.index', 'description' => 'Face verification and identity review'],
            'loans.view'              => ['label' => 'Loans', 'route' => 'admin.loans.index', 'description' => 'Active loans and balances'],
            'support.tickets'         => ['label' => 'Support tickets', 'route' => 'admin.support-tickets.index', 'description' => 'Respond to borrower support'],
            'reports.view'            => ['label' => 'Reports', 'route' => 'admin.reports.portfolio', 'description' => 'Portfolio and operational reports'],
            'audit.view'              => ['label' => 'Audit logs', 'route' => 'admin.audit-logs.index', 'description' => 'System audit trail'],
            'finance.reports'         => ['label' => 'Finance reports', 'route' => 'admin.reports.finance-summary', 'description' => 'Financial statements'],
            'marketplace.view'        => ['label' => 'Marketplace', 'route' => 'admin.marketplace-assets.index', 'description' => 'Asset marketplace inventory'],
        ];
    }
}
