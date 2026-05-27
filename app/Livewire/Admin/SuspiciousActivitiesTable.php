<?php

namespace App\Livewire\Admin;

use App\Models\SuspiciousActivity;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class SuspiciousActivitiesTable extends Component
{
    use WithPagination;
    #[Url(as: 'q')] public string $search = '';
    #[Url] public string $sort = 'detected_at';
    #[Url] public string $direction = 'desc';
    public int $perPage = 15;

    public function updatingSearch(): void { $this->resetPage(); }
    public function sortBy(string $col): void { $this->direction = $this->sort === $col ? ($this->direction === 'asc' ? 'desc' : 'asc') : 'asc'; $this->sort = $col; }

    public function render()
    {
        $rows = SuspiciousActivity::with(['customer', 'rule'])
            ->when($this->search !== '', fn($q) => $q->where('activity_type','like','%'.$this->search.'%')->orWhere('description','like','%'.$this->search.'%'))
            ->orderBy($this->sort, $this->direction)->paginate($this->perPage);
        return view('livewire.admin.suspicious-activities-table', compact('rows'));
    }
}
