<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\LoanAgreement;
use App\Models\NotificationLog;
use App\Models\PartnerPayment;
use App\Models\RecoveryAssignment;
use App\Models\ValuationAssignment;
use App\Models\Vendor;
use App\Models\VendorDocument;
use App\Models\VendorPayment;
use App\Models\VendorTask;
use App\Services\AffiliateService;
use App\Services\CollateralInsurancePartnerService;
use App\Services\GpsDeviceService;
use App\Services\LoanAgreementService;
use App\Services\NotificationService;
use App\Services\PartnerPayoutRequestService;
use App\Services\AffiliateMembershipService;
use App\Services\PartnerMembershipService;
use App\Services\PartnerProfileService;
use App\Services\PartnerSettlementService;
use App\Services\PaymentAccountService;
use App\Services\PartnerWalletService;
use App\Services\RecoveryCommissionWalletService;
use App\Services\RecoveryPartnerKpiService;
use App\Services\RecoveryPartnerPortalService;
use App\Services\RecoveryPartnerService;
use App\Services\ValuationInspectionService;
use App\Services\ValuationPartnerService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class VendorController extends Controller
{
    /* ------------------------------------------------------------------ */
    /* Helpers */
    /* ------------------------------------------------------------------ */

    protected function vendor(): Vendor
    {
        $user = Auth::user();
        abort_unless($user && $user->role === 'vendor', 403);

        $vendor = Vendor::where('user_id', $user->id)->first();
        if (! $vendor) {
            abort(403, 'Partner profile not found. Contact support or activate your account.');
        }

        return $vendor;
    }

    protected function tasksQuery(Vendor $vendor)
    {
        return VendorTask::where('partner_id', $vendor->id)
            ->with(['loan.customer', 'loanApplication.customer', 'loanApplication.assetReservation.asset', 'documents', 'valuationAssignment']);
    }

    /* ------------------------------------------------------------------ */
    /* Dashboard */
    /* ------------------------------------------------------------------ */

    public function dashboard()
    {
        $vendor = $this->vendor();

        $stats = [
            'assigned' => $this->tasksQuery($vendor)->where('status', 'assigned')->count(),
            'in_progress' => $this->tasksQuery($vendor)->where('status', 'in_progress')->count(),
            'completed_mo' => $this->tasksQuery($vendor)->where('status', 'completed')
                ->where('completed_at', '>=', now()->startOfMonth())->count(),
            'payments_pend' => (int) VendorPayment::where('partner_id', $vendor->id)
                ->where('status', 'pending')->sum('amount'),
            'earnings' => (int) VendorPayment::where('partner_id', $vendor->id)
                ->where('status', 'paid')->sum('amount'),
        ];

        $upcoming = $this->tasksQuery($vendor)
            ->whereIn('status', ['assigned', 'in_progress'])
            ->orderByRaw('COALESCE(due_at, created_at) ASC')
            ->limit(5)->get();

        $affiliateStats = null;
        $affiliateShare = null;
        $affiliateLinks = null;
        $recoveryKpi = null;
        $recoveryWallet = null;
        $wallet = null;

        if (app(RecoveryPartnerService::class)->isRecoveryPartner($vendor)) {
            $recoveryKpi = app(RecoveryPartnerKpiService::class)->kpis($vendor);
            $recoveryWallet = app(RecoveryCommissionWalletService::class)->summary($vendor);
        } else {
            $wallet = app(PartnerWalletService::class)->summary($vendor);
        }

        if ($vendor->category === 'affiliate') {
            $affiliateService = app(AffiliateService::class);
            $affiliateService->ensureCode($vendor);
            $vendor->refresh();
            $affiliateStats = $affiliateService->stats($vendor);
            $affiliateShare = $affiliateService->renderMessage($vendor, 'share_template');
            $affiliateLinks = $affiliateService->messageContext($vendor);
        }

        return view('site.vendor.dashboard', compact(
            'vendor', 'stats', 'upcoming',
            'affiliateStats', 'affiliateShare', 'affiliateLinks',
            'recoveryKpi', 'recoveryWallet', 'wallet',
        ));
    }

    /* ------------------------------------------------------------------ */
    /* Tasks */
    /* ------------------------------------------------------------------ */

    public function tasks(Request $request)
    {
        $vendor = $this->vendor();
        $status = $request->string('status')->toString();

        $q = $this->tasksQuery($vendor)->latest();
        if ($status !== '' && $status !== 'all') {
            $q->where('status', $status);
        }

        $tasks = $q->paginate(15)->withQueryString();

        return view('site.vendor.tasks', compact('vendor', 'tasks', 'status'));
    }

    public function activeJobs()
    {
        $vendor = $this->vendor();
        $tasks = $this->tasksQuery($vendor)
            ->whereIn('status', ['assigned', 'in_progress'])
            ->orderByRaw('COALESCE(due_at, created_at) ASC')
            ->paginate(15);

        return view('site.vendor.active', compact('vendor', 'tasks'));
    }

    public function recoveryCases(Request $request)
    {
        $vendor = $this->vendor();

        if (! app(RecoveryPartnerService::class)->isRecoveryPartner($vendor)) {
            abort(403, 'Recovery partner access only.');
        }

        $status = $request->string('status')->toString();

        $query = RecoveryAssignment::query()
            ->with(['arrearCase.loan.customer', 'vendorTask'])
            ->where('partner_id', $vendor->id)
            ->latest('assigned_at');

        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $assignments = $query->paginate(15)->withQueryString();

        $recoveryKpi = app(RecoveryPartnerKpiService::class)->kpis($vendor);
        $recoveryWallet = app(RecoveryCommissionWalletService::class)->summary($vendor);

        return view('site.vendor.recovery-cases', compact('vendor', 'assignments', 'status', 'recoveryKpi', 'recoveryWallet'));
    }

    public function recoveryWallet(Request $request)
    {
        $vendor = $this->vendor();

        if (! app(RecoveryPartnerService::class)->isRecoveryPartner($vendor)) {
            abort(403, 'Recovery partner access only.');
        }

        $wallet = app(RecoveryCommissionWalletService::class);
        $summary = $wallet->summary($vendor);
        $payments = $wallet->paginated($vendor, 15);
        $recoveryKpi = app(RecoveryPartnerKpiService::class)->kpis($vendor);

        return view('site.vendor.recovery-wallet', compact('vendor', 'summary', 'payments', 'recoveryKpi'));
    }

    public function disputeRecoveryPayment(Request $request, PartnerPayment $payment)
    {
        $vendor = $this->vendor();

        if (! app(RecoveryPartnerService::class)->isRecoveryPartner($vendor)) {
            abort(403, 'Recovery partner access only.');
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            app(RecoveryCommissionWalletService::class)->dispute($payment, $vendor, $data['reason']);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        return back()->with('status', 'Commission dispute submitted for review.');
    }

    public function requestRecoveryPayout(Request $request)
    {
        $vendor = $this->vendor();

        if (! app(RecoveryPartnerService::class)->isRecoveryPartner($vendor)) {
            abort(403, 'Recovery partner access only.');
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            app(PartnerPayoutRequestService::class)->request(
                $vendor,
                'recovery_commission',
                (float) $data['amount'],
                $data['notes'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('status', 'Payout request submitted for admin approval.');
    }

    public function recoveryCase(RecoveryAssignment $recoveryAssignment)
    {
        $vendor = $this->vendor();

        if (! app(RecoveryPartnerService::class)->isRecoveryPartner($vendor)) {
            abort(403, 'Recovery partner access only.');
        }

        $portal = app(RecoveryPartnerPortalService::class);
        $portal->assertVendorOwnsAssignment($recoveryAssignment, $vendor);

        $case = $portal->caseViewData($recoveryAssignment);

        return view('site.vendor.recovery-case', array_merge(
            compact('vendor', 'recoveryAssignment'),
            $case,
            ['assignment' => $recoveryAssignment],
        ));
    }

    public function downloadRecoveryLetter(RecoveryAssignment $recoveryAssignment, LoanAgreement $agreement)
    {
        $vendor = $this->vendor();

        abort_unless(app(RecoveryPartnerService::class)->isRecoveryPartner($vendor), 403);

        $portal = app(RecoveryPartnerPortalService::class);
        $portal->assertVendorOwnsAssignment($recoveryAssignment, $vendor);
        abort_unless($portal->assignmentMayViewAgreement($recoveryAssignment, $agreement), 403);
        abort_unless($agreement->file_path && Storage::disk('public')->exists($agreement->file_path), 404);

        $service = app(LoanAgreementService::class);
        $agreement = $service->ensureBrandedPdf($agreement);
        abort_unless($agreement->file_path && Storage::disk('public')->exists($agreement->file_path), 404);

        return response(Storage::disk('public')->get($agreement->file_path), 200, $service->brandedPdfHeaders(
            $agreement,
            request()->boolean('download'),
        ));
    }

    public function startRecoveryCase(RecoveryAssignment $recoveryAssignment)
    {
        $vendor = $this->vendor();
        $portal = app(RecoveryPartnerPortalService::class);
        $portal->assertVendorOwnsAssignment($recoveryAssignment, $vendor);

        $portal->startCase($recoveryAssignment, $vendor, Auth::user());

        return back()->with('status', 'Recovery case marked in progress.');
    }

    public function recoveryCaseAction(Request $request, RecoveryAssignment $recoveryAssignment)
    {
        $vendor = $this->vendor();
        $portal = app(RecoveryPartnerPortalService::class);
        $portal->assertVendorOwnsAssignment($recoveryAssignment, $vendor);

        $data = $request->validate([
            'action' => ['required', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'auction_proceeds' => ['nullable', 'numeric', 'min:0.01'],
            'buyer_name' => ['nullable', 'string', 'max:120'],
            'lot_reference' => ['nullable', 'string', 'max:80'],
            'contacted_party' => ['nullable', 'string', 'max:80'],
        ]);

        $portal->recordAction(
            $recoveryAssignment,
            $vendor,
            Auth::user(),
            $data['action'],
            $data['notes'] ?? null,
            $request->file('file'),
            isset($data['auction_proceeds']) ? (float) $data['auction_proceeds'] : null,
            $data['buyer_name'] ?? null,
            $data['lot_reference'] ?? null,
            $data['contacted_party'] ?? null,
        );

        $message = in_array($data['action'], ['resolved', 'sold', 'removed', 'repossession_complete'], true)
            ? 'Recovery case completed.'
            : 'Action recorded.';

        return redirect()
            ->route('site.partner.recovery-case', $recoveryAssignment)
            ->with('status', $message);
    }

    public function remindRecoveryCase(RecoveryAssignment $recoveryAssignment)
    {
        $vendor = $this->vendor();
        $portal = app(RecoveryPartnerPortalService::class);
        $portal->assertVendorOwnsAssignment($recoveryAssignment, $vendor);

        $portal->sendBorrowerReminder($recoveryAssignment, $vendor, Auth::user());

        return back()->with('status', 'In-app payment reminder sent to the borrower.');
    }

    public function completedJobs()
    {
        $vendor = $this->vendor();
        $tasks = $this->tasksQuery($vendor)
            ->where('status', 'completed')
            ->latest('completed_at')
            ->paginate(15);

        return view('site.vendor.completed', compact('vendor', 'tasks'));
    }

    public function task(VendorTask $task)
    {
        $vendor = $this->vendor();
        abort_unless($task->vendor_id === $vendor->id, 404);
        $task->load([
            'documents',
            'payment',
            'loan.customer',
            'loanApplication.customer',
            'loanApplication.assetReservation.asset',
        ]);

        return view('site.vendor.task', compact('vendor', 'task'));
    }

    public function acceptTask(VendorTask $task)
    {
        $vendor = $this->vendor();
        abort_unless($task->vendor_id === $vendor->id, 404);
        if ($redirect = $this->redirectIfJobsBlocked($vendor)) {
            return $redirect;
        }
        abort_unless($task->isWritable(), 403);
        $task->update(['status' => 'in_progress', 'accepted_at' => now()]);
        $this->markValuationInProgress($task);

        return back()->with('status', 'Task accepted. You may start work now.');
    }

    public function startTask(VendorTask $task)
    {
        $vendor = $this->vendor();
        abort_unless($task->vendor_id === $vendor->id, 404);
        if ($redirect = $this->redirectIfJobsBlocked($vendor)) {
            return $redirect;
        }
        abort_unless($task->isWritable(), 403);
        $task->update([
            'status' => 'in_progress',
            'started_at' => now(),
            'accepted_at' => $task->accepted_at ?: now(),
        ]);
        $this->markValuationInProgress($task);

        if ($task->task_type === 'asset_valuation') {
            return redirect()
                ->route('site.partner.task', ['task' => $task, 'tab' => 'inspect'])
                ->with('status', __('site.partner_portal.valuation_start_status'));
        }

        return back()->with('status', 'Marked as in progress.');
    }

    public function declineTask(Request $request, VendorTask $task)
    {
        $vendor = $this->vendor();
        abort_unless($task->vendor_id === $vendor->id, 404);
        abort_unless($task->isWritable(), 403);

        $data = $request->validate([
            'reason' => ['required', 'in:too_far,unavailable,conflict,other'],
            'detail' => ['nullable', 'string', 'max:500'],
        ]);

        $ok = app(\App\Services\ServicePartnerReassignmentService::class)
            ->declineTask($task, $data['reason'], $data['detail'] ?? null);

        if (! $ok) {
            $task->mergeNotesMeta(['declined_reason' => $data['reason'], 'declined_detail' => $data['detail'] ?? null]);
            $task->update([
                'status' => 'rejected',
                'completed_at' => now(),
            ]);
        }

        return redirect()
            ->route('site.partner.tasks')
            ->with('status', __('site.partner_portal.decline_thanks'));
    }

    public function inspectValuationPhoto(Request $request, VendorTask $task)
    {
        $vendor = $this->vendor();
        abort_unless($task->vendor_id === $vendor->id, 404);
        abort_unless($task->task_type === 'asset_valuation', 404);
        abort_unless($task->isWritable(), 403);
        abort_unless(filled($task->started_at) || $task->status === 'in_progress', 403);

        $data = $request->validate([
            'customer_asset_id' => ['required', 'integer'],
            'angle' => ['required', 'string', 'max:40'],
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $inspection = app(ValuationInspectionService::class);
        $asset = $inspection->assetsForTask($task)->firstWhere('id', (int) $data['customer_asset_id']);
        abort_unless($asset, 404);

        $inspection->storePhoto($task, $vendor->id, $asset, (string) $data['angle'], $request->file('file'));

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['ok' => true]);
        }

        $task->unsetRelation('documents');
        $task->load('documents');
        $assets = $inspection->assetsForTask($task);
        $steps = $inspection->photoSteps($task, $assets);
        $next = collect($steps)->search(fn (array $step) => ($step['required'] ?? true) && blank($step['path']));
        if ($next === false) {
            $next = collect($steps)->search(fn (array $step) => blank($step['path']));
        }
        $params = ['task' => $task, 'tab' => 'inspect'];
        if ($next !== false) {
            $params['photo'] = $next;
        }

        return redirect()
            ->route('site.partner.task', $params)
            ->with('status', __('site.partner_portal.valuation_photo_saved'));
    }

    public function inspectValuationChecks(Request $request, VendorTask $task)
    {
        $vendor = $this->vendor();
        abort_unless($task->vendor_id === $vendor->id, 404);
        abort_unless($task->task_type === 'asset_valuation', 404);
        abort_unless($task->isWritable(), 403);
        abort_unless(filled($task->started_at) || $task->status === 'in_progress', 403);

        $inspection = app(ValuationInspectionService::class);
        $data = $request->validate([
            'engine' => ['nullable', 'string', Rule::in(array_keys($inspection->engineOptions()))],
            'test_drive' => ['nullable', 'string', Rule::in(array_keys($inspection->driveOptions()))],
            'body_condition' => ['nullable', 'string', Rule::in(array_keys($inspection->bodyConditionOptions()))],
            'tyres' => ['nullable', 'string', Rule::in(array_keys($inspection->tyreOptions()))],
            'interior' => ['nullable', 'string', Rule::in(array_keys($inspection->interiorOptions()))],
        ]);

        $assignment = ValuationAssignment::query()->where('partner_task_id', $task->id)->first();
        abort_unless($assignment, 404);

        $assignment = $inspection->saveChecks($assignment, $data);
        $assets = $inspection->assetsForTask($task);
        $needsVehicle = $assets->contains(fn ($asset) => $asset->isVehicleLike());
        $done = $inspection->inspectionComplete($assignment, $needsVehicle);
        $params = ['task' => $task, 'step' => $done ? 'values' : 'condition'];

        return redirect()
            ->route('site.partner.task', $params)
            ->with('status', __('site.partner_portal.valuation_checks_saved'));
    }

    private function markValuationInProgress(VendorTask $task): void
    {
        if ($task->task_type !== 'asset_valuation') {
            return;
        }

        $assignment = ValuationAssignment::query()
            ->where('partner_task_id', $task->id)
            ->first();

        if ($assignment) {
            app(ValuationPartnerService::class)->markInProgress($assignment);
        }
    }

    public function completeTask(Request $request, VendorTask $task)
    {
        $vendor = $this->vendor();
        abort_unless($task->vendor_id === $vendor->id, 404);
        abort_unless($task->isWritable(), 403);

        $data = $request->validate([
            'gps_serial' => ['nullable', 'string', 'max:60'],
            'gps_provider' => ['nullable', 'string', 'max:40'],
            'gps_device_id' => ['nullable', 'string', 'max:80'],
            'gps_tracking_url' => ['nullable', 'url', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'market_value' => ['nullable', 'string', 'max:40'],
            'forced_sale_value' => ['nullable', 'string', 'max:40'],
            'values' => ['nullable', 'array'],
            'values.*.market_value' => ['nullable', 'string', 'max:40'],
            'values.*.forced_sale_value' => ['nullable', 'string', 'max:40'],
            'insurance_expires_at' => ['nullable', 'date'],
            'insurance_policy_number' => ['nullable', 'string', 'max:120'],
            'insurance_type' => ['nullable', 'in:comprehensive,third_party'],
        ]);

        if ($task->task_type === 'asset_valuation') {
            $assignment = ValuationAssignment::query()
                ->where('partner_task_id', $task->id)
                ->first();
            abort_unless($assignment, 404);

            $inspection = app(ValuationInspectionService::class);
            $assets = $inspection->assetsForTask($task);
            $task->load('documents');
            $inspection->assertReadyToValue($task, $assignment, $assets);

            $valuesInput = (array) ($data['values'] ?? []);
            if ($valuesInput === [] && $assets->count() === 1) {
                $first = $assets->first();
                $valuesInput = [
                    $first->id => [
                        'market_value' => $data['market_value'] ?? 0,
                        'forced_sale_value' => $data['forced_sale_value'] ?? 0,
                    ],
                ];
            }
            $perAsset = $inspection->parseValues($valuesInput, $assets);
            $market = (float) collect($perAsset)->sum('market_value');
            $fsv = (float) collect($perAsset)->sum('forced_sale_value');

            app(ValuationPartnerService::class)->complete(
                $assignment,
                $market,
                $fsv,
                $data['notes'] ?? null,
                $perAsset,
            );

            return redirect()->route('site.partner.task', $task)
                ->with('status', __('site.partner_portal.valuation_submitted'));
        }

        if ($task->task_type === CollateralInsurancePartnerService::TASK_TYPE) {
            abort_unless(filled($data['insurance_expires_at'] ?? null), 422);

            app(CollateralInsurancePartnerService::class)->completeCover(
                $task,
                (string) $data['insurance_expires_at'],
                $data['insurance_policy_number'] ?? null,
                'comprehensive',
            );

            return redirect()->route('site.partner.task', $task)
                ->with('status', 'Insurance cover recorded on the collateral asset.');
        }

        if (str_contains((string) $task->task_type, 'gps')) {
            if (! filled($data['gps_serial'] ?? null) && ! filled($task->gps_serial)) {
                return back()->withErrors(['gps_serial' => 'GPS serial number is required to complete installation.']);
            }
            if (! filled($data['gps_tracking_url'] ?? null) && ! filled($task->gps_tracking_url)) {
                return back()->withErrors(['gps_tracking_url' => 'Enter this device’s tracking URL from your GPS provider portal.']);
            }

            app(GpsDeviceService::class)->recordInstallFromTask($task, [
                'gps_serial' => $data['gps_serial'] ?? $task->gps_serial,
                'gps_provider' => $data['gps_provider'] ?? null,
                'gps_device_id' => $data['gps_device_id'] ?? null,
                'gps_tracking_url' => $data['gps_tracking_url'] ?? null,
            ]);

            $task->update([
                'status' => 'completed',
                'completed_at' => now(),
                'notes' => $data['notes'] ?? $task->notes,
            ]);
        } else {
            $task->update([
                'status' => 'completed',
                'completed_at' => now(),
                'gps_serial' => $data['gps_serial'] ?? $task->gps_serial,
                'notes' => $data['notes'] ?? $task->notes,
            ]);
        }

        // auto-issue invoice if fee set and no payment yet
        if ($task->fee_amount > 0 && ! $task->payment()->exists()) {
            app(PartnerSettlementService::class)->accrue(
                $vendor,
                (int) $task->fee_amount,
                'vendor_task',
                $task->id,
                'Task completion fee #'.$task->id,
                $task->id,
            );
            $task->update(['payment_status' => 'pending']);
        }

        return redirect()->route('site.partner.task', $task)
            ->with('status', 'Task completed. Invoice generated and awaiting settlement.');
    }

    public function uploadProof(Request $request, VendorTask $task)
    {
        $vendor = $this->vendor();
        abort_unless($task->vendor_id === $vendor->id, 404);

        if ($task->task_type === 'asset_valuation') {
            return back()->withErrors([
                'file' => __('site.partner_portal.valuation_camera_only'),
            ]);
        }

        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:80'],
            'angle' => ['nullable', 'string', 'max:20'],
            'customer_asset_id' => ['nullable', 'integer'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $label = trim((string) ($data['label'] ?? ''));
        $payload = [];
        if ($label === '') {
            return back()->withErrors(['label' => 'Add a label for this file.']);
        }

        $path = $request->file('file')->store("vendor/{$vendor->id}/proofs", 'public');

        $create = [
            'vendor_id' => $vendor->id,
            'vendor_task_id' => $task->id,
            'label' => $label,
            'file_path' => $path,
            'mime' => $request->file('file')->getMimeType(),
            'size_bytes' => $request->file('file')->getSize(),
        ];
        if (($payload['doc_type'] ?? null) && (Schema::hasColumn('partner_documents', 'doc_type') || Schema::hasColumn('vendor_documents', 'doc_type'))) {
            $create['doc_type'] = $payload['doc_type'];
        }

        VendorDocument::create($create);

        if (! $task->proof_path) {
            $task->update(['proof_path' => $path]);
        }

        return back()->with('status', 'Proof uploaded.');
    }

    /* ------------------------------------------------------------------ */
    /* Documents */
    /* ------------------------------------------------------------------ */

    public function documents()
    {
        $vendor = $this->vendor();
        $documents = VendorDocument::where('partner_id', $vendor->id)
            ->with('task')
            ->latest()->paginate(20);

        return view('site.vendor.documents', compact('vendor', 'documents'));
    }

    public function uploadDocument(Request $request)
    {
        $vendor = $this->vendor();
        $types = array_keys(app(PartnerProfileService::class)->documentTypesFor($vendor));
        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'doc_type' => ['nullable', 'string', 'in:'.implode(',', $types)],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $path = $request->file('file')->store("vendor/{$vendor->id}/documents", 'public');

        $payload = [
            'vendor_id' => $vendor->id,
            'label' => $data['label'],
            'file_path' => $path,
            'mime' => $request->file('file')->getMimeType(),
            'size_bytes' => $request->file('file')->getSize(),
        ];
        if (Schema::hasColumn('partner_documents', 'doc_type') || Schema::hasColumn('vendor_documents', 'doc_type')) {
            $payload['doc_type'] = $data['doc_type'] ?? null;
        }

        VendorDocument::create($payload);

        return back()->with('status', __('site.partner_account.document_uploaded'));
    }

    public function membershipPayForm(Request $request)
    {
        $vendor = $this->vendor();
        $profile = app(PartnerProfileService::class);
        if (! $profile->isComplete($vendor)) {
            return redirect()->route('site.partner.profile')
                ->with('error', __('site.partner_portal.job_requires_profile'));
        }

        if ($vendor->isAffiliate()) {
            return redirect()->route('site.affiliate.membership.pay');
        }

        $membership = app(PartnerMembershipService::class);
        if (! $membership->requiresPayment($vendor) || $membership->isActive($vendor)) {
            return redirect()->route('site.partner.dashboard');
        }

        $payment = app(\App\Services\PartnerMembershipPaymentService::class)->open($vendor);
        $accounts = app(PaymentAccountService::class);
        $bankAccounts = $accounts->bankAccountsForDisplay('partner_membership', $payment->reference);
        $canSwitchToBank = (bool) $accounts->resolveBankAccount('partner_membership');

        return view('site.vendor.membership-pay', [
            'vendor' => $vendor,
            'payment' => $payment,
            'fee' => $membership->feeFor($vendor),
            'bankAccounts' => $bankAccounts,
            'canSwitchToBank' => $canSwitchToBank,
            'mobileDetails' => [],
            'payUrl' => route('site.partner.membership.checkout.pay', $payment),
            'statusUrl' => route('site.partner.membership.checkout.status', $payment),
            'retryUrl' => route('site.partner.membership.checkout.retry', $payment),
            'gateUrl' => route('site.partner.membership.checkout.gate', $payment),
            'successUrl' => route('site.partner.dashboard'),
        ]);
    }

    public function membershipPay(Request $request)
    {
        return redirect()->route('site.partner.membership.pay');
    }

    protected function redirectIfJobsBlocked(Vendor $vendor): ?\Illuminate\Http\RedirectResponse
    {
        $reason = app(PartnerProfileService::class)->jobBlockReason($vendor);
        if ($reason === 'profile') {
            return redirect()->route('site.partner.profile')
                ->with('error', __('site.partner_portal.job_requires_profile'));
        }
        if ($reason === 'payment') {
            $route = $vendor->isAffiliate() ? 'site.affiliate.membership.pay' : 'site.partner.membership.pay';

            return redirect()->route($route)
                ->with('error', __('site.partner_portal.job_requires_payment'));
        }

        return null;
    }

    /* ------------------------------------------------------------------ */
    /* Payments */
    /* ------------------------------------------------------------------ */

    public function payments()
    {
        $vendor = $this->vendor();
        $walletService = app(PartnerWalletService::class);
        $wallet = $walletService->summary($vendor);

        $payments = VendorPayment::where('partner_id', $vendor->id)
            ->with('task')->latest()->paginate(15);

        $totals = [
            'paid' => (int) VendorPayment::where('partner_id', $vendor->id)->where('status', 'paid')->sum('amount'),
            'pending' => (int) VendorPayment::where('partner_id', $vendor->id)->where('status', 'pending')->sum('amount'),
            'approved' => (int) round($wallet['approved']),
            'available' => (int) round($wallet['available']),
            'count' => VendorPayment::where('partner_id', $vendor->id)->count(),
        ];

        return view('site.vendor.payments', compact('vendor', 'payments', 'totals', 'wallet'));
    }

    public function requestPayout(Request $request)
    {
        $vendor = $this->vendor();
        $walletService = app(PartnerWalletService::class);
        $sourceType = $walletService->sourceTypeFor($vendor);

        if (app(RecoveryPartnerService::class)->isRecoveryPartner($vendor)) {
            return redirect()->route('site.partner.recovery-wallet');
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $payout = app(PartnerPayoutRequestService::class)->request(
                $vendor,
                $sourceType,
                (float) $data['amount'],
                $data['notes'] ?? null,
            );

            app(NotificationService::class)->notifyPartner(
                $vendor,
                'partner_payout_requested',
                [
                    'amount' => format_money($payout->amount),
                    'partner' => $vendor->name,
                ],
                route('site.partner.payments'),
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('status', __('site.partner_portal.payout_submitted'));
    }

    public function invoice(VendorPayment $payment)
    {
        $vendor = $this->vendor();
        abort_unless($payment->vendor_id === $vendor->id || $payment->partner_id === $vendor->id, 404);
        $payment->load('task');

        return view('site.vendor.invoice', compact('vendor', 'payment'));
    }

    /* ------------------------------------------------------------------ */
    /* Calendar */
    /* ------------------------------------------------------------------ */

    public function calendar()
    {
        $vendor = $this->vendor();

        $start = now()->startOfWeek();
        $end = now()->endOfWeek()->addWeeks(3);

        $items = $this->tasksQuery($vendor)
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [$start, $end])
            ->orderBy('due_at')->get();

        $today = $items->filter(fn ($t) => Carbon::parse($t->due_at)->isToday());
        $upcoming = $items->filter(fn ($t) => Carbon::parse($t->due_at)->isFuture() && ! Carbon::parse($t->due_at)->isToday());
        $overdue = $this->tasksQuery($vendor)
            ->whereNotIn('status', ['completed', 'cancelled', 'rejected'])
            ->whereNotNull('due_at')->where('due_at', '<', now()->startOfDay())->get();

        return view('site.vendor.calendar', compact('vendor', 'today', 'upcoming', 'overdue'));
    }

    /* ------------------------------------------------------------------ */
    /* Notifications */
    /* ------------------------------------------------------------------ */

    public function notifications()
    {
        $notifications = NotificationLog::query()
            ->when(
                Schema::hasColumn('notification_logs', 'user_id'),
                fn ($q) => $q->where('user_id', Auth::id()),
                fn ($q) => $q->where(function ($inner) {
                    $inner->where('recipient', Auth::user()?->email)
                        ->orWhere('recipient', Auth::user()?->phone);
                })
            )
            ->latest()
            ->paginate(20);

        return view('site.vendor.notifications', compact('notifications'));
    }

    /* ------------------------------------------------------------------ */
    /* Profile */
    /* ------------------------------------------------------------------ */

    public function profile(Request $request, ?string $section = null)
    {
        $vendor = $this->vendor();

        $section = $section ?: 'hub';
        $allowed = array_merge(['hub'], app(PartnerProfileService::class)->sectionsFor($vendor));

        if (! in_array($section, $allowed, true)) {
            return redirect()->route('site.partner.profile');
        }

        if ($vendor->isAffiliate()) {
            app(AffiliateService::class)->ensureCode($vendor);
        }

        $common = [
            'partner' => $vendor,
            'portal' => 'vendor',
            'profileRoute' => 'site.partner.profile',
            'updateRoute' => 'site.partner.profile.update',
            'layoutComponent' => 'site.vendor-layout',
            'eyebrow' => ucfirst(str_replace('_', ' ', $vendor->category ?? 'partner')),
            'accountTabs' => [
                ['key' => 'profile', 'label' => __('site.partner_account.tab_profile'), 'url' => route('site.partner.profile')],
                ['key' => 'documents', 'label' => __('site.partner_account.tab_documents'), 'url' => route('site.partner.documents')],
                ['key' => 'settings', 'label' => __('site.partner_account.tab_settings'), 'url' => route('site.partner.settings')],
            ],
        ];

        if ($section === 'hub') {
            return view('site.partner-account.hub', $common + [
                'title' => __('site.partner_account.hub_title'),
                'subtitle' => __('site.partner_account.hub_subtitle'),
            ]);
        }

        return view('site.partner-account.'.$section, $common + [
            'title' => __('site.partner_account.'.$section.'_section'),
            'canChangeCode' => $vendor->isAffiliate() ? app(AffiliateService::class)->canChangeCode($vendor) : false,
        ]);
    }

    public function settings()
    {
        $vendor = $this->vendor();

        return view('site.vendor.settings', compact('vendor'));
    }

    public function updateProfile(Request $request, string $section = 'personal')
    {
        $vendor = $this->vendor();

        if (! in_array($section, PartnerProfileService::SECTIONS, true)) {
            abort(404);
        }

        try {
            app(PartnerProfileService::class)->updateSection($vendor, $section, $request);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['affiliate_code' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Profile updated.');
    }

    /* ------------------------------------------------------------------ */
    /* Support */
    /* ------------------------------------------------------------------ */

    public function support()
    {
        $vendor = $this->vendor();
        $faqKey = match (true) {
            $vendor->isInsurance() => 'insurance',
            $vendor->isValuer() => 'valuer',
            $vendor->isGpsInstaller() => 'gps',
            app(RecoveryPartnerService::class)->isRecoveryPartner($vendor) => 'recovery',
            default => 'default',
        };
        $faqs = __('site.partner_portal.faq.'.$faqKey);
        if (! is_array($faqs)) {
            $faqs = __('site.partner_portal.faq.default');
        }

        return view('site.vendor.support', [
            'vendor' => $vendor,
            'faqs' => is_array($faqs) ? $faqs : [],
            'supportPhone' => support_contact('phone'),
            'supportEmail' => support_contact('email'),
            'supportWhatsapp' => support_contact('whatsapp'),
        ]);
    }
}
