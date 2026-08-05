<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\MembershipHistory;
use App\Models\PartnerApplication;
use Illuminate\Support\Collection;

class AdminAlertService
{
    /** @return Collection<int, array{key: string, label: string, count: int, url: string, category: string}> */
    public function alerts(): Collection
    {
        $integrationAlerts = collect(app(\App\Services\Integrations\IntegrationHealthService::class)->unhealthyPartners())
            ->map(fn (array $item) => [
                'key'      => 'integration_'.$item['key'],
                'label'    => 'Integration issue: '.$item['label'],
                'count'    => 1,
                'url'      => $item['url'],
                'category' => 'integrations',
            ]);

        $items = collect([
            [
                'key'      => 'registrations',
                'label'    => 'New registrations (7 days)',
                'count'    => Customer::where('created_at', '>=', now()->subDays(7))->count(),
                'url'      => route('admin.customers.index'),
                'category' => 'customers',
            ],
            [
                'key'      => 'face_pending',
                'label'    => 'Face verification pending',
                'count'    => Customer::where('face_verification_status', 'pending')->count(),
                'url'      => route('admin.face-verifications.index'),
                'category' => 'kyc',
            ],
            [
                'key'      => 'loans_submitted',
                'label'    => 'New loan applications',
                'count'    => LoanApplication::whereIn('status', ['submitted', 'pending_documents'])->count(),
                'url'      => route('admin.loan-applications.new'),
                'category' => 'loans',
            ],
            [
                'key'      => 'under_review',
                'label'    => 'Applications under review',
                'count'    => LoanApplication::where('status', 'under_review')->count(),
                'url'      => route('admin.loan-applications.pipeline.under-review'),
                'category' => 'loans',
            ],
            [
                'key'      => 'membership_payments',
                'label'    => 'Membership payments pending',
                'count'    => MembershipHistory::pending()->count(),
                'url'      => route('admin.membership-payments.index'),
                'category' => 'customers',
            ],
            [
                'key'      => 'partner_applications',
                'label'    => 'Partner applications pending',
                'count'    => PartnerApplication::where('status', 'pending')->count(),
                'url'      => route('admin.partner-applications.index'),
                'category' => 'partners',
            ],
        ])->concat($integrationAlerts);

        return $items->filter(fn (array $item) => $item['count'] > 0)->values();
    }

    public function unreadCount(): int
    {
        return $this->alerts()->sum('count');
    }
}
