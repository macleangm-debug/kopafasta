<?php

namespace App\Services;

use App\Models\AssetRequest;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\MarketplaceAsset;
use App\Models\PartnerApplication;
use App\Models\PartnerTask;
use App\Models\User;
use App\Models\Vendor;

class StaffDashboardService
{
    public function __construct(
        private readonly PartnerCoverageRequestService $coverage,
        private readonly PartnerStaffService $partners,
        private readonly CapacityAutoRejectService $capacity,
    ) {}

    public function desk(?User $user): string
    {
        return match ($user?->role) {
            'partner_support' => 'partner_support',
            'officer', 'credit_analyst' => 'screening',
            'credit_committee' => 'committee',
            'manager' => 'management',
            'asset_manager' => 'assets',
            default => 'operations',
        };
    }

    /** @return array<string, mixed> */
    public function payload(?User $user): array
    {
        return match ($this->desk($user)) {
            'partner_support' => $this->partnerSupport(),
            'screening' => $this->screening($user),
            'committee' => $this->committee(),
            'management' => $this->management(),
            'assets' => $this->assets(),
            default => [],
        };
    }

    /** @return array<string, mixed> */
    public function partnerSupport(): array
    {
        $coverageAlerts = $this->coverage->staffAlerts();
        $screeningApps = PartnerApplication::query()
            ->screening()
            ->with('documents')
            ->latest()
            ->limit(8)
            ->get();
        $awaitingActivation = Vendor::query()
            ->where('status', 'inactive')
            ->latest()
            ->limit(8)
            ->get();
        $screeningCount = PartnerApplication::query()->screening()->count();
        $awaitingCount = Vendor::query()->where('status', 'inactive')->count();

        return [
            'kicker' => 'Partner support',
            'title' => 'Your dashboard',
            'subtitle' => 'Screen new partners, close coverage gaps, and keep marketplace and field work moving. Password lives under your name → Account security.',
            'cards' => [
                [
                    'label' => 'Coverage gaps',
                    'value' => $coverageAlerts->count(),
                    'hint' => 'Regions screening asked for',
                    'url' => $coverageAlerts->first()['url'] ?? route('admin.partners.index'),
                ],
                [
                    'label' => 'Applications to screen',
                    'value' => $screeningCount,
                    'hint' => 'Approve to move them to the Partners hub',
                    'url' => route('admin.partner-applications.index'),
                ],
                [
                    'label' => 'Awaiting activation',
                    'value' => $awaitingCount,
                    'hint' => 'Approved, PIN not set — on the Partners hub',
                    'url' => route('admin.partners.onboarding'),
                ],
                [
                    'label' => 'Open partner tasks',
                    'value' => PartnerTask::query()->whereIn('status', ['assigned', 'in_progress', 'accepted'])->count(),
                    'hint' => 'Valuation, GPS, recovery',
                    'url' => route('admin.partners.tasks'),
                ],
                [
                    'label' => 'Asset requests',
                    'value' => AssetRequest::query()->whereIn('status', ['pending', 'sourcing', 'reviewing'])->count(),
                    'hint' => 'Borrower marketplace asks',
                    'url' => route('admin.asset-requests.index'),
                ],
                [
                    'label' => 'Active partners',
                    'value' => Vendor::query()->where('status', 'active')->count(),
                    'hint' => 'Live on the Partners hub',
                    'url' => route('admin.partners.index'),
                ],
            ],
            'coverageAlerts' => $coverageAlerts,
            'pendingApplications' => $screeningApps,
            'awaitingActivation' => $awaitingActivation,
            'duties' => $this->partners->duties(),
            'actions' => [
                ['Add partner', route('admin.partners.create'), 'gold'],
                ['Partner applications', route('admin.partner-applications.index'), 'white'],
                ['Partners hub', route('admin.partners.index'), 'white'],
                ['Marketplace', route('admin.marketplace-assets.index'), 'white'],
                ['Field & recovery', route('admin.recovery.assignments.index'), 'white'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function screening(?User $user): array
    {
        $mine = LoanApplication::query()
            ->whereIn('current_stage', ['screening', 'credit_appraisal'])
            ->where('assigned_analyst_id', $user?->id)
            ->count();

        return [
            'kicker' => 'Credit screening',
            'title' => 'Your dashboard',
            'subtitle' => 'Documents, face/ID, affordability, then push to committee. Change your password under your name → Account security.',
            'cards' => [
                [
                    'label' => 'Assigned to me',
                    'value' => $mine,
                    'hint' => 'Your screening files',
                    'url' => route('admin.loan-applications.index', ['mine' => 1]),
                ],
                [
                    'label' => 'Screening',
                    'value' => LoanApplication::query()->where('current_stage', 'screening')->count(),
                    'hint' => 'Waiting on this desk',
                    'url' => route('admin.loan-applications.pipeline.under-review'),
                ],
                [
                    'label' => 'Credit appraisal',
                    'value' => LoanApplication::query()->where('current_stage', 'credit_appraisal')->count(),
                    'hint' => 'In appraisal',
                    'url' => route('admin.loan-applications.pipeline.under-review'),
                ],
            ],
            'actions' => [
                ['Open screening queue', route('admin.loan-applications.pipeline.under-review'), 'gold'],
                ['My queue', route('admin.loan-applications.index', ['mine' => 1]), 'white'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function committee(): array
    {
        return [
            'kicker' => 'Credit committee',
            'title' => 'Your dashboard',
            'subtitle' => 'Approve, counter, or reject. Password is under your name → Account security.',
            'cards' => [
                [
                    'label' => 'Committee queue',
                    'value' => LoanApplication::query()->where('current_stage', 'pre_approval')->count(),
                    'hint' => 'Waiting on a decision',
                    'url' => route('admin.loan-applications.pre-approvals'),
                ],
                [
                    'label' => 'With recommendation',
                    'value' => LoanApplication::query()
                        ->where('current_stage', 'pre_approval')
                        ->whereNotNull('recommendation_type')
                        ->count(),
                    'hint' => 'Screening has signed off',
                    'url' => route('admin.loan-applications.pre-approvals'),
                ],
                [
                    'label' => 'System sorted',
                    'value' => LoanApplication::query()
                        ->whereIn('current_stage', ['submitted', 'screening', 'credit_appraisal'])
                        ->where('screening_payload->capacity_auto_reject->status', CapacityAutoRejectService::STATUS_PENDING)
                        ->count(),
                    'hint' => 'Capacity auto-reject window',
                    'url' => route('admin.loan-applications.pipeline.system-sorted'),
                ],
            ],
            'actions' => [
                ['Open committee queue', route('admin.loan-applications.pre-approvals'), 'gold'],
                ['System sorted', route('admin.loan-applications.pipeline.system-sorted'), 'white'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function management(): array
    {
        return [
            'kicker' => 'Credit management',
            'title' => 'Your dashboard',
            'subtitle' => 'Offer, fees, contract, then release and payout. Password is under your name → Account security.',
            'cards' => [
                [
                    'label' => 'Management queue',
                    'value' => LoanApplication::query()->where('current_stage', 'approval')->count(),
                    'hint' => 'Offer / fees / contract',
                    'url' => route('admin.loan-applications.pipeline.approved'),
                ],
                [
                    'label' => 'Release queue',
                    'value' => LoanApplication::query()->where('current_stage', 'disbursement')->count(),
                    'hint' => 'Ready to release',
                    'url' => route('admin.loan-applications.pipeline.disbursement'),
                ],
                [
                    'label' => 'Payout queue',
                    'value' => Loan::query()->where('status', 'pending')->count(),
                    'hint' => 'Awaiting payout',
                    'url' => route('admin.loans.disbursement'),
                ],
            ],
            'actions' => [
                ['Management queue', route('admin.loan-applications.pipeline.approved'), 'gold'],
                ['Release queue', route('admin.loan-applications.pipeline.disbursement'), 'white'],
                ['Payout queue', route('admin.loans.disbursement'), 'white'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function assets(): array
    {
        return [
            'kicker' => 'Asset marketplace',
            'title' => 'Your dashboard',
            'subtitle' => 'Listings and borrower asset requests. Password is under your name → Account security.',
            'cards' => [
                [
                    'label' => 'Live listings',
                    'value' => MarketplaceAsset::query()->where('is_active', true)->count(),
                    'hint' => 'On the marketplace',
                    'url' => route('admin.marketplace-assets.index'),
                ],
                [
                    'label' => 'Asset requests',
                    'value' => AssetRequest::query()->whereIn('status', ['pending', 'sourcing', 'reviewing'])->count(),
                    'hint' => 'Waiting on a match',
                    'url' => route('admin.asset-requests.index'),
                ],
            ],
            'actions' => [
                ['Marketplace', route('admin.marketplace-assets.index'), 'gold'],
                ['Asset requests', route('admin.asset-requests.index'), 'white'],
            ],
        ];
    }
}
