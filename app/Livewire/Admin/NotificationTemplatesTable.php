<?php

namespace App\Livewire\Admin;

use App\Models\NotificationTemplate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class NotificationTemplatesTable extends Component
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
        $rows = NotificationTemplate::query()
            ->when($this->search !== '', fn($q) => $q->where('name','like','%'.$this->search.'%')->orWhere('code','like','%'.$this->search.'%'))
            ->orderBy($this->sort, $this->direction)->paginate($this->perPage);
        return view('livewire.admin.notification-templates-table', compact('rows'));
    }
}
