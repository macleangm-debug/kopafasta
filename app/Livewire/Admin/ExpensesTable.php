<?php

namespace App\Livewire\Admin;

use App\Models\Expense;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ExpensesTable extends Component
{
    use WithPagination;

    #[Url(as: 'q')] public string $search = '';
    #[Url] public string $status = '';
    #[Url] public string $sort = 'expense_date';
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
        $rows = Expense::query()
            ->with('vendor', 'branch')
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($q) use ($term) {
                    $q->where('description', 'like', $term)
                        ->orWhere('reference', 'like', $term)
                        ->orWhere('category', 'like', $term);
                });
            })
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->perPage);

        $statuses = ['recorded', 'approved', 'paid', 'rejected'];

        return view('livewire.admin.expenses-table', compact('rows', 'statuses'));
    }
}
