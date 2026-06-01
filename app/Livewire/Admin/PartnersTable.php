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
        $rows = $partners->filteredQuery($this->role ?: null, $this->search ?: null)
            ->orderByDesc('id')
            ->paginate(15);

        return view('livewire.admin.partners-table', [
            'rows'        => $rows,
            'roleOptions' => $partners->roleOptions(),
        ]);
    }
}
