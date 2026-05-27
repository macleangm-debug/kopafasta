<?php

namespace App\Livewire\Admin;

use App\Models\LoanApplication;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class LoanApplicationsTable extends Component
{
    use WithPagination;

    #[Url(as: 'q')] public string $search = '';
    #[Url] public string $status = '';
    #[Url] public string $stage = '';
    #[Url] public string $sort = 'created_at';
    #[Url] public string $direction = 'desc';
    public int $perPage = 15;
    public bool $lockStage = false;

    public function mount(?string $stage = null, bool $lockStage = false): void
    {
        if ($stage !== null && $stage !== '') {
            $this->stage = $stage;
        }
        $this->lockStage = $lockStage;
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatus(): void { $this->resetPage(); }
    public function updatingStage(): void { $this->resetPage(); }

    public function sortBy(string $col): void
    {
        $this->direction = $this->sort === $col
            ? ($this->direction === 'asc' ? 'desc' : 'asc')
            : 'asc';
        $this->sort = $col;
    }

    public function render()
    {
        $rows = LoanApplication::query()
            ->with('customer', 'product')
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($q) use ($term) {
                    $q->where('application_number', 'like', $term)
                        ->orWhereHas('customer', fn ($q) => $q->where('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term)
                            ->orWhere('phone', 'like', $term));
                });
            })
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->stage !== '', fn ($q) => $q->where('current_stage', $this->stage))
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->perPage);

        $statuses = ['pending', 'under_review', 'approved', 'rejected', 'cancelled'];
        $stages = LoanApplication::STAGES;

        return view('livewire.admin.loan-applications-table', compact('rows', 'statuses', 'stages'));
    }
}
