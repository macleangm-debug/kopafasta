<?php

namespace App\Services;

use App\Models\CollectionAction;
use App\Models\Customer;
use App\Models\CustomerGuarantor;
use App\Models\GuarantorInvitation;
use App\Models\Loan;
use App\Models\LoanAgreement;
use App\Models\LoanGroupMember;
use App\Models\RecoveryAssignment;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorDocument;
use App\Support\PhoneNumber;
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
            'arrearCase.loan.application.loanGroup.members.customer',
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
        $collectionFile = $this->collectionFile($loan);
        $talkTrack = $assignment->partner_type === 'call_center'
            ? $this->talkTrack(
                $assignment,
                $loan?->loan_number,
                $liveOutstanding,
                $daysPastDue,
                $nextInstallment,
                $slaDaysRemaining,
                $nextPartnerLabel,
                $collectionFile['contacts'],
            )
            : null;
        $auctionHold = $loan
            ? app(AuctionHoldService::class)->statusForLoan($loan)
            : null;
        $gpsService = app(GpsDeviceService::class);
        $gpsInstallerContact = $loan ? $gpsService->installerContactForLoan($loan) : null;
        $showGpsInstallerContact = $assignment->partner_type === 'debt_collector'
            && $gpsInstallerContact !== null;
        $letters = app(LoanAgreementService::class)->creditFileLetters($loan?->application);

        return [
            'loan' => $loan,
            'customer' => $customer,
            'guarantor' => $guarantor,
            'guarantor_name' => $guarantor?->guarantorCustomer?->full_name
                ?? $guarantor?->invitee_name,
            'guarantor_phone' => $guarantor?->contact
                ?? $guarantor?->guarantorCustomer?->phone,
            'sla_days_remaining' => $slaDaysRemaining,
            'portal_actions' => $this->portalActions($assignment->partner_type),
            'activity' => $actions,
            'live_outstanding' => $liveOutstanding,
            'penalty_outstanding' => $penaltyOutstanding,
            'days_past_due' => $daysPastDue,
            'product_name' => $productName,
            'borrower_region' => $region,
            'borrower_address' => $addressLine !== '' ? $addressLine : null,
            'branch_name' => $branchName,
            'next_partner_label' => $nextPartnerLabel,
            'next_installment' => $nextInstallment,
            'mini_schedule' => $miniSchedule,
            'collateral_items' => $collateral,
            'talk_track' => $talkTrack,
            'wallet_url' => route('site.partner.recovery-wallet'),
            'auction_hold' => $auctionHold,
            'gps_installer_contact' => $gpsInstallerContact,
            'show_gps_installer_contact' => $showGpsInstallerContact,
            'gps_map_enabled' => $gpsService->mapEnabled(),
            'record' => $loan?->application,
            'product' => $loan?->product,
            'servicing' => $servicing,
            'letters' => $letters,
            'offer' => $letters['offer'],
            'contract' => $letters['contract'],
            'finalContract' => $letters['final'],
            'signedContract' => $letters['signed'],
            'collection_contacts' => $collectionFile['contacts'],
            'collection_subjects' => $collectionFile['subjects'],
        ];
    }

    public function assignmentMayViewAgreement(RecoveryAssignment $assignment, LoanAgreement $agreement): bool
    {
        $assignment->loadMissing('arrearCase.loan');
        $loan = $assignment->arrearCase?->loan;
        if (! $loan) {
            return false;
        }

        $applicationId = (int) ($loan->loan_application_id ?? 0);
        if ($applicationId <= 0 || (int) $agreement->loan_application_id !== $applicationId) {
            return false;
        }

        return in_array($agreement->document_type, [
            'offer_letter',
            'loan_contract',
            'final_loan_contract',
        ], true);
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
        ?string $contactedParty = null,
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
        $contactedParty = trim((string) $contactedParty);

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

        $contact = null;
        if ($config['requires_contact'] ?? false) {
            $assignment->loadMissing('arrearCase.loan');
            $contacts = collect($this->collectionFile($assignment->arrearCase?->loan)['contacts'] ?? []);
            $contact = $contacts->firstWhere('key', $contactedParty);
            if (! $contact) {
                throw ValidationException::withMessages([
                    'contacted_party' => 'Select who you contacted — borrower, guarantor, next of kin, or group member.',
                ]);
            }
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
            $contact,
        ) {
            if ($assignment->status === RecoveryAssignment::STATUS_ASSIGNED) {
                $assignment = $this->assignments->start($assignment, $actor);
            }

            if ($file && ($config['accepts_file'] ?? false)) {
                $this->storeProof($assignment, $vendor, $file, (string) ($config['file_label'] ?? 'Recovery photo'));
            }

            $label = (string) ($config['label'] ?? $actionKey);
            if (is_array($contact)) {
                $label .= ' · '.($contact['role'] ?? 'Contact').' · '.($contact['name'] ?? '');
            }
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
     * Contacts and profile subjects the collection partner may call — no CRB, no screening.
     *
     * @return array{contacts: list<array<string, mixed>>, subjects: list<array<string, mixed>>}
     */
    public function collectionFile(?Loan $loan): array
    {
        if (! $loan) {
            return ['contacts' => [], 'subjects' => []];
        }

        $loan->loadMissing(['customer', 'application.loanGroup.members.customer']);
        $application = $loan->application;
        $reviewer = app(LoanApplicationReviewService::class);
        $contacts = [];
        $subjects = [];
        $seen = [];

        $pushContact = function (array $row) use (&$contacts, &$seen): void {
            $phoneKey = preg_replace('/\D+/', '', (string) ($row['phone'] ?? '')) ?: 'none';
            $dedupe = $row['key'].':'.$phoneKey;
            if (isset($seen[$dedupe])) {
                return;
            }
            $seen[$dedupe] = true;
            $contacts[] = $row;
        };

        $borrower = $loan->customer;
        if ($borrower) {
            $pushContact($this->makeContact('borrower', 'Borrower', $borrower->full_name, $borrower->phone, $borrower));
            $nok = $this->nextOfKinContact($borrower, 'borrower', 'Next of kin (borrower)');
            if ($nok) {
                $pushContact($nok);
            }
            $subjects[] = [
                'key' => 'borrower',
                'person' => 'borrower',
                'label' => 'Borrower',
                'sublabel' => $borrower->full_name,
                'phone' => $borrower->phone,
                'file' => $reviewer->subjectFileForCustomer($borrower),
            ];
        }

        if ($application) {
            $links = CustomerGuarantor::query()
                ->where('loan_application_id', $application->id)
                ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'rejected'))
                ->with('guarantor')
                ->get();

            foreach ($links as $link) {
                $invitation = GuarantorInvitation::query()
                    ->where('customer_guarantor_id', $link->id)
                    ->latest('id')
                    ->first();
                $gCustomer = $invitation?->guarantor_customer_id
                    ? Customer::query()->find($invitation->guarantor_customer_id)
                    : null;
                $gRecord = $link->guarantor;
                $name = $gCustomer?->full_name
                    ?: trim(($gRecord?->first_name ?? '').' '.($gRecord?->last_name ?? ''))
                    ?: ($invitation?->invitee_name ?: 'Guarantor');
                $phone = $gCustomer?->phone ?: ($gRecord?->phone ?: $invitation?->contact);
                $key = 'guarantor:'.$link->id;
                $pushContact($this->makeContact(
                    $key,
                    'Guarantor',
                    $name,
                    $phone,
                    $gCustomer,
                    $gRecord?->relationship,
                ));
                if ($gCustomer) {
                    $nok = $this->nextOfKinContact($gCustomer, $key, 'Next of kin (guarantor)');
                    if ($nok) {
                        $pushContact($nok);
                    }
                    $subjects[] = [
                        'key' => $key,
                        'person' => 'guarantor',
                        'g' => $link->id,
                        'label' => 'Guarantor',
                        'sublabel' => $name,
                        'phone' => $phone,
                        'file' => $reviewer->subjectFileForCustomer($gCustomer),
                    ];
                }
            }

            foreach ($application->loanGroup?->members ?? [] as $member) {
                if (! $member instanceof LoanGroupMember || ! $member->customer) {
                    continue;
                }
                $mCustomer = $member->customer;
                if ((int) $mCustomer->id === (int) ($borrower?->id ?? 0) && ($member->role ?? '') === 'leader') {
                    continue;
                }
                $role = ($member->role ?? '') === 'leader' ? 'Group leader' : 'Group member';
                $key = 'member:'.$member->id;
                $pushContact($this->makeContact($key, $role, $mCustomer->full_name, $mCustomer->phone, $mCustomer));
                $nok = $this->nextOfKinContact($mCustomer, $key, 'Next of kin ('.$role.')');
                if ($nok) {
                    $pushContact($nok);
                }
                $subjects[] = [
                    'key' => $key,
                    'person' => 'member',
                    'm' => $member->id,
                    'label' => $role,
                    'sublabel' => $mCustomer->full_name,
                    'phone' => $mCustomer->phone,
                    'file' => $reviewer->subjectFileForCustomer($mCustomer),
                ];
            }
        }

        return ['contacts' => $contacts, 'subjects' => $subjects];
    }

    /**
     * @return array<string, mixed>
     */
    private function makeContact(
        string $key,
        string $role,
        ?string $name,
        ?string $phone,
        ?Customer $customer = null,
        ?string $relationship = null,
    ): array {
        $address = collect([
            $customer?->street ?: $customer?->address,
            $customer?->ward,
            $customer?->district,
            $customer?->region,
        ])->filter()->implode(', ');

        return [
            'key' => $key,
            'role' => $role,
            'name' => filled($name) ? $name : '—',
            'phone' => $phone,
            'phone_label' => PhoneNumber::format($phone) ?: $phone,
            'relationship' => $relationship,
            'address' => $address !== '' ? $address : null,
            'tel' => $this->telHref($phone),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function nextOfKinContact(Customer $customer, string $ownerKey, string $role): ?array
    {
        if (! filled($customer->nok_name) && ! filled($customer->nok_phone)) {
            return null;
        }

        $address = collect([
            $customer->nok_street ?? null,
            $customer->nok_ward ?? null,
            $customer->nok_district ?? null,
            $customer->nok_region ?? null,
        ])->filter()->implode(', ');
        $phone = $customer->nok_phone;

        return [
            'key' => 'nok:'.$ownerKey,
            'role' => $role,
            'name' => filled($customer->nok_name) ? $customer->nok_name : '—',
            'phone' => $phone,
            'phone_label' => PhoneNumber::format($phone) ?: $phone,
            'relationship' => function_exists('kin_relationship_label')
                ? kin_relationship_label($customer->nok_relationship)
                : $customer->nok_relationship,
            'address' => $address !== '' ? $address : null,
            'tel' => $this->telHref($phone),
        ];
    }

    private function telHref(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if ($digits === '') {
            return null;
        }

        return 'tel:+'.$digits;
    }

    /**
     * @param  array{amount?: float, due_date?: mixed}|null  $nextInstallment
     * @param  list<array<string, mixed>>  $contacts
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
        array $contacts = [],
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

        $otherReach = collect($contacts)
            ->reject(fn (array $row) => ($row['key'] ?? '') === 'borrower')
            ->filter(fn (array $row) => filled($row['phone'] ?? null));
        if ($otherReach->isNotEmpty()) {
            $lines[] = 'You may also call: '.$otherReach
                ->map(fn (array $row) => ($row['role'] ?? 'Contact').' '.($row['name'] ?? '').' ('.($row['phone_label'] ?? $row['phone']).')')
                ->implode('; ').'.';
        }

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
            'vendor_id' => $vendor->id,
            'vendor_task_id' => $task->id,
            'label' => $label,
            'file_path' => $path,
            'mime' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
        ]);

        if (! $task->proof_path) {
            $task->update(['proof_path' => $path]);
        }
    }
}
