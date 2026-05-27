<?php

namespace App\Livewire\Admin;

use App\Models\Guarantor;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class GuarantorsTable extends Component
{
    use WithPagination;

    #[Url(as: 'q')] public string $search = '';
    #[Url] public string $sort = 'created_at';
    #[Url] public string $direction = 'desc';
    public int $perPage = 15;

    public function updatingSearch(): void { $this->resetPage(); }

    public function sortBy(string $col): void
    {
        $this->direction = $this->sort === $col
            ? ($this->direction === 'asc' ? 'desc' : 'asc')
            : 'asc';
        $this->sort = $col;
    }

    public function render()
    {
        $rows = Guarantor::query()
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($q) use ($term) {
                    $q->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('national_id', 'like', $term);
                });
            })
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->perPage);

        return view('livewire.admin.guarantors-table', compact('rows'));
    }
}
