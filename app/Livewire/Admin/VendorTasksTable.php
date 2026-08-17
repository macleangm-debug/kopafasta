<?php

namespace App\Livewire\Admin;

use App\Models\VendorTask;
use App\Services\PartnerTaskLifecycleService;
use App\Services\PartnerTaskReassignmentService;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class VendorTasksTable extends Component
{
    use WithPagination;

    #[Url(as: 'q')] public string $search = '';
    #[Url] public string $status = '';
    #[Url] public ?int $partner = null;
    #[Url] public string $sort = 'created_at';
    #[Url] public string $direction = 'desc';
    public int $perPage = 15;

    /** @var array<int, int|string> */
    public array $reassignTo = [];

    public ?string $notice = null;

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatus(): void { $this->resetPage(); }

    public function sortBy(string $col): void
    {
        $this->direction = $this->sort === $col
            ? ($this->direction === 'asc' ? 'desc' : 'asc')
            : 'asc';
        $this->sort = $col;
    }

    public function reassign(int $taskId): void
    {
        $task = VendorTask::query()->findOrFail($taskId);
        $actor = auth('admin')->user() ?? auth()->user();
        $service = app(PartnerTaskReassignmentService::class);

        abort_unless($actor && $service->can($actor, $task), 403);

        $to = (int) ($this->reassignTo[$taskId] ?? 0);

        try {
            $service->reassign(
                $task,
                $actor,
                $to > 0 ? $to : null,
                'Reassigned from partner tasks.',
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->addError('reassignTo.'.$taskId, $e->validator->errors()->first() ?: 'Could not reassign.');

            return;
        }

        unset($this->reassignTo[$taskId]);
        $this->notice = 'Task reassigned to another partner.';
    }

    public function close(int $taskId): void
    {
        $task = VendorTask::query()->with('loanApplication')->findOrFail($taskId);
        $actor = auth('admin')->user() ?? auth()->user();
        $service = app(PartnerTaskReassignmentService::class);

        abort_unless($actor && $service->canClose($actor, $task), 403);

        try {
            $service->close($task, $actor, 'Closed from partner tasks because the application is no longer active.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->addError('reassignTo.'.$taskId, $e->validator->errors()->first() ?: 'Could not close.');

            return;
        }

        $this->notice = 'Job closed. It is no longer ongoing.';
    }

    public function render()
    {
        app(PartnerTaskLifecycleService::class)->reconcileOpenTasksOnClosedFiles();

        $rows = VendorTask::query()
            ->with([
                'vendor',
                'assigner:id,name',
                'loanApplication.customer',
                'loanApplication.product:id,code,name',
                'loan.customer',
                'loan.product:id,code,name',
                'documents',
                'payment',
                'valuationAssignment',
                'recoveryAssignment',
            ])
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($qq) use ($term) {
                    $qq->where('task_type', 'like', $term)
                       ->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', $term));
                });
            })
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->partner, fn ($q) => $q->where('partner_id', $this->partner))
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->perPage);

        $statuses = ['assigned', 'in_progress', 'completed', 'failed', 'cancelled'];
        $reassign = app(PartnerTaskReassignmentService::class);
        $regionCoverage = app(\App\Services\PartnerRegionCoverage::class);
        $actor = auth('admin')->user() ?? auth()->user();
        $candidates = [];
        $canReassign = [];
        $canClose = [];
        foreach ($rows as $row) {
            $can = $actor && $reassign->can($actor, $row);
            $canReassign[$row->id] = $can;
            $canClose[$row->id] = $actor && $reassign->canClose($actor, $row);
            $candidates[$row->id] = $can ? $reassign->candidates($row) : collect();
        }

        return view('livewire.admin.vendor-tasks-table', compact('rows', 'statuses', 'candidates', 'canReassign', 'canClose', 'regionCoverage'));
    }
}
