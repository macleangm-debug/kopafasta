<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Models\Vendor;
use App\Models\VendorDocument;
use App\Models\VendorPayment;
use App\Models\VendorTask;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VendorController extends Controller
{
    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    protected function vendor(): Vendor
    {
        $user = Auth::user();
        $vendor = Vendor::where('user_id', $user->id)->first();
        if (! $vendor) {
            $vendor = Vendor::create([
                'vendor_number' => 'PTR-'.strtoupper(Str::random(6)),
                'name'          => $user->name,
                'category'      => 'gps_installer',
                'phone'         => $user->phone,
                'email'         => $user->email,
                'status'        => 'active',
                'user_id'       => $user->id,
            ]);
        }
        return $vendor;
    }

    protected function tasksQuery(Vendor $vendor)
    {
        return VendorTask::where('partner_id', $vendor->id);
    }

    /* ------------------------------------------------------------------ */
    /* Dashboard                                                           */
    /* ------------------------------------------------------------------ */

    public function dashboard()
    {
        $vendor = $this->vendor();

        $stats = [
            'assigned'      => $this->tasksQuery($vendor)->where('status', 'assigned')->count(),
            'in_progress'   => $this->tasksQuery($vendor)->where('status', 'in_progress')->count(),
            'completed_mo'  => $this->tasksQuery($vendor)->where('status', 'completed')
                                ->where('completed_at', '>=', now()->startOfMonth())->count(),
            'payments_pend' => (int) VendorPayment::where('partner_id', $vendor->id)
                                ->where('status', 'pending')->sum('amount'),
            'earnings'      => (int) VendorPayment::where('partner_id', $vendor->id)
                                ->where('status', 'paid')->sum('amount'),
        ];

        $upcoming = $this->tasksQuery($vendor)
            ->whereIn('status', ['assigned', 'in_progress'])
            ->orderByRaw('COALESCE(due_at, created_at) ASC')
            ->limit(5)->get();

        $notifications = NotificationLog::where('user_id', Auth::id())
            ->latest()->limit(4)->get();

        $affiliateStats = null;
        $affiliateShare = null;
        $affiliateLinks = null;

        if ($vendor->category === 'affiliate') {
            $affiliateService = app(\App\Services\AffiliateService::class);
            $affiliateService->ensureCode($vendor);
            $vendor->refresh();
            $affiliateStats = $affiliateService->stats($vendor);
            $affiliateShare = $affiliateService->renderMessage($vendor, 'share_template');
            $affiliateLinks = $affiliateService->messageContext($vendor);
        }

        return view('site.vendor.dashboard', compact(
            'vendor', 'stats', 'upcoming', 'notifications',
            'affiliateStats', 'affiliateShare', 'affiliateLinks',
        ));
    }

    /* ------------------------------------------------------------------ */
    /* Tasks                                                               */
    /* ------------------------------------------------------------------ */

    public function tasks(Request $request)
    {
        $vendor = $this->vendor();
        $status = $request->string('status')->toString();

        $q = $this->tasksQuery($vendor)->latest();
        if ($status !== '' && $status !== 'all') $q->where('status', $status);

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

        if (! app(\App\Services\RecoveryPartnerService::class)->isRecoveryPartner($vendor)) {
            abort(403, 'Recovery partner access only.');
        }

        $status = $request->string('status')->toString();

        $query = \App\Models\RecoveryAssignment::query()
            ->with(['arrearCase.loan.customer', 'vendorTask'])
            ->where('partner_id', $vendor->id)
            ->latest('assigned_at');

        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $assignments = $query->paginate(15)->withQueryString();

        return view('site.vendor.recovery-cases', compact('vendor', 'assignments', 'status'));
    }

    public function recoveryCase(\App\Models\RecoveryAssignment $recoveryAssignment)
    {
        $vendor = $this->vendor();

        if (! app(\App\Services\RecoveryPartnerService::class)->isRecoveryPartner($vendor)) {
            abort(403, 'Recovery partner access only.');
        }

        $portal = app(\App\Services\RecoveryPartnerPortalService::class);
        $portal->assertVendorOwnsAssignment($recoveryAssignment, $vendor);

        $case = $portal->caseViewData($recoveryAssignment);

        return view('site.vendor.recovery-case', array_merge(
            compact('vendor', 'recoveryAssignment'),
            $case,
            ['assignment' => $recoveryAssignment],
        ));
    }

    public function startRecoveryCase(\App\Models\RecoveryAssignment $recoveryAssignment)
    {
        $vendor = $this->vendor();
        $portal = app(\App\Services\RecoveryPartnerPortalService::class);
        $portal->assertVendorOwnsAssignment($recoveryAssignment, $vendor);

        $portal->startCase($recoveryAssignment, $vendor, Auth::user());

        return back()->with('status', 'Recovery case marked in progress.');
    }

    public function recoveryCaseAction(Request $request, \App\Models\RecoveryAssignment $recoveryAssignment)
    {
        $vendor = $this->vendor();
        $portal = app(\App\Services\RecoveryPartnerPortalService::class);
        $portal->assertVendorOwnsAssignment($recoveryAssignment, $vendor);

        $data = $request->validate([
            'action'           => ['required', 'string', 'max:40'],
            'notes'            => ['nullable', 'string', 'max:2000'],
            'file'             => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'auction_proceeds' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        $portal->recordAction(
            $recoveryAssignment,
            $vendor,
            Auth::user(),
            $data['action'],
            $data['notes'] ?? null,
            $request->file('file'),
            isset($data['auction_proceeds']) ? (float) $data['auction_proceeds'] : null,
        );

        $message = in_array($data['action'], ['resolved', 'sold', 'gps_removed'], true)
            ? 'Recovery case completed.'
            : 'Action recorded.';

        return redirect()
            ->route('site.partner.recovery-case', $recoveryAssignment)
            ->with('status', $message);
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
        $task->load(['documents', 'payment', 'loan', 'loanApplication']);
        return view('site.vendor.task', compact('vendor', 'task'));
    }

    public function acceptTask(VendorTask $task)
    {
        $vendor = $this->vendor();
        abort_unless($task->vendor_id === $vendor->id, 404);
        $task->update(['status' => 'in_progress', 'accepted_at' => now()]);
        $this->markValuationInProgress($task);

        return back()->with('status', 'Task accepted. You may start work now.');
    }

    public function startTask(VendorTask $task)
    {
        $vendor = $this->vendor();
        abort_unless($task->vendor_id === $vendor->id, 404);
        $task->update(['status' => 'in_progress', 'started_at' => now()]);
        $this->markValuationInProgress($task);

        return back()->with('status', 'Marked as in progress.');
    }

    private function markValuationInProgress(VendorTask $task): void
    {
        if ($task->task_type !== 'asset_valuation') {
            return;
        }

        $assignment = \App\Models\ValuationAssignment::query()
            ->where('partner_task_id', $task->id)
            ->first();

        if ($assignment) {
            app(\App\Services\ValuationPartnerService::class)->markInProgress($assignment);
        }
    }

    public function completeTask(Request $request, VendorTask $task)
    {
        $vendor = $this->vendor();
        abort_unless($task->vendor_id === $vendor->id, 404);

        $data = $request->validate([
            'gps_serial'        => ['nullable', 'string', 'max:60'],
            'notes'             => ['nullable', 'string', 'max:1000'],
            'market_value'      => ['nullable', 'numeric', 'min:0'],
            'forced_sale_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($task->task_type === 'asset_valuation') {
            $assignment = \App\Models\ValuationAssignment::query()
                ->where('partner_task_id', $task->id)
                ->first();

            if ($assignment && filled($data['market_value'] ?? null) && filled($data['forced_sale_value'] ?? null)) {
                app(\App\Services\ValuationPartnerService::class)->complete(
                    $assignment,
                    (float) $data['market_value'],
                    (float) $data['forced_sale_value'],
                    $data['notes'] ?? null,
                );
            } else {
                $task->update([
                    'status'       => 'completed',
                    'completed_at' => now(),
                    'notes'        => $data['notes'] ?? $task->notes,
                ]);
            }

            return redirect()->route('site.partner.task', $task)
                ->with('status', 'Valuation submitted.');
        }

        $task->update([
            'status'       => 'completed',
            'completed_at' => now(),
            'gps_serial'   => $data['gps_serial'] ?? $task->gps_serial,
            'notes'        => $data['notes'] ?? $task->notes,
        ]);

        if (str_contains((string) $task->task_type, 'gps')) {
            app(\App\Services\AssetReservationService::class)->syncGpsFromTask($task->fresh());
        }

        // auto-issue invoice if fee set and no payment yet
        if ($task->fee_amount > 0 && ! $task->payment()->exists()) {
            app(\App\Services\PartnerSettlementService::class)->accrue(
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

        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'file'  => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $path = $request->file('file')->store("vendor/{$vendor->id}/proofs", 'public');

        VendorDocument::create([
            'vendor_id'      => $vendor->id,
            'vendor_task_id' => $task->id,
            'label'          => $data['label'],
            'file_path'      => $path,
            'mime'           => $request->file('file')->getMimeType(),
            'size_bytes'     => $request->file('file')->getSize(),
        ]);

        if (! $task->proof_path) $task->update(['proof_path' => $path]);

        return back()->with('status', 'Proof uploaded.');
    }

    /* ------------------------------------------------------------------ */
    /* Documents                                                           */
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
        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'file'  => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $path = $request->file('file')->store("vendor/{$vendor->id}/documents", 'public');

        VendorDocument::create([
            'vendor_id'  => $vendor->id,
            'label'      => $data['label'],
            'file_path'  => $path,
            'mime'       => $request->file('file')->getMimeType(),
            'size_bytes' => $request->file('file')->getSize(),
        ]);

        return back()->with('status', 'Document uploaded.');
    }

    /* ------------------------------------------------------------------ */
    /* Payments                                                            */
    /* ------------------------------------------------------------------ */

    public function payments()
    {
        $vendor = $this->vendor();
        $payments = VendorPayment::where('partner_id', $vendor->id)
            ->with('task')->latest()->paginate(15);

        $totals = [
            'paid'    => (int) VendorPayment::where('partner_id', $vendor->id)->where('status', 'paid')->sum('amount'),
            'pending' => (int) VendorPayment::where('partner_id', $vendor->id)->where('status', 'pending')->sum('amount'),
            'count'   => VendorPayment::where('partner_id', $vendor->id)->count(),
        ];

        return view('site.vendor.payments', compact('vendor', 'payments', 'totals'));
    }

    public function invoice(VendorPayment $payment)
    {
        $vendor = $this->vendor();
        abort_unless($payment->vendor_id === $vendor->id, 404);
        $payment->load('task');
        return view('site.vendor.invoice', compact('vendor', 'payment'));
    }

    /* ------------------------------------------------------------------ */
    /* Calendar                                                            */
    /* ------------------------------------------------------------------ */

    public function calendar()
    {
        $vendor = $this->vendor();

        $start = now()->startOfWeek();
        $end   = now()->endOfWeek()->addWeeks(3);

        $items = $this->tasksQuery($vendor)
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [$start, $end])
            ->orderBy('due_at')->get();

        $today    = $items->filter(fn ($t) => Carbon::parse($t->due_at)->isToday());
        $upcoming = $items->filter(fn ($t) => Carbon::parse($t->due_at)->isFuture() && ! Carbon::parse($t->due_at)->isToday());
        $overdue  = $this->tasksQuery($vendor)
            ->whereNotIn('status', ['completed', 'cancelled', 'rejected'])
            ->whereNotNull('due_at')->where('due_at', '<', now()->startOfDay())->get();

        return view('site.vendor.calendar', compact('vendor', 'today', 'upcoming', 'overdue'));
    }

    /* ------------------------------------------------------------------ */
    /* Notifications                                                       */
    /* ------------------------------------------------------------------ */

    public function notifications()
    {
        $notifications = NotificationLog::where('user_id', Auth::id())
            ->latest()->paginate(20);
        return view('site.vendor.notifications', compact('notifications'));
    }

    /* ------------------------------------------------------------------ */
    /* Profile                                                             */
    /* ------------------------------------------------------------------ */

    public function profile()
    {
        $vendor = $this->vendor();
        return view('site.vendor.profile', compact('vendor'));
    }

    public function updateProfile(Request $request)
    {
        $vendor = $this->vendor();
        $data = $request->validate([
            'name'    => ['required', 'string', 'max:120'],
            'phone'   => ['nullable', 'string', 'max:30'],
            'email'   => ['nullable', 'email', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'affiliate_selfie' => ['nullable', 'image', 'max:5120'],
            'affiliate_id'     => ['nullable', 'image', 'max:5120'],
            'affiliate_photo'  => ['nullable', 'image', 'max:5120'],
        ]);
        unset($data['affiliate_selfie'], $data['affiliate_id'], $data['affiliate_photo']);

        if ($vendor->isAffiliate()) {
            if ($request->hasFile('affiliate_selfie')) {
                $data['affiliate_selfie_path'] = $request->file('affiliate_selfie')->store("partners/{$vendor->id}/kyc", 'public');
            }
            if ($request->hasFile('affiliate_id')) {
                $data['affiliate_id_path'] = $request->file('affiliate_id')->store("partners/{$vendor->id}/kyc", 'public');
            }
            if ($request->hasFile('affiliate_photo')) {
                $data['affiliate_photo_path'] = $request->file('affiliate_photo')->store("partners/{$vendor->id}/kyc", 'public');
            }
            if (! empty($data['affiliate_selfie_path']) && ! empty($data['affiliate_id_path'])) {
                $data['affiliate_kyc_status'] = 'submitted';
            }
        }

        $vendor->update($data);
        return back()->with('status', 'Profile updated.');
    }

    /* ------------------------------------------------------------------ */
    /* Support                                                             */
    /* ------------------------------------------------------------------ */

    public function support()
    {
        return view('site.vendor.support');
    }
}
