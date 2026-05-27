<?php

namespace App\Livewire\Admin;

use App\Models\Settlement;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class SettlementsTable extends Component
{
    use WithPagination;

    #[Url(as: 'q')] public string $search = '';
    #[Url] public string $status = '';
    #[Url] public string $sort = 'settlement_date';
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
        $rows = Settlement::query()
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($q) use ($term) {
                    $q->where('reference', 'like', $term)
                        ->orWhere('partner', 'like', $term);
                });
            })
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->perPage);

        $statuses = ['pending', 'reconciled', 'disputed'];

        return view('livewire.admin.settlements-table', compact('rows', 'statuses'));
    }
}
