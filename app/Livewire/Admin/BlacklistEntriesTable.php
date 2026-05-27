<?php

namespace App\Livewire\Admin;

use App\Models\BlacklistEntry;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class BlacklistEntriesTable extends Component
{
    use WithPagination;
    #[Url(as: 'q')] public string $search = '';
    #[Url] public string $sort = 'created_at';
    #[Url] public string $direction = 'desc';
    public int $perPage = 15;

    public function updatingSearch(): void { $this->resetPage(); }
    public function sortBy(string $col): void { $this->direction = $this->sort === $col ? ($this->direction === 'asc' ? 'desc' : 'asc') : 'asc'; $this->sort = $col; }

    public function render()
    {
        $rows = BlacklistEntry::with('addedBy')
            ->when($this->search !== '', fn($q) => $q->where('identifier_value','like','%'.$this->search.'%')->orWhere('reason','like','%'.$this->search.'%'))
            ->orderBy($this->sort, $this->direction)->paginate($this->perPage);
        return view('livewire.admin.blacklist-entries-table', compact('rows'));
    }
}
