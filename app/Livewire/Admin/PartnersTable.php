<?php

namespace App\Livewire\Admin;

use App\Services\PartnerService;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class PartnersTable extends Component
{
    use WithPagination;

    #[Url(as: 'q')] public string $search = '';
    #[Url] public string $role = '';

    public string $category = '';
    public bool $lockCategory = false;

    public function mount(?string $category = null, bool $lockCategory = false): void
    {
        if (filled($category)) {
            $this->category = $category;
            $this->role = $category;
            $this->lockCategory = $lockCategory;
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingRole(): void
    {
        $this->resetPage();
    }

    public function render(PartnerService $partners)
    {
        $role = $this->lockCategory && filled($this->category)
            ? $this->category
            : ($this->role ?: null);

        $rows = $partners->filteredQuery($role, $this->search ?: null)
            ->orderByDesc('id')
            ->paginate(15);

        return view('livewire.admin.partners-table', [
            'rows'         => $rows,
            'roleOptions'  => $partners->roleOptions(),
            'lockCategory' => $this->lockCategory,
            'lockedRole'   => $this->category,
        ]);
    }
}
