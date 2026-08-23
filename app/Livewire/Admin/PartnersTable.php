<?php

namespace App\Livewire\Admin;

use App\Services\PartnerEfficiencyService;
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
    public string $status = '';
    public bool $lockStatus = false;
    public bool $reviewOnly = false;

    public function mount(
        ?string $category = null,
        bool $lockCategory = false,
        ?string $status = null,
        bool $lockStatus = false,
        bool $reviewOnly = false,
    ): void {
        $this->category = (string) ($category ?? '');
        $this->lockCategory = $lockCategory;
        $this->status = (string) ($status ?? '');
        $this->lockStatus = $lockStatus;
        $this->reviewOnly = $reviewOnly || ($lockStatus && $this->status === 'inactive');

        if ($this->lockCategory && filled($this->category)) {
            $this->role = $this->category;
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

        $query = $partners->filteredQuery($role, $this->search ?: null);
        if (filled($this->status)) {
            $query->where('status', $this->status);
        }

        $rows = $query->orderByDesc('id')->paginate(15);
        $performance = app(PartnerEfficiencyService::class)->summariesFor(collect($rows->items()));

        return view('livewire.admin.partners-table', [
            'rows'         => $rows,
            'roleOptions'  => $partners->roleOptions(),
            'lockCategory' => $this->lockCategory,
            'lockedRole'   => $this->category,
            'reviewOnly'   => $this->reviewOnly,
            'performance'  => $performance,
        ]);
    }
}
