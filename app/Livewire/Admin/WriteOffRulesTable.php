<?php

namespace App\Livewire\Admin;

use App\Models\WriteOffRule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class WriteOffRulesTable extends Component
{
    use WithPagination;
    #[Url(as: 'q')] public string $search = '';
    #[Url] public string $sort = 'days_past_due';
    #[Url] public string $direction = 'asc';
    public int $perPage = 15;

    public function updatingSearch(): void { $this->resetPage(); }
    public function sortBy(string $col): void { $this->direction = $this->sort === $col ? ($this->direction === 'asc' ? 'desc' : 'asc') : 'asc'; $this->sort = $col; }

    public function render()
    {
        $rows = WriteOffRule::query()
            ->when($this->search !== '', fn($q) => $q->where('name','like','%'.$this->search.'%'))
            ->orderBy($this->sort, $this->direction)->paginate($this->perPage);
        return view('livewire.admin.write-off-rules-table', compact('rows'));
    }
}
