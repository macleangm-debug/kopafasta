<x-site.vendor-layout :title="$task->task_type === 'vehicle_insurance' ? __('site.partner_portal.cover_job_detail_title') : 'Task detail'" active="tasks">
    @php
        $badge = match ($task->status) {
            'assigned'    => 'bg-amber-100 text-amber-700',
            'in_progress' => 'bg-indigo-100 text-brand',
            'completed'   => 'bg-emerald-100 text-emerald-700',
            'rejected'    => 'bg-red-100 text-red-700',
            'cancelled'   => 'bg-gray-100 text-gray-600',
            default       => 'bg-gray-100 text-gray-600',
        };
        $priority = $task->priorityMeta();
        $priorityBadge = match ($priority['tone']) {
            'red'    => 'bg-red-100 text-red-700',
            'amber'  => 'bg-amber-100 text-amber-700',
            'indigo' => 'bg-indigo-100 text-brand',
            default  => 'bg-gray-100 text-gray-600',
        };
        $loan = $task->loan;
        $application = $task->loanApplication;
        $asset = $application?->assetReservation?->asset;
        $taskMeta = [];
        if (is_string($task->notes)) {
            $decoded = json_decode($task->notes, true);
            $taskMeta = is_array($decoded) ? $decoded : [];
        }
        $collateralAssets = collect();
        if (! empty($taskMeta['customer_asset_ids']) && is_array($taskMeta['customer_asset_ids'])) {
            $collateralAssets = \App\Models\CustomerAsset::query()
                ->whereIn('id', $taskMeta['customer_asset_ids'])
                ->get();
        }
        if ($collateralAssets->isEmpty() && ! empty($taskMeta['customer_asset_id'])) {
            $one = \App\Models\CustomerAsset::query()->find($taskMeta['customer_asset_id']);
            if ($one) {
                $collateralAssets = collect([$one]);
            }
        }
        if ($collateralAssets->isEmpty() && $application && $task->task_type === 'asset_valuation') {
            $ids = app(\App\Services\CustomerAssetService::class)->onLoanAssetIds($application);
            $collateralAssets = $ids === []
                ? collect()
                : \App\Models\CustomerAsset::query()->whereIn('id', $ids)->get();
        }
        $collateralAsset = $collateralAssets->first();
        $assetProfile = $taskMeta['asset_profile'] ?? null;
        if ($collateralAsset) {
            $assetProfile = app(\App\Services\CollateralInsurancePartnerService::class)->assetProfilePayload($collateralAsset);
        }
        $isInsurance = $task->task_type === 'vehicle_insurance';
    @endphp

    @if ($isInsurance)
        @include('site.vendor._cover_job_detail')
    @else
    <div class="mb-5">
        <a href="{{ route('site.partner.tasks') }}" data-kf-motion="pop" class="text-sm text-brand hover:underline">← Back to tasks</a>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-extrabold" style="view-transition-name: kf-task-{{ $task->id }}">{{ ucfirst(str_replace('_',' ', $task->task_type)) }}</h1>
            <p class="text-xs text-gray-500">Task #{{ $task->id }} · Created {{ $task->created_at->format('d M Y') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $priorityBadge }}">{{ $priority['label'] }} priority</span>
            <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $badge }}">{{ str_replace('_',' ', $task->status) }}</span>
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        {{-- Left: details --}}
        <div class="lg:col-span-2 space-y-4">
            <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                <h2 class="font-bold mb-3">Task overview</h2>
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-gray-500 text-xs">Task type</dt><dd class="font-medium">{{ ucfirst(str_replace('_', ' ', $task->task_type)) }}</dd></div>
                    <div><dt class="text-gray-500 text-xs">Status</dt><dd class="font-medium capitalize">{{ str_replace('_', ' ', $task->status) }}</dd></div>
                    <div><dt class="text-gray-500 text-xs">Due date</dt><dd class="font-medium">{{ $task->due_at ? $task->due_at->format('d M Y H:i') : '—' }}</dd></div>
                    <div><dt class="text-gray-500 text-xs">Priority</dt><dd class="font-medium">{{ $priority['label'] }}</dd></div>
                    <div><dt class="text-gray-500 text-xs">Fee</dt><dd class="font-medium">{{ format_money($task->fee_amount) }}</dd></div>
                    <div><dt class="text-gray-500 text-xs">Completion</dt><dd class="font-medium">{{ $task->completed_at ? $task->completed_at->format('d M Y H:i') : 'Not completed' }}</dd></div>
                </dl>
            </div>

            @if ($loan || $application)
                <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                    <h2 class="font-bold mb-3">Related loan information</h2>
                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        @if ($loan)
                            <div><dt class="text-gray-500 text-xs">Loan ID</dt><dd class="font-medium font-mono">{{ $loan->loan_number ?? '#'.$loan->id }}</dd></div>
                            <div><dt class="text-gray-500 text-xs">Loan status</dt><dd class="font-medium capitalize">{{ str_replace('_', ' ', $loan->status) }}</dd></div>
                            <div><dt class="text-gray-500 text-xs">Customer</dt><dd class="font-medium">{{ $loan->customer?->name ?? $task->customer_name ?? '—' }}</dd></div>
                            <div><dt class="text-gray-500 text-xs">Customer phone</dt><dd class="font-medium">{{ $loan->customer?->phone ?? $task->customer_phone ?? '—' }}</dd></div>
                            <div><dt class="text-gray-500 text-xs">Loan amount</dt><dd class="font-medium">{{ format_money($loan->approved_amount ?? $loan->principal_amount) }}</dd></div>
                            <div><dt class="text-gray-500 text-xs">Outstanding balance</dt><dd class="font-medium">{{ format_money($loan->outstanding_balance) }}</dd></div>
                        @elseif ($application)
                            <div><dt class="text-gray-500 text-xs">Application ID</dt><dd class="font-medium">#{{ $application->id }}</dd></div>
                            <div><dt class="text-gray-500 text-xs">Application stage</dt><dd class="font-medium capitalize">{{ str_replace('_', ' ', $application->status) }}</dd></div>
                            <div><dt class="text-gray-500 text-xs">Customer</dt><dd class="font-medium">{{ $application->customer?->name ?? $task->customer_name ?? '—' }}</dd></div>
                            <div><dt class="text-gray-500 text-xs">Requested amount</dt><dd class="font-medium">{{ format_money($application->requested_amount) }}</dd></div>
                        @endif
                        @if ($asset)
                            <div class="col-span-2"><dt class="text-gray-500 text-xs">Asset financed</dt><dd class="font-medium">{{ $asset->title }} · {{ format_money($asset->asset_value) }}</dd></div>
                        @elseif ($task->vehicle_details)
                            <div class="col-span-2"><dt class="text-gray-500 text-xs">Asset / vehicle</dt><dd class="font-medium">{{ $task->vehicle_details }}</dd></div>
                        @endif
                    </dl>
                </div>
            @endif

            <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                <h2 class="font-bold mb-3">Customer & location</h2>
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-gray-500 text-xs">Customer</dt><dd class="font-medium">{{ $task->customer_name ?: '—' }}</dd></div>
                    <div><dt class="text-gray-500 text-xs">Phone</dt><dd class="font-medium">{{ $task->customer_phone ?: '—' }}</dd></div>
                    <div class="col-span-2"><dt class="text-gray-500 text-xs">Vehicle</dt><dd class="font-medium">{{ $task->vehicle_details ?: '—' }}</dd></div>
                    <div class="col-span-2"><dt class="text-gray-500 text-xs">Location</dt><dd class="font-medium">{{ $task->location ?: '—' }}</dd></div>
                    <div><dt class="text-gray-500 text-xs">Due</dt><dd class="font-medium">{{ $task->due_at ? $task->due_at->format('d M Y H:i') : '—' }}</dd></div>
                    <div><dt class="text-gray-500 text-xs">Fee</dt><dd class="font-medium">{{ format_money($task->fee_amount) }}</dd></div>
                </dl>
                @if ($task->instructions)
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <p class="text-xs text-gray-500 mb-1">Action required</p>
                        <p class="text-sm whitespace-pre-line">{{ $task->instructions }}</p>
                    </div>
                @endif
                @if ($task->due_at)
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <p class="text-xs text-gray-500 mb-1">Submission deadline</p>
                        <p class="text-sm font-medium">{{ $task->due_at->format('d M Y H:i') }}</p>
                    </div>
                @endif
                @if ($task->documents->isNotEmpty())
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <p class="text-xs text-gray-500 mb-2">Required documents submitted</p>
                        <ul class="space-y-1 text-sm">
                            @foreach ($task->documents as $d)
                                <li class="flex items-center justify-between gap-2">
                                    <span>{{ $d->label }}</span>
                                    <x-site.document-view-button :url="asset('storage/'.$d->file_path)" label="View" class="text-brand hover:underline text-xs" />
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            {{-- Upload proof --}}
            @if ($task->status !== 'completed' && $task->status !== 'cancelled')
                <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                    <h2 class="font-bold mb-3">{{ $task->task_type === 'asset_valuation' ? 'Upload inspection photos' : 'Upload proof' }}</h2>
                    @if ($task->task_type === 'asset_valuation' && $collateralAssets->isNotEmpty())
                        <p class="text-xs text-gray-500 mb-3">Take the same angles as the borrower profile, including owner with asset. When there is more than one pledged asset, pick which asset each photo belongs to.</p>
                        @foreach ($collateralAssets as $collateralAsset)
                            @php
                                $angleLabels = \App\Models\CustomerAsset::photoAngleLabels($collateralAsset->asset_type);
                                $borrowerAngles = $collateralAsset->photosByAngle();
                            @endphp
                            <p class="text-sm font-semibold text-gray-900 mb-2">{{ $collateralAsset->label }}</p>
                            <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 mb-4">
                                @foreach ($angleLabels as $angle => $angleLabel)
                                    <div class="rounded-xl overflow-hidden ring-1 ring-gray-200 bg-gray-50">
                                        @if (! empty($borrowerAngles[$angle]))
                                            <img src="{{ asset('storage/'.$borrowerAngles[$angle]) }}" alt="{{ $angleLabel }}" class="h-24 w-full object-cover">
                                        @else
                                            <div class="h-24 grid place-items-center text-[11px] text-gray-500 px-2 text-center">No borrower {{ strtolower($angleLabel) }} photo</div>
                                        @endif
                                        <p class="text-[10px] font-semibold uppercase tracking-wide text-center py-1">Borrower · {{ $angleLabel }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    @endif
                    <form method="POST" action="{{ route('site.partner.task.proof', $task) }}" enctype="multipart/form-data" class="space-y-3">
                        @csrf
                        @if ($task->task_type === 'asset_valuation')
                            @php $angleLabels = \App\Models\CustomerAsset::photoAngleLabels($collateralAsset?->asset_type); @endphp
                            @if ($collateralAssets->count() > 1)
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Which asset?</label>
                                    <select name="customer_asset_id" required class="w-full rounded-lg border-gray-300 text-sm">
                                        <option value="">Select asset…</option>
                                        @foreach ($collateralAssets as $opt)
                                            <option value="{{ $opt->id }}">{{ $opt->label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @elseif ($collateralAsset)
                                <input type="hidden" name="customer_asset_id" value="{{ $collateralAsset->id }}">
                            @endif
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Photo angle</label>
                                <select name="angle" required class="w-full rounded-lg border-gray-300 focus:border-brand/500 focus:ring-brand/500 text-sm">
                                    <option value="">Select angle…</option>
                                    @foreach ($angleLabels as $angle => $angleLabel)
                                        <option value="{{ $angle }}">{{ $angleLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @else
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">What is it? (e.g. Installation photo)</label>
                                <input name="label" required class="w-full rounded-lg border-gray-300 focus:border-brand/500 focus:ring-brand/500 text-sm"
                                       placeholder="Proof label">
                            </div>
                        @endif
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">File (image or PDF, max 5MB)</label>
                            <input type="file" name="file" required accept=".jpg,.jpeg,.png,.pdf" class="w-full text-sm">
                        </div>
                        <button class="rounded-lg bg-brand text-white text-sm font-semibold px-4 py-2 hover:bg-brand-light">Upload</button>
                    </form>

                    @if ($task->documents->isNotEmpty())
                        <ul class="mt-5 divide-y divide-gray-100">
                            @foreach ($task->documents as $d)
                                <li class="py-2 flex items-center justify-between text-sm">
                                    <span class="truncate">{{ $d->label }}</span>
                                    <x-site.document-view-button :url="asset('storage/'.$d->file_path)" label="View" class="text-brand hover:underline text-xs" />
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                {{-- Complete task --}}
                <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                    <h2 class="font-bold mb-3">
                        @if ($task->task_type === 'asset_valuation')
                            Submit valuation report
                        @else
                            Mark complete
                        @endif
                    </h2>
                    @if ($task->task_type === 'asset_valuation')
                        <p class="text-xs text-gray-500 mb-4">Upload photos above, then enter market and forced sale values from your physical inspection.</p>
                    @endif
                    <form method="POST" action="{{ route('site.partner.task.complete', $task) }}" class="space-y-3"
                          @submit.prevent="window.confirmForm($el, {
                              title: @js(match ($task->task_type) {
                                  'asset_valuation' => __('site.partner_portal.confirm.valuation_title'),
                                  default => str_contains((string) $task->task_type, 'gps')
                                      ? __('site.partner_portal.confirm.gps_complete_title')
                                      : __('site.partner_portal.confirm.task_complete_title'),
                              }),
                              message: @js(match ($task->task_type) {
                                  'asset_valuation' => __('site.partner_portal.confirm.valuation_message'),
                                  default => str_contains((string) $task->task_type, 'gps')
                                      ? __('site.partner_portal.confirm.gps_complete_message')
                                      : __('site.partner_portal.confirm.task_complete_message'),
                              }),
                              confirmLabel: @js(__('site.partner_portal.confirm.task_complete_button')),
                              tone: 'warning',
                              confirmClass: 'bg-emerald-600 hover:bg-emerald-700 text-white',
                          })">
                        @csrf
                        @if ($task->task_type === 'asset_valuation')
                            @if ($collateralAssets->count() > 1)
                                @foreach ($collateralAssets as $valAsset)
                                    <div class="rounded-xl ring-1 ring-gray-200 p-3 space-y-2">
                                        <p class="text-sm font-semibold text-gray-900">{{ $valAsset->label }}</p>
                                        <div>
                                            <label class="block text-xs text-gray-500 mb-1">Market value (TZS)</label>
                                            <input name="values[{{ $valAsset->id }}][market_value]" type="number" min="0" step="1000" required class="w-full rounded-lg border-gray-300 text-sm">
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-500 mb-1">Forced sale value (TZS)</label>
                                            <input name="values[{{ $valAsset->id }}][forced_sale_value]" type="number" min="0" step="1000" required class="w-full rounded-lg border-gray-300 text-sm">
                                        </div>
                                    </div>
                                @endforeach
                                <input type="hidden" name="market_value" value="0">
                                <input type="hidden" name="forced_sale_value" value="0">
                            @else
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Market value (TZS)</label>
                                    <input name="market_value" type="number" min="0" step="1000" required class="w-full rounded-lg border-gray-300 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Forced sale value (TZS)</label>
                                    <input name="forced_sale_value" type="number" min="0" step="1000" required class="w-full rounded-lg border-gray-300 text-sm">
                                </div>
                            @endif
                        @endif
                        @if (str_contains($task->task_type, 'gps'))
                            @php
                                $gpsProviders = app(\App\Services\GpsDeviceService::class)->providerOptions();
                                $defaultProvider = old('gps_provider', $task->gps_provider ?: app(\App\Services\GpsDeviceService::class)->defaultProvider());
                            @endphp
                            <div class="rounded-xl border border-sky-100 bg-sky-50/50 p-3 space-y-3">
                                <p class="text-xs font-semibold text-sky-950">GPS device (tied to this loan)</p>
                                <p class="text-[11px] text-sky-900/80">Enter this unit’s own tracking URL from your provider portal. It is saved on the loan application — not as a global integration.</p>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">GPS serial number *</label>
                                    <input name="gps_serial" value="{{ old('gps_serial', $task->gps_serial) }}" required
                                           class="w-full rounded-lg border-gray-300 text-sm" placeholder="Device / unit serial">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">GPS provider</label>
                                    <select name="gps_provider" class="w-full rounded-lg border-gray-300 text-sm">
                                        @foreach ($gpsProviders as $key => $label)
                                            <option value="{{ $key }}" @selected($defaultProvider === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Provider device / IMEI (optional)</label>
                                    <input name="gps_device_id" value="{{ old('gps_device_id', $task->gps_device_id) }}"
                                           class="w-full rounded-lg border-gray-300 text-sm" placeholder="IMEI or portal device ID">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Device tracking URL *</label>
                                    <input name="gps_tracking_url" type="url" value="{{ old('gps_tracking_url', $task->gps_tracking_url) }}" required
                                           class="w-full rounded-lg border-gray-300 text-sm"
                                           placeholder="https://… this device’s map/share link">
                                    <p class="mt-1 text-[11px] text-gray-500">Paste the live link for <em>this</em> device only. Credit and recovery will use it when map viewing is enabled.</p>
                                </div>
                            </div>
                        @endif
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Notes (optional)</label>
                            <textarea name="notes" rows="3" class="w-full rounded-lg border-gray-300 text-sm">{{ old('notes') }}</textarea>
                        </div>
                        <button class="rounded-lg bg-emerald-600 text-white text-sm font-semibold px-4 py-2 hover:bg-emerald-700">Complete task</button>
                    </form>
                </div>
            @else
                @if ($task->documents->isNotEmpty())
                    <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                        <h2 class="font-bold mb-3">Uploaded proof</h2>
                        <ul class="divide-y divide-gray-100">
                            @foreach ($task->documents as $d)
                                <li class="py-2 flex items-center justify-between text-sm">
                                    <span class="truncate">{{ $d->label }}</span>
                                    <x-site.document-view-button :url="asset('storage/'.$d->file_path)" label="View" class="text-brand hover:underline text-xs" />
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endif
        </div>

        {{-- Right: actions --}}
        <div class="space-y-4">
            <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                <h3 class="font-bold mb-3">Actions</h3>
                <div class="space-y-2">
                    @if ($task->status === 'assigned')
                        <form method="POST" action="{{ route('site.partner.task.accept', $task) }}"
                              @submit.prevent="window.confirmForm($el, {
                                  title: @js(__('site.partner_portal.confirm.accept_task_title')),
                                  message: @js(__('site.partner_portal.confirm.accept_task_message')),
                                  confirmLabel: @js(__('site.partner_portal.confirm.accept_task_button')),
                                  tone: 'confirm',
                              })">
                            @csrf
                            <button class="w-full rounded-lg bg-brand text-white text-sm font-semibold py-2 hover:bg-brand-light">
                                Accept task
                            </button>
                        </form>
                    @endif
                    @if (in_array($task->status, ['assigned', 'in_progress']))
                        <form method="POST" action="{{ route('site.partner.task.start', $task) }}"
                              @submit.prevent="window.confirmForm($el, {
                                  title: @js(__('site.partner_portal.confirm.start_task_title')),
                                  message: @js(__('site.partner_portal.confirm.start_task_message')),
                                  confirmLabel: @js(__('site.partner_portal.confirm.start_task_button')),
                                  tone: 'info',
                              })">
                            @csrf
                            <button class="w-full rounded-lg bg-gray-900 text-white text-sm font-semibold py-2 hover:bg-black">Start work</button>
                        </form>
                    @endif
                </div>
                <div class="mt-4 pt-4 border-t border-gray-100 text-xs space-y-1 text-gray-500">
                    @if ($task->accepted_at)<p>Accepted {{ $task->accepted_at->format('d M H:i') }}</p>@endif
                    @if ($task->started_at)<p>Started {{ $task->started_at->format('d M H:i') }}</p>@endif
                    @if ($task->completed_at)<p>Completed {{ $task->completed_at->format('d M H:i') }}</p>@endif
                </div>
            </div>

            @if ($task->payment)
                <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                    <h3 class="font-bold mb-3">Payment</h3>
                    <p class="text-sm"><span class="text-gray-500">Invoice:</span> <span class="font-mono">{{ $task->payment->invoice_number }}</span></p>
                    <p class="text-sm"><span class="text-gray-500">Amount:</span> {{ format_money($task->payment->amount) }}</p>
                    @php $pc = $task->payment->status === 'paid' ? 'emerald' : 'amber'; @endphp
                    <p class="mt-1"><span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-{{ $pc }}-100 text-{{ $pc }}-700">{{ $task->payment->status }}</span></p>
                    <a href="{{ route('site.partner.invoice', $task->payment) }}" class="block mt-3 text-sm text-brand hover:underline">View invoice →</a>
                </div>
            @endif
        </div>
    </div>
    @endif
</x-site.vendor-layout>
