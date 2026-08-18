<?php

namespace App\Livewire\Admin;

use App\Models\Repayment;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class RepaymentsTable extends Component
{
    use WithPagination;

    #[Url(as: 'q')] public string $search = '';
    #[Url] public string $status = '';
    #[Url] public string $sort = 'created_at';
    #[Url(as: 'sortDir')] public string $direction = 'desc';
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
        $rows = Repayment::query()
            ->with('loan.customer')
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($q) use ($term) {
                    $q->where('reference', 'like', $term)
                        ->orWhere('channel', 'like', $term)
                        ->orWhereHas('loan', fn ($q) => $q->where('loan_number', 'like', $term));
                });
            })
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->orderBy($this->sort, $this->safeDirection())
            ->paginate($this->perPage);

        $statuses = ['received', 'allocated', 'reversed', 'pending'];

        return view('livewire.admin.repayments-table', compact('rows', 'statuses'));
    }

    private function safeDirection(): string
    {
        return in_array($this->direction, ['asc', 'desc'], true) ? $this->direction : 'desc';
    }
}
