<?php

namespace App\Livewire\Admin;

use App\Models\VendorTask;
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

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatus(): void { $this->resetPage(); }

    public function sortBy(string $col): void
    {
        $this->direction = $this->sort === $col
            ? ($this->direction === 'asc' ? 'desc' : 'asc')
            : 'asc';
        $this->sort = $col;
    }

    public function render()
    {
        $rows = VendorTask::query()
            ->with(['vendor', 'loanApplication:id,application_number', 'loan:id,loan_number', 'documents', 'payment'])
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

        return view('livewire.admin.vendor-tasks-table', compact('rows', 'statuses'));
    }
}
