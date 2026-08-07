<?php

namespace App\Services;

use App\Models\CollectionAction;
use App\Models\GuarantorInvitation;
use App\Models\RecoveryAssignment;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecoveryPartnerPortalService
{
    public function __construct(
        private readonly RecoveryAssignmentService $assignments,
        private readonly LoanCollectionActionService $collectionActions,
        private readonly AuctionProceedsService $auctions,
    ) {}

    public function assertVendorOwnsAssignment(RecoveryAssignment $assignment, Vendor $vendor): void
    {
        if ((int) $assignment->vendor_id !== (int) $vendor->id) {
            abort(404);
        }
    }

    /** @return array<string, array<string, mixed>> */
    public function portalActions(string $partnerType): array
    {
        return config("recovery.portal_actions.{$partnerType}", config('recovery.portal_actions.call_center', []));
    }

    /** @return array<string, mixed> */
    public function caseViewData(RecoveryAssignment $assignment): array
    {
        $assignment->loadMissing([
            'vendor',
            'arrearCase.loan.customer.branch',
            'arrearCase.loan.product',
            'arrearCase.loan.application.collateralAssets.customerAsset',
            'arrearCase.loan.repaymentSchedules',
            'vendorTask.documents',
        ]);

        $loan = $assignment->arrearCase?->loan;
        $customer = $loan?->customer;
        $guarantor = null;

        if ($loan?->loan_application_id) {
            $guarantor = GuarantorInvitation::query()
                ->with('guarantorCustomer')
                ->where('loan_application_id', $loan->loan_application_id)
                ->whereIn('status', ['accepted', 'completed', 'signed'])
                ->latest('id')
                ->first();
        }

        $slaDaysRemaining = null;
        if ($assignment->sla_due_at && $assignment->isOpen()) {
            $slaDaysRemaining = (int) now()->startOfDay()->diffInDays($assignment->sla_due_at->startOfDay(), false);
        }

        $vendorName = (string) ($assignment->vendor?->name ?? '');
        $actions = $assignment->arrearCase
            ? CollectionAction::query()
                ->with('performer')
                ->where('arrear_case_id', $assignment->arrear_case_id)
                ->where(function ($q) use ($assignment, $vendorName) {
                    $q->where('recovery_assignment_id', $assignment->id);
                    if ($vendorName !== '') {
                        $q->orWhere(function ($legacy) use ($assignment, $vendorName) {
                            $legacy->whereNull('recovery_assignment_id')
                                ->where('arrear_case_id', $assignment->arrear_case_id)
                                ->where('notes', 'like', '['.$vendorName.']%');
                        });
                    }
                })
                ->latest('performed_at')
                ->limit(20)
                ->get()
            : collect();

        $liveOutstanding = null;
        $penaltyOutstanding = null;
        $daysPastDue = null;
        $productName = $loan?->product?->name;
        $nextInstallment = null;
        $miniSchedule = collect();
        $servicing = null;

        if ($loan) {
            $servicing = app(ActiveLoanServicingService::class)->forLoan($loan);
            $liveOutstanding = (float) ($servicing['outstanding_balance'] ?? 0);
            $penaltyOutstanding = (float) ($servicing['balance_breakdown']['penalty_outstanding'] ?? 0);
            $daysPastDue = (int) ($servicing['days_past_due'] ?? 0);
            $nextRow = $servicing['next_installment'] ?? null;
            if ($nextRow) {
                $nextInstallment = [
                    'amount' => (float) ($nextRow->total_due - $nextRow->amount_paid),
                    'due_date' => $nextRow->due_date,
                    'installment_no' => $nextRow->installment_no,
                    'status' => $nextRow->status,
                ];
            }
            $miniSchedule = $loan->repaymentSchedules
                ->sortBy('installment_no')
                ->reject(fn ($row) => in_array($row->status, ['paid'], true)
                    || (float) $row->amount_paid >= (float) $row->total_due)
                ->take(3)
                ->values()
                ->map(fn ($row) => [
                    'installment_no' => $row->installment_no,
                    'due_date' => $row->due_date,
                    'amount_due' => max(0, (float) $row->total_due - (float) $row->amount_paid),
                    'status' => $row->status,
                ]);
        }

        $region = collect([
            $customer?->region,
            $customer?->district,
            $customer?->ward,
        ])->filter()->implode(', ') ?: null;

        $addressLine = trim((string) ($customer?->street ?: $customer?->address ?: ''));
        $branchName = $customer?->branch?->name ?? $customer?->branch?->label ?? null;

        $nextPartnerType = app(RecoveryEscalationService::class)->nextPartnerType($assignment->partner_type);
        $nextPartnerLabel = $nextPartnerType
            ? app(RecoveryPolicyService::class)->partnerTypeLabel($nextPartnerType)
            : null;

        $collateral = app(GpsDeviceService::class)->collateralForLoan($loan);
        $talkTrack = $assignment->partner_type === 'call_center'
            ? $this->talkTrack(
                $assignment,
                $loan?->loan_number,
                $liveOutstanding,
                $daysPastDue,
                $nextInstallment,
                $slaDaysRemaining,
                $nextPartnerLabel,
            )
            : null;
        $auctionHold = $loan
            ? app(AuctionHoldService::class)->statusForLoan($loan)
            : null;
        $gpsService = app(GpsDeviceService::class);
        $gpsInstallerContact = $loan ? $gpsService->installerContactForLoan($loan) : null;
        $showGpsInstallerContact = $assignment->partner_type === 'debt_collector'
            && $gpsInstallerContact !== null;

        return [
            'loan'                => $loan,
            'customer'            => $customer,
            'guarantor'           => $guarantor,
            'guarantor_name'      => $guarantor?->guarantorCustomer?->full_name
                ?? $guarantor?->invitee_name,
            'guarantor_phone'     => $guarantor?->contact
                ?? $guarantor?->guarantorCustomer?->phone,
            'sla_days_remaining'  => $slaDaysRemaining,
            'portal_actions'      => $this->portalActions($assignment->partner_type),
            'activity'            => $actions,
            'live_outstanding'    => $liveOutstanding,
            'penalty_outstanding' => $penaltyOutstanding,
            'days_past_due'       => $daysPastDue,
            'product_name'        => $productName,
            'borrower_region'     => $region,
            'borrower_address'    => $addressLine !== '' ? $addressLine : null,
            'branch_name'         => $branchName,
            'next_partner_label'  => $nextPartnerLabel,
            'next_installment'    => $nextInstallment,
            'mini_schedule'       => $miniSchedule,
            'collateral_items'    => $collateral,
            'talk_track'          => $talkTrack,
            'wallet_url'          => route('site.partner.recovery-wallet'),
            'auction_hold'        => $auctionHold,
            'gps_installer_contact' => $gpsInstallerContact,
            'show_gps_installer_contact' => $showGpsInstallerContact,
            'gps_map_enabled'     => $gpsService->mapEnabled(),
        ];
    }

    public function sendBorrowerReminder(
        RecoveryAssignment $assignment,
        Vendor $vendor,
        User $actor,
    ): void {
        $this->assertVendorOwnsAssignment($assignment, $vendor);

        if (! $assignment->isOpen()) {
            throw ValidationException::withMessages([
                'status' => 'This recovery case is already closed.',
            ]);
        }

        $assignment->loadMissing(['arrearCase.loan.customer']);
        $loan = $assignment->arrearCase?->loan;
        $customer = $loan?->customer;

        if (! $customer) {
            throw ValidationException::withMessages([
                'customer' => 'Borrower not found for this case.',
            ]);
        }

        $outstanding = $loan
            ? (float) (app(ActiveLoanServicingService::class)->forLoan($loan)['outstanding_balance'] ?? 0)
            : (float) $assignment->original_outstanding;

        $brand = function_exists('brand_name') ? brand_name() : 'KopaFasta';
        $name = trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')) ?: 'Customer';
        $loanNumber = $loan?->loan_number ?? 'your loan';
        $amount = format_money($outstanding);

        app(NotificationService::class)->notifyCustomer($customer, 'recovery_case_reminder', [
            'name' => $name,
            'loan_number' => $loanNumber,
            'amount' => $amount,
            '_fallback_subject' => 'Payment reminder',
            '_fallback_body' => "Hi {$name}, reminder: loan {$loanNumber} has {$amount} outstanding. Please pay today or contact us. — {$brand}",
        ]);

        if ($assignment->arrearCase) {
            $this->collectionActions->logForCase(
                $assignment->arrearCase,
                $actor,
                'reminder_sent',
                '['.$vendor->name.'] In-app payment reminder sent to borrower',
                'reminded',
                null,
                $assignment,
            );
        }
    }

    public function startCase(RecoveryAssignment $assignment, Vendor $vendor, User $actor): RecoveryAssignment
    {
        $this->assertVendorOwnsAssignment($assignment, $vendor);

        if ($assignment->status !== RecoveryAssignment::STATUS_ASSIGNED) {
            throw ValidationException::withMessages([
                'status' => 'This case has already been started.',
            ]);
        }

        return $this->assignments->start($assignment, $actor);
    }

    public function recordAction(
        RecoveryAssignment $assignment,
        Vendor $vendor,
        User $actor,
        string $actionKey,
        ?string $notes = null,
        ?UploadedFile $file = null,
        ?float $auctionProceeds = null,
        ?string $buyerName = null,
        ?string $lotReference = null,
    ): RecoveryAssignment {
        $this->assertVendorOwnsAssignment($assignment, $vendor);

        $config = $this->actionConfig($assignment->partner_type, $actionKey);
        if ($config === null) {
            throw ValidationException::withMessages([
                'action' => 'Invalid recovery action.',
            ]);
        }

        if (! $assignment->isOpen()) {
            throw ValidationException::withMessages([
                'status' => 'This recovery case is already closed.',
            ]);
        }

        $notes = trim((string) $notes);
        $buyerName = trim((string) $buyerName);
        $lotReference = trim((string) $lotReference);

        if (($config['notes'] ?? null) === 'required' && $notes === '' && ! ($config['accepts_file'] ?? false)) {
            throw ValidationException::withMessages([
                'notes' => 'Notes are required for this action.',
            ]);
        }

        if (($config['accepts_file'] ?? false) && ! $file) {
            throw ValidationException::withMessages([
                'file' => 'Please upload a photo for this action.',
            ]);
        }

        if (($config['requires_auction_proceeds'] ?? false) && ($auctionProceeds === null || $auctionProceeds <= 0)) {
            throw ValidationException::withMessages([
                'auction_proceeds' => 'Enter the auction sale amount.',
            ]);
        }

        return DB::transaction(function () use (
            $assignment,
            $vendor,
            $actor,
            $actionKey,
            $notes,
            $file,
            $config,
            $auctionProceeds,
            $buyerName,
            $lotReference,
        ) {
            if ($assignment->status === RecoveryAssignment::STATUS_ASSIGNED) {
                $assignment = $this->assignments->start($assignment, $actor);
            }

            if ($file && ($config['accepts_file'] ?? false)) {
                $this->storeProof($assignment, $vendor, $file, (string) ($config['file_label'] ?? 'Recovery photo'));
            }

            $label = (string) ($config['label'] ?? $actionKey);
            $extra = [];
            if ($lotReference !== '') {
                $extra[] = 'Lot '.$lotReference;
            }
            if ($buyerName !== '') {
                $extra[] = 'Buyer '.$buyerName;
            }
            $enrichedNotes = $notes;
            if ($extra !== []) {
                $enrichedNotes = trim($notes.($notes !== '' ? ' · ' : '').implode(' · ', $extra));
            }
            $noteText = $label.($enrichedNotes !== '' ? ': '.$enrichedNotes : '');

            if ($assignment->arrearCase) {
                $this->collectionActions->logForCase(
                    $assignment->arrearCase,
                    $actor,
                    (string) ($config['collection_type'] ?? 'other'),
                    '['.$vendor->name.'] '.$noteText,
                    $config['result'] ?? null,
                    null,
                    $assignment,
                );
            }

            $assignment->update([
                'notes' => trim(($assignment->notes ? $assignment->notes."\n" : '')
                    .'['.now()->format('d M Y H:i').'] '.$noteText),
            ]);

            $hold = app(AuctionHoldService::class);
            if (($config['starts_auction_hold'] ?? false) && $assignment->arrearCase) {
                $hold->markRepossessed(
                    $assignment->arrearCase,
                    $assignment,
                    $actor,
                    $enrichedNotes !== '' ? $enrichedNotes : null,
                );
            }
            if (($config['marks_auction_listed'] ?? false) && $assignment->arrearCase) {
                $hold->markListed($assignment->arrearCase, $actor, $assignment);
            }

            if ($config['completes'] ?? false) {
                $completed = $this->assignments->complete(
                    $assignment->fresh(),
                    $actor,
                    (string) ($config['outcome'] ?? $actionKey),
                    $enrichedNotes !== '' ? $enrichedNotes : null,
                );

                if (($config['requires_auction_proceeds'] ?? false) && $assignment->partner_type === 'auctioneer') {
                    $loan = $assignment->arrearCase?->loan;
                    if ($loan) {
                        $this->auctions->settle(
                            $loan,
                            (float) $auctionProceeds,
                            $actor,
                            $assignment->arrearCase,
                            $completed,
                            trim(($enrichedNotes !== '' ? $enrichedNotes.' · ' : '').'Sold via auctioneer portal'),
                        );
                    }
                }

                if (($config['marks_auction_sold'] ?? false) && $assignment->arrearCase) {
                    $hold->markSold($assignment->arrearCase->fresh());
                }

                return $completed->fresh();
            }

            return $assignment->fresh();
        });
    }

    /**
     * @param  array{amount?: float, due_date?: mixed}|null  $nextInstallment
     * @return array{title: string, lines: list<string>}
     */
    private function talkTrack(
        RecoveryAssignment $assignment,
        ?string $loanNumber,
        ?float $outstanding,
        ?int $daysPastDue,
        ?array $nextInstallment,
        ?int $slaDaysRemaining,
        ?string $nextPartnerLabel,
    ): array {
        $brand = function_exists('brand_name') ? brand_name() : 'KopaFasta';
        $lines = [
            "Hello, I'm calling from {$brand} collections about loan ".($loanNumber ?: 'your account').'.',
        ];

        if ($outstanding !== null) {
            $lines[] = 'Your current outstanding balance is '.format_money($outstanding)
                .(($daysPastDue ?? 0) > 0 ? ", and you are {$daysPastDue} day(s) past due." : '.');
        }

        if ($nextInstallment) {
            $due = $nextInstallment['due_date']?->format('d M Y') ?? 'soon';
            $lines[] = 'The next installment of '.format_money((float) ($nextInstallment['amount'] ?? 0))
                .' is due on '.$due.'.';
        }

        if ($assignment->slaBreached()) {
            $lines[] = 'This case has already passed its SLA. We need a clear payment commitment today.';
        } elseif ($slaDaysRemaining !== null && $slaDaysRemaining <= 2) {
            $escalation = $nextPartnerLabel
                ? " or the file moves to {$nextPartnerLabel}"
                : '';
            $lines[] = "We only have {$slaDaysRemaining} day(s) left on this follow-up{$escalation}. "
                .'What amount can you pay today?';
        } else {
            $lines[] = 'How can we help you clear the arrears today — full payment or a firm promise date?';
        }

        $lines[] = 'I will note your commitment and follow up if payment is not received.';

        return [
            'title' => 'Suggested talk track',
            'lines' => $lines,
        ];
    }

    /** @return array<string, mixed>|null */
    private function actionConfig(string $partnerType, string $actionKey): ?array
    {
        $actions = $this->portalActions($partnerType);

        return $actions[$actionKey] ?? null;
    }

    private function storeProof(
        RecoveryAssignment $assignment,
        Vendor $vendor,
        UploadedFile $file,
        string $label,
    ): void {
        $task = $assignment->vendorTask;
        if (! $task) {
            throw ValidationException::withMessages([
                'file' => 'No linked task found for this recovery case.',
            ]);
        }

        $path = $file->store("vendor/{$vendor->id}/recovery", 'public');

        VendorDocument::create([
            'vendor_id'      => $vendor->id,
            'vendor_task_id' => $task->id,
            'label'          => $label,
            'file_path'      => $path,
            'mime'           => $file->getMimeType(),
            'size_bytes'     => $file->getSize(),
        ]);

        if (! $task->proof_path) {
            $task->update(['proof_path' => $path]);
        }
    }
}
