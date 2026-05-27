<?php

namespace App\Livewire\Admin;

use App\Models\ChartOfAccount;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ChartOfAccountsTable extends Component
{
    use WithPagination;

    #[Url(as: 'q')] public string $search = '';
    #[Url] public string $sort = 'code';
    #[Url] public string $direction = 'asc';
    public int $perPage = 25;

    public function updatingSearch(): void { $this->resetPage(); }
    public function sortBy(string $col): void {
        $this->direction = $this->sort === $col ? ($this->direction === 'asc' ? 'desc' : 'asc') : 'asc';
        $this->sort = $col;
    }

    public function render()
    {
        $rows = ChartOfAccount::query()
            ->when($this->search !== '', fn($q) => $q->where('name','like','%'.$this->search.'%')->orWhere('code','like','%'.$this->search.'%'))
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->perPage);

        return view('livewire.admin.chart-of-accounts-table', compact('rows'));
    }
}
